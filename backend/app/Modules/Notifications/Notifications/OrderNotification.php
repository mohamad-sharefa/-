<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for every order-related notification. Each one is stored in the database (shown on the
 * notifications page) and sent by email. Only the data is stored; the text is built when it is
 * shown, in the reader's language (see text()).
 */
abstract class OrderNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    /**
     * Short name stored in the notifications.type column, e.g. "order_placed".
     */
    abstract public function kind(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function data(): array;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->kind();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['order_id' => $this->order->id, ...$this->data()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ['title' => $title, 'message' => $message] = self::text($this->kind(), $this->toArray($notifiable));

        return (new MailMessage)
            ->subject($title)
            ->line($message)
            ->action(__('notifications.view_order'), url('/orders/'.$this->order->id));
    }

    /**
     * Title and message of a stored notification, in the current language.
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, message: string}
     */
    public static function text(string $kind, array $data): array
    {
        $replace = [
            'order' => $data['order_id'] ?? '',
            'total' => isset($data['total']) ? '$'.$data['total'] : '',
            'status' => isset($data['status']) ? __('orders.statuses.'.$data['status']) : '',
            'customer' => $data['customer_name'] ?? '',
        ];

        return [
            'title' => __("notifications.$kind.title", $replace),
            'message' => __("notifications.$kind.message", $replace),
        ];
    }
}
