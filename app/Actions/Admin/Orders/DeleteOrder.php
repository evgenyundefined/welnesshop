<?php

namespace App\Actions\Admin\Orders;

use App\Actions\Orders\ReleaseOrderStock;
use App\Enums\OrderStatus;
use App\Models\Order;

class DeleteOrder
{
    public function __construct(private readonly ReleaseOrderStock $releaseOrderStock) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(Order $order): void
    {
        // A cancelled order already handed its units back; releasing twice would
        // invent stock that does not exist.
        if ($order->status !== OrderStatus::Cancelled) {
            ($this->releaseOrderStock)($order);
        }

        $order->delete();
    }
}
