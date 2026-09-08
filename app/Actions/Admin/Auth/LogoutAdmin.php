<?php

namespace App\Actions\Admin\Auth;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Session\Store as Session;

class LogoutAdmin
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly Session $session,
    ) {}

    /**
     * Dropping just this guard leaves a customer signed in on the storefront
     * when an administrator signs out of the panel in the same browser.
     */
    public function __invoke(): void
    {
        $this->guard->logout();
        $this->session->regenerateToken();
    }
}
