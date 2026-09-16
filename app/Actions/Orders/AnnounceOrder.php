<?php

namespace App\Actions\Orders;

use App\Mail\NewOrderMail;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\TelegramChat;
use App\Support\Money;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Mail\Mailer;

/**
 * Everyone who needs to hear about a new order hears about it here: the buyer
 * gets a confirmation, the shop gets the details a manager acts on, and the
 * chat gets a line if one is configured.
 *
 * Announcing is deliberately separate from placing: a mail server that is down
 * must not undo an order that is already paid for in stock.
 */
class AnnounceOrder
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly Config $config,
        private readonly TelegramChat $telegram,
    ) {}

    public function __invoke(Order $order): void
    {
        $order->loadMissing('items');

        $this->mailer->to($order->contact_email)->send(new OrderPlacedMail($order));

        $shopAddress = (string) $this->config->get('shop.orders_email');

        if ($shopAddress !== '') {
            $this->mailer->to($shopAddress)->send(new NewOrderMail($order));
        }

        $this->telegram->send($this->chatMessage($order));
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
