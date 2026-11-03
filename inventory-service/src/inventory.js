// Stock rules, independent of gRPC so they can be tested directly.

export class CallCancelled extends Error {}

/**
 * Merge duplicate products and sort by id. Locking rows in a fixed order means two
 * reservations can never wait on each other (no deadlock).
 */
function normalize(items) {
  const totals = new Map();
  for (const { productId, quantity } of items) {
    totals.set(productId, (totals.get(productId) ?? 0) + quantity);
  }
  return [...totals].map(([productId, quantity]) => ({ productId, quantity })).sort((a, b) => a.productId - b.productId);
}

export async function getStock(pool, productIds) {
  if (productIds.length === 0) return [];
  const [rows] = await pool.query('SELECT product_id, quantity FROM stock WHERE product_id IN (?)', [productIds]);
  const found = new Map(rows.map((row) => [Number(row.product_id), row.quantity]));
  return productIds.map((productId) => ({ productId, quantity: found.get(productId) ?? 0 }));
}

/**
 * All or nothing. The stock rows are locked (SELECT ... FOR UPDATE) until the transaction ends,
 * so when several orders want the last piece at the same moment they are served one after the
 * other: the first takes it, the others then see 0 and are refused. This is the race condition fix.
 */
export async function reserve(pool, reservationId, items, { isCancelled = () => false } = {}) {
  const wanted = normalize(items);
  const conn = await pool.getConnection();

  try {
    await conn.beginTransaction();

    // Same reservation id again (e.g. the caller retried after a timeout): don't reserve twice
    const [existing] = await conn.query('SELECT status FROM reservations WHERE id = ? FOR UPDATE', [reservationId]);
    if (existing.length > 0) {
      await conn.commit();
      return { reserved: existing[0].status === 'reserved', shortages: [] };
    }

    const ids = wanted.map((item) => item.productId);
    const [rows] = await conn.query(
      'SELECT product_id, quantity FROM stock WHERE product_id IN (?) ORDER BY product_id FOR UPDATE',
      [ids],
    );
    const available = new Map(rows.map((row) => [Number(row.product_id), row.quantity]));

    const shortages = wanted
      .filter((item) => (available.get(item.productId) ?? 0) < item.quantity)
      .map((item) => ({ productId: item.productId, quantity: available.get(item.productId) ?? 0 }));

    if (shortages.length > 0) {
      await conn.rollback();
      return { reserved: false, shortages };
    }

    for (const item of wanted) {
      await conn.query('UPDATE stock SET quantity = quantity - ? WHERE product_id = ?', [item.quantity, item.productId]);
    }
    await conn.query("INSERT INTO reservations (id, status) VALUES (?, 'reserved')", [reservationId]);
    await conn.query(
      'INSERT INTO reservation_items (reservation_id, product_id, quantity) VALUES ?',
      [wanted.map((item) => [reservationId, item.productId, item.quantity])],
    );

    // The caller gave up (its deadline passed) while we were working: nobody will use this
    // reservation, so don't keep it. Otherwise the stock would stay taken by an order that never exists.
    if (isCancelled()) {
      await conn.rollback();
      throw new CallCancelled();
    }

    await conn.commit();
    return { reserved: true, shortages: [] };
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}

/**
 * Return a reservation's quantities to stock. Running it twice returns them only once.
 */
export async function release(pool, reservationId) {
  const conn = await pool.getConnection();

  try {
    await conn.beginTransaction();

    const [found] = await conn.query('SELECT status FROM reservations WHERE id = ? FOR UPDATE', [reservationId]);
    if (found.length === 0) {
      await conn.commit();
      return { released: false };
    }

    if (found[0].status === 'reserved') {
      const [items] = await conn.query(
        'SELECT product_id, quantity FROM reservation_items WHERE reservation_id = ? ORDER BY product_id',
        [reservationId],
      );
      for (const item of items) {
        await conn.query('UPDATE stock SET quantity = quantity + ? WHERE product_id = ?', [item.quantity, item.product_id]);
      }
      await conn.query("UPDATE reservations SET status = 'released', released_at = NOW() WHERE id = ?", [reservationId]);
    }

    await conn.commit();
    return { released: true };
  } catch (error) {
    await conn.rollback();
    throw error;
  } finally {
    conn.release();
  }
}

export async function setStock(pool, productId, quantity) {
  await pool.query(
    'INSERT INTO stock (product_id, quantity) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
    [productId, quantity],
  );
  return { productId, quantity };
}
