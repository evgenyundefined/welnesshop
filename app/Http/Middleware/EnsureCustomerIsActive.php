<?php

namespace App\Http\Middleware;

use App\Actions\Auth\LogoutCustomer;
use App\Enums\Guard;
use App\Exceptions\CustomerIsBlocked;
use App\Models\Customer;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocking has to bite on the requests a customer already holds a session for,
 * and on the ones a "remember me" cookie would silently sign back in.
 */
class EnsureCustomerIsActive
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly LogoutCustomer $logoutCustomer,
    ) {}

    /**
     * @throws CustomerIsBlocked
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $this->auth->guard(Guard::Customer->value)->user();

        if ($customer instanceof Customer && $customer->isBlocked()) {
            ($this->logoutCustomer)();

            throw new CustomerIsBlocked;
        }

        return $next($request);
    }
}
