<?php

namespace App\Actions\Auth;

use App\Actions\Cart\MergeGuestCart;
use App\Exceptions\CustomerIsBlocked;
use App\Exceptions\InvalidCredentials;
use App\Models\Customer;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Session\Store as Session;

class LoginCustomer
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly Session $session,
        private readonly MergeGuestCart $mergeGuestCart,
    ) {}

    /**
     * @throws CustomerIsBlocked
     * @throws InvalidCredentials
     */
    public function __invoke(string $email, string $password, bool $remember): Customer
    {
        $blocked = false;

        // attemptWhen verifies the credentials before the callback runs and skips
        // the login entirely when it returns false, so a blocked account never
        // gets a session -- not even a short-lived one.
        $signedIn = $this->guard->attemptWhen(
            compact('email', 'password'),
            function (Customer $customer) use (&$blocked): bool {
                $blocked = $customer->isBlocked();

                return ! $blocked;
            },
            $remember,
        );

        if ($blocked) {
            throw new CustomerIsBlocked;
        }

        if (! $signedIn) {
            throw new InvalidCredentials;
        }

        $this->session->regenerate();

        /** @var Customer $customer */
        $customer = $this->guard->user();
        ($this->mergeGuestCart)($customer);

        return $customer;
    }
}
