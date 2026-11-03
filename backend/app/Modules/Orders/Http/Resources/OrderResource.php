<?php

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Orders\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            // Statuses an admin may move this order to next
            'next_statuses' => array_map(fn (OrderStatus $status) => $status->value, $this->status->nextStatuses()),
            'total' => $this->total,
            'items_count' => $this->whenCounted('items', fn () => (int) $this->items_count),
            'quantity' => $this->whenHas('quantity', fn () => (int) $this->quantity),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
