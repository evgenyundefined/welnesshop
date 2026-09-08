<?php

namespace App\Actions\Admin\Customers;

use App\Models\Customer;

class UnblockCustomer
{
    public function __invoke(Customer $customer): Customer
    {
        $customer->forceFill(['blocked_at' => null])->save();

        return $customer->loadCount('orders');
    }
}
