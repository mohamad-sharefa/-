import mysql from 'mysql2/promise';
import { setTimeout as sleep } from 'node:timers/promises';

// The inventory service's own database. The main application never connects to it.
const SCHEMA = [
  `CREATE TABLE IF NOT EXISTS stock (
     product_id BIGINT UNSIGNED PRIMARY KEY,
     quantity INT UNSIGNED NOT NULL,
     updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   )`,
  `CREATE TABLE IF NOT EXISTS reservations (
     id CHAR(36) PRIMARY KEY,
     status ENUM('reserved', 'released') NOT NULL,
     created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
     released_at TIMESTAMP NULL
   )`,
  `CREATE TABLE IF NOT EXISTS reservation_items (
     reservation_id CHAR(36) NOT NULL,
     product_id BIGINT UNSIGNED NOT NULL,
     quantity INT UNSIGNED NOT NULL,
     PRIMARY KEY (reservation_id, product_id),
     FOREIGN KEY (reservation_id) REFERENCES reservations (id)
   )`,
];

export async function connect(dbConfig, { attempts = 30 } = {}) {
  const pool = mysql.createPool({ ...dbConfig, connectionLimit: 10, supportBigNumbers: true });

  // The database container may still be starting: retry for a while
  for (let attempt = 1; ; attempt++) {
    try {
      for (const statement of SCHEMA) await pool.query(statement);
      return pool;
    } catch (error) {
      if (attempt >= attempts) throw error;
      console.log(`database not ready (${error.code ?? error.message}), retrying...`);
      await sleep(2000);
    }
  }
}
