<?php

namespace App\Modules\Products\Inventory;

use App\Grpc\Inventory\V1\GetStockRequest;
use App\Grpc\Inventory\V1\InventoryServiceClient;
use App\Grpc\Inventory\V1\ReleaseRequest;
use App\Grpc\Inventory\V1\ReserveRequest;
use App\Grpc\Inventory\V1\SetStockRequest;
use App\Grpc\Inventory\V1\StockItem;
use Google\Protobuf\Internal\Message;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;
use RuntimeException;

use const Grpc\STATUS_DEADLINE_EXCEEDED;
use const Grpc\STATUS_OK;
use const Grpc\STATUS_UNAVAILABLE;

/**
 * Talks to the inventory service over gRPC. Every call has a deadline, so a stopped or
 * slow service turns into a quick InventoryUnavailableException, never an endless wait.
 */
class GrpcInventory implements InventoryService
{
    private ?InventoryServiceClient $client = null;

    public function __construct(
        private readonly string $host,
        private readonly int $timeoutMs,
    ) {}

    public function stockOf(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $response = $this->call('GetStock', new GetStockRequest(['product_ids' => array_values($productIds)]));

        $stock = [];
        foreach ($response->getItems() as $item) {
            $stock[(int) $item->getProductId()] = $item->getQuantity();
        }

        return $stock;
    }

    public function reserve(string $reservationId, array $quantities): void
    {
        $response = $this->call('Reserve', new ReserveRequest([
            'reservation_id' => $reservationId,
            'items' => $this->items($quantities),
        ]));

        if (! $response->getReserved()) {
            $available = [];
            foreach ($response->getShortages() as $item) {
                $available[(int) $item->getProductId()] = $item->getQuantity();
            }

            throw new InsufficientStockException($available);
        }
    }

    public function release(string $reservationId): void
    {
        $this->call('Release', new ReleaseRequest(['reservation_id' => $reservationId]));
    }

    public function setStock(int $productId, int $quantity): void
    {
        $this->call('SetStock', new SetStockRequest(['product_id' => $productId, 'quantity' => $quantity]));
    }

    /**
     * @param  array<int, int>  $quantities
     * @return list<StockItem>
     */
    private function items(array $quantities): array
    {
        $items = [];
        foreach ($quantities as $productId => $quantity) {
            $items[] = new StockItem(['product_id' => $productId, 'quantity' => $quantity]);
        }

        return $items;
    }

    private function call(string $method, Message $request): mixed
    {
        // gRPC deadlines are given in microseconds
        [$response, $status] = $this->client()
            ->$method($request, [], ['timeout' => $this->timeoutMs * 1000])
            ->wait();

        if ($status->code === STATUS_OK) {
            return $response;
        }

        if (in_array($status->code, [STATUS_DEADLINE_EXCEEDED, STATUS_UNAVAILABLE], true)) {
            $reason = $status->code === STATUS_DEADLINE_EXCEEDED ? 'timeout' : 'unavailable';
            Log::warning("Inventory service $reason on $method", ['details' => $status->details]);

            throw new InventoryUnavailableException($reason);
        }

        throw new RuntimeException("Inventory $method failed: [{$status->code}] {$status->details}");
    }

    private function client(): InventoryServiceClient
    {
        return $this->client ??= new InventoryServiceClient($this->host, [
            'credentials' => ChannelCredentials::createInsecure(),
            // After the service restarts, reconnect within a second or two instead of backing off for long
            'grpc.initial_reconnect_backoff_ms' => 500,
            'grpc.max_reconnect_backoff_ms' => 2000,
        ]);
    }
}
