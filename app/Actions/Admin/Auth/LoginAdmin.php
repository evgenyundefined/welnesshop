<?php

namespace App\Actions\Admin\Auth;

use App\Exceptions\InvalidCredentials;
use App\Models\Admin;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Session\Store as Session;

class LoginAdmin
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly Session $session,
    ) {}

    /**
     * @throws InvalidCredentials
     */
    public function __invoke(string $email, string $password, bool $remember): Admin
    {
        if (! $this->guard->attempt(compact('email', 'password'), $remember)) {
            throw new InvalidCredentials;
        }

        $this->session->regenerate();

        /** @var Admin $admin */
        $admin = $this->guard->user();

        return $admin;
    }
}
