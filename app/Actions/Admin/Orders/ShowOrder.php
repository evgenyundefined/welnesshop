<?php

namespace App\Actions\Admin\Orders;

use App\Models\Order;

class ShowOrder
{
    public function __invoke(Order $order): Order
    {
        return $order->load(['items', 'customer']);
    }
}
