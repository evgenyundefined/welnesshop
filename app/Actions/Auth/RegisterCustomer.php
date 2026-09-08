<?php

namespace App\Actions\Auth;

use App\Actions\Cart\MergeGuestCart;
use App\Models\Customer;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Session\Store as Session;

class RegisterCustomer
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly Session $session,
        private readonly MergeGuestCart $mergeGuestCart,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: ?string, password: string}  $attributes
     */
    public function __invoke(array $attributes): Customer
    {
        $customer = Customer::query()->create($attributes);

        $this->guard->login($customer);
        $this->session->regenerate();
        ($this->mergeGuestCart)($customer);

        return $customer;
    }
}
