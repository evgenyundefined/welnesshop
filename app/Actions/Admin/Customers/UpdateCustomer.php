<?php

namespace App\Actions\Admin\Customers;

use App\Models\Customer;

class UpdateCustomer
{
    /**
     * @param  array{name: string, email: string, phone: ?string, password?: string}  $attributes
     */
    public function __invoke(Customer $customer, array $attributes): Customer
    {
        $customer->fill($attributes)->save();

        return $customer->loadCount('orders');
    }
}
