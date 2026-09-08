<?php

namespace App\Actions\Auth;

use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Session\Store as Session;

class LogoutCustomer
{
    public function __construct(
        private readonly Config $config,
        private readonly StatefulGuard $guard,
        private readonly Session $session,
    ) {}

    /**
     * Only the storefront's own state is dropped. The panel shares this session,
     * so flushing it would sign an administrator out of the same browser, and
     * migrating it would race the sibling requests a single page load fires --
     * one of them would then persist a session the administrator is missing
     * from. Fixation is handled where it matters, when a session is granted.
     */
    public function __invoke(): void
    {
        $this->guard->logout();
        $this->session->forget($this->config->string('shop.cart_session_key'));
        $this->session->regenerateToken();
    }
}
