<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Payments\PaymentIntent;

class ApplyPaymentResult
{
    /**
     * Moves the order to whatever the acquirer says. Paying an order twice is
     * a no-op rather than a second paid_at, because a webhook can arrive more
     * than once and the buyer can return to the page after it did.
     */
    public function __invoke(Order $order, PaymentIntent $intent): Order
    {
        $order->fill([
            'payment_status' => $intent->status,
            'payment_provider' => $intent->provider,
            'payment_external_id' => $intent->externalId ?? $order->payment_external_id,
        ]);

        if ($intent->status === PaymentStatus::Succeeded && $order->status !== OrderStatus::Cancelled) {
            $order->status = OrderStatus::Paid;
            $order->paid_at ??= now();
        }

        $order->save();

        return $order;
    }
}
