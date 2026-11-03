import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

export const config = {
  port: Number(process.env.GRPC_PORT ?? 50051),
  protoPath: process.env.PROTO_PATH ?? path.join(root, 'proto/inventory/v1/inventory.proto'),
  db: {
    host: process.env.DB_HOST ?? 'inventory-db',
    port: Number(process.env.DB_PORT ?? 3306),
    database: process.env.DB_DATABASE ?? 'inventory',
    user: process.env.DB_USERNAME ?? 'inventory',
    password: process.env.DB_PASSWORD ?? 'secret',
  },
  // For the "slow service" demonstration: wait this long before answering each call
  simulatedDelayMs: Number(process.env.SIMULATE_DELAY_MS ?? 0),
};
