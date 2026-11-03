import grpc from '@grpc/grpc-js';
import protoLoader from '@grpc/proto-loader';
import { setTimeout as sleep } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';
import { config } from './config.js';
import { connect } from './db.js';
import * as inventory from './inventory.js';

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

class InvalidArgument extends Error {}

export function loadProto(protoPath = config.protoPath) {
  const definition = protoLoader.loadSync(protoPath, { longs: Number, defaults: true });
  return grpc.loadPackageDefinition(definition).inventory.v1;
}

function positiveId(value) {
  if (!Number.isSafeInteger(value) || value <= 0) throw new InvalidArgument('product_id must be a positive integer');
  return value;
}

function items(list, { allowZero = false } = {}) {
  if (list.length === 0) throw new InvalidArgument('items must not be empty');
  return list.map((item) => {
    if (!Number.isInteger(item.quantity) || item.quantity < (allowZero ? 0 : 1)) {
      throw new InvalidArgument('quantity must be a positive integer');
    }
    return { productId: positiveId(item.productId), quantity: item.quantity };
  });
}

function reservationId(value) {
  if (!UUID.test(value)) throw new InvalidArgument('reservation_id must be a UUID');
  return value.toLowerCase();
}

/**
 * Wraps a handler: optional artificial delay, input errors → INVALID_ARGUMENT,
 * anything unexpected → INTERNAL (details stay in the service's log).
 */
function handler(name, fn, delayMs) {
  return async (call, callback) => {
    const started = Date.now();
    // true once the client's deadline has passed or it disconnected
    const isCancelled = () => call.cancelled;
    try {
      if (delayMs > 0) await sleep(delayMs);
      if (isCancelled()) throw new inventory.CallCancelled();

      const result = await fn(call.request, { isCancelled });
      console.log(`${name} ok ${Date.now() - started}ms`);
      callback(null, result);
    } catch (error) {
      if (error instanceof inventory.CallCancelled) {
        console.log(`${name} skipped: the caller gave up after ${Date.now() - started}ms`);
        return callback({ code: grpc.status.CANCELLED, message: 'cancelled by the caller' });
      }
      if (error instanceof InvalidArgument) {
        console.log(`${name} invalid: ${error.message}`);
        return callback({ code: grpc.status.INVALID_ARGUMENT, message: error.message });
      }
      console.error(`${name} failed`, error);
      callback({ code: grpc.status.INTERNAL, message: 'inventory error' });
    }
  };
}

export function createServer(pool, { delayMs = config.simulatedDelayMs } = {}) {
  const { InventoryService } = loadProto();
  const server = new grpc.Server();

  server.addService(InventoryService.service, {
    GetStock: handler('GetStock', async ({ productIds }) => ({
      items: await inventory.getStock(pool, productIds.map(positiveId)),
    }), delayMs),

    Reserve: handler('Reserve', async (request, { isCancelled }) => {
      return inventory.reserve(pool, reservationId(request.reservationId), items(request.items), { isCancelled });
    }, delayMs),

    Release: handler('Release', async (request) => {
      return inventory.release(pool, reservationId(request.reservationId));
    }, delayMs),

    SetStock: handler('SetStock', async ({ productId, quantity }) => {
      const [item] = items([{ productId, quantity }], { allowZero: true });
      return { item: await inventory.setStock(pool, item.productId, item.quantity) };
    }, delayMs),
  });

  return server;
}

export function listen(server, port) {
  return new Promise((resolve, reject) => {
    server.bindAsync(`0.0.0.0:${port}`, grpc.ServerCredentials.createInsecure(), (error, boundPort) => {
      if (error) reject(error);
      else resolve(boundPort);
    });
  });
}

// Started directly (node src/server.js), not imported by the tests
if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const pool = await connect(config.db);
  const server = createServer(pool);
  const port = await listen(server, config.port);
  console.log(`inventory service listening on ${port}` + (config.simulatedDelayMs ? ` (simulated delay ${config.simulatedDelayMs}ms)` : ''));

  const shutdown = () => server.tryShutdown(() => pool.end().then(() => process.exit(0)));
  process.on('SIGTERM', shutdown);
  process.on('SIGINT', shutdown);
}
