<?php

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Notifications\OrderNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Text is built now, so it follows the reader's Accept-Language
        $text = OrderNotification::text($this->type, $this->data);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $text['title'],
            'message' => $text['message'],
            'order_id' => $this->data['order_id'] ?? null,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
