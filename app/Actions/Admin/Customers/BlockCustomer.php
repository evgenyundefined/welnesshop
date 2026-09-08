<?php

namespace App\Actions\Admin\Customers;

use App\Models\Customer;

class BlockCustomer
{
    public function __invoke(Customer $customer): Customer
    {
        $customer->forceFill(['blocked_at' => now()])->save();

        return $customer->loadCount('orders');
    }
}
