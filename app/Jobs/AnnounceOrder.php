<?php

namespace App\Jobs;

use App\Mail\NewOrderMail;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\TelegramChat;
use App\Support\Money;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Everyone who needs to hear about a new order hears about it here: the buyer
 * gets a confirmation, the shop gets the details a manager acts on, and the
 * chat gets a line if one is configured.
 *
 * Queued, and not merely because it is polite to be quick: the chat is reached
 * over the network with a timeout measured in seconds, and a buyer who has
 * already pressed the button would spend every one of them looking at a dead
 * screen. Announcing is separate from placing for the same reason in reverse —
 * a mail server that is down must not undo an order already taken from stock.
 */
class AnnounceOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function handle(Mailer $mailer, Config $config, TelegramChat $telegram): void
    {
        $order = $this->order;

        $order->loadMissing('items');

        $mailer->to($order->contact_email)->send(new OrderPlacedMail($order));

        $shopAddress = (string) $config->get('shop.orders_email');

        if ($shopAddress !== '') {
            $mailer->to($shopAddress)->send(new NewOrderMail($order));
        }

        $telegram->send($this->chatMessage($order));
    }

    private function chatMessage(Order $order): string
    {
        $lines = $order->items
            ->map(fn (OrderItem $item): string => sprintf(
                '• %s — %d шт. · %s',
                $item->product_name,
                $item->quantity,
                Money::format($item->total_minor, $order->currency),
            ))
            ->implode("\n");

        return implode("\n", array_filter([
            "<b>Новый заказ {$order->number}</b>",
            "{$order->contact_name}, {$order->contact_phone}",
            $order->contact_email,
            'Доставка: '.$order->delivery_method->label(),
            $order->shipping_address,
            '',
            $lines,
            '',
            'Итого: '.Money::format($order->total_minor, $order->currency),
        ], static fn (string $line): bool => $line !== '' || true));
    }
}
