<?php

namespace App\Actions\Admin\Orders;

use App\Actions\Orders\RecordOrder;
use App\Exceptions\ProductNotAvailable;
use App\Models\Customer;
use App\Models\Order;

class CreateOrder
{
    public function __construct(private readonly RecordOrder $recordOrder) {}

    /**
     * @param  array<int, int>  $quantities  product id => quantity
     * @param  array<string, mixed>  $details
     *
     * @throws ProductNotAvailable
     * @throws \Throwable
     */
    public function __invoke(Customer $customer, array $quantities, array $details): Order
    {
        return ($this->recordOrder)($customer, $quantities, $details)->load('customer');
    }
}
