// Runs against a real MySQL database (the inventory-db container):
//   docker compose run --rm inventory npm test
import { after, before, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import grpc from '@grpc/grpc-js';
import { config } from '../src/config.js';
import { connect } from '../src/db.js';
import { createServer, listen, loadProto } from '../src/server.js';

let pool;
let server;
let client;

const call = (method, request) => new Promise((resolve, reject) => {
  client[method](request, { deadline: Date.now() + 5000 }, (error, response) => (error ? reject(error) : resolve(response)));
});

before(async () => {
  pool = await connect({ ...config.db, database: process.env.TEST_DB_DATABASE ?? config.db.database });
  server = createServer(pool, { delayMs: 0 });
  const port = await listen(server, 0);
  const { InventoryService } = loadProto();
  client = new InventoryService(`127.0.0.1:${port}`, grpc.credentials.createInsecure());
});

after(async () => {
  client.close();
  await new Promise((resolve) => server.tryShutdown(resolve));
  await pool.end();
});

// Test products live in a high id range so they never touch real data
const A = 9_000_001;
const B = 9_000_002;

beforeEach(async () => {
  await pool.query('DELETE ri FROM reservation_items ri WHERE ri.product_id >= 9000000');
  await pool.query('DELETE FROM stock WHERE product_id >= 9000000');
  await call('SetStock', { productId: A, quantity: 10 });
  await call('SetStock', { productId: B, quantity: 1 });
});

test('GetStock returns each product, unknown ones as 0', async () => {
  const { items } = await call('GetStock', { productIds: [A, B, 9_000_999] });
  assert.deepEqual(items.map((i) => [i.productId, i.quantity]), [[A, 10], [B, 1], [9_000_999, 0]]);
});

test('Reserve takes the stock of every item', async () => {
  const result = await call('Reserve', { reservationId: randomUUID(), items: [{ productId: A, quantity: 3 }, { productId: B, quantity: 1 }] });
  assert.equal(result.reserved, true);

  const { items } = await call('GetStock', { productIds: [A, B] });
  assert.deepEqual(items.map((i) => i.quantity), [7, 0]);
});

test('Reserve is all or nothing and reports what is left', async () => {
  const result = await call('Reserve', { reservationId: randomUUID(), items: [{ productId: A, quantity: 2 }, { productId: B, quantity: 5 }] });
  assert.equal(result.reserved, false);
  assert.deepEqual(result.shortages.map((s) => [s.productId, s.quantity]), [[B, 1]]);

  const { items } = await call('GetStock', { productIds: [A, B] });
  assert.deepEqual(items.map((i) => i.quantity), [10, 1], 'nothing was taken');
});

test('Release gives the stock back once, even if called twice', async () => {
  const id = randomUUID();
  await call('Reserve', { reservationId: id, items: [{ productId: A, quantity: 4 }] });

  assert.equal((await call('Release', { reservationId: id })).released, true);
  assert.equal((await call('Release', { reservationId: id })).released, true);

  const { items } = await call('GetStock', { productIds: [A] });
  assert.equal(items[0].quantity, 10);
});

test('Reserving twice with the same id takes the stock once', async () => {
  const id = randomUUID();
  await call('Reserve', { reservationId: id, items: [{ productId: A, quantity: 4 }] });
  await call('Reserve', { reservationId: id, items: [{ productId: A, quantity: 4 }] });

  const { items } = await call('GetStock', { productIds: [A] });
  assert.equal(items[0].quantity, 6);
});

test('Race condition: 20 simultaneous orders for the last piece, exactly one wins', async () => {
  const attempts = Array.from({ length: 20 }, () =>
    call('Reserve', { reservationId: randomUUID(), items: [{ productId: B, quantity: 1 }] }));

  const results = await Promise.all(attempts);
  assert.equal(results.filter((r) => r.reserved).length, 1);
  assert.equal(results.filter((r) => !r.reserved).length, 19);

  const { items } = await call('GetStock', { productIds: [B] });
  assert.equal(items[0].quantity, 0, 'stock never goes below zero');
});

test('A reservation whose caller gave up (deadline passed) does not take stock', async () => {
  const slowServer = createServer(pool, { delayMs: 400 });
  const port = await listen(slowServer, 0);
  const { InventoryService } = loadProto();
  const slowClient = new InventoryService(`127.0.0.1:${port}`, grpc.credentials.createInsecure());

  const error = await new Promise((resolve) => {
    slowClient.Reserve(
      { reservationId: randomUUID(), items: [{ productId: A, quantity: 5 }] },
      { deadline: Date.now() + 100 },
      (err) => resolve(err),
    );
  });
  assert.equal(error.code, grpc.status.DEADLINE_EXCEEDED);

  // Let the slow handler finish its delay; it must notice the caller is gone and do nothing
  await new Promise((resolve) => setTimeout(resolve, 600));
  const { items } = await call('GetStock', { productIds: [A] });
  assert.equal(items[0].quantity, 10, 'stock untouched');

  slowClient.close();
  await new Promise((resolve) => slowServer.tryShutdown(resolve));
});

test('Bad input is rejected with INVALID_ARGUMENT', async () => {
  await assert.rejects(call('Reserve', { reservationId: 'not-a-uuid', items: [{ productId: A, quantity: 1 }] }),
    (error) => error.code === grpc.status.INVALID_ARGUMENT);
  await assert.rejects(call('Reserve', { reservationId: randomUUID(), items: [{ productId: A, quantity: 0 }] }),
    (error) => error.code === grpc.status.INVALID_ARGUMENT);
  await assert.rejects(call('Reserve', { reservationId: randomUUID(), items: [] }),
    (error) => error.code === grpc.status.INVALID_ARGUMENT);
});
