<?php

namespace App\Actions\Admin\Orders;

use App\Actions\Orders\ReleaseOrderStock;
use App\Enums\OrderStatus;
use App\Exceptions\CancelledOrderIsFinal;
use App\Models\Order;

class UpdateOrder
{
    public function __construct(private readonly ReleaseOrderStock $releaseOrderStock) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws CancelledOrderIsFinal
     * @throws \Throwable
     */
    public function __invoke(Order $order, array $attributes): Order
    {
        $status = $attributes['status'] ?? $order->status;

        if ($order->status === OrderStatus::Cancelled && $status !== OrderStatus::Cancelled) {
            throw new CancelledOrderIsFinal;
        }

        if ($status === OrderStatus::Cancelled && $order->status !== OrderStatus::Cancelled) {
            ($this->releaseOrderStock)($order);
        }

        $order->fill([
            ...$attributes,
            'paid_at' => $status === OrderStatus::Paid ? $order->paid_at ?? now() : null,
        ]);

        // The lines are fixed once the order exists, so a changed delivery
        // cost is the only thing that can move the total.
        if ($order->isDirty('delivery_cost_minor')) {
            $order->total_minor = $order->items()->sum('total_minor') + $order->delivery_cost_minor;
        }

        $order->save();

        return $order->load(['items', 'customer']);
    }
}
