<?php

namespace Tests\Unit\Admin;

use App\Actions\Admin\Auth\LoginAdmin;
use App\Actions\Admin\Auth\LogoutAdmin;
use App\Enums\Guard;
use App\Exceptions\InvalidCredentials;
use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminSessionTest extends TestCase
{
    public function test_signing_in_authenticates_the_admin_guard_and_rotates_the_session(): void
    {
        $admin = Admin::factory()->create(['email' => 'boss@example.com', 'password' => 'Password1']);
        $before = session()->getId();

        $signedIn = ($this->app->make(LoginAdmin::class))('boss@example.com', 'Password1', false);

        $this->assertSame($admin->id, $signedIn->id);
        $this->assertTrue(Auth::guard(Guard::Admin->value)->check());
        $this->assertNotSame($before, session()->getId());
    }

    public function test_signing_in_does_not_touch_the_customer_guard(): void
    {
        Admin::factory()->create(['email' => 'boss@example.com', 'password' => 'Password1']);

        ($this->app->make(LoginAdmin::class))('boss@example.com', 'Password1', false);

        $this->assertFalse(Auth::guard(Guard::Customer->value)->check());
    }

    public function test_a_customer_account_is_not_an_admin_account(): void
    {
        Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $this->expectException(InvalidCredentials::class);

        try {
            ($this->app->make(LoginAdmin::class))('buyer@example.com', 'Password1', false);
        } finally {
            $this->assertFalse(Auth::guard(Guard::Admin->value)->check());
        }
    }

    public function test_a_wrong_password_is_refused(): void
    {
        Admin::factory()->create(['email' => 'boss@example.com', 'password' => 'Password1']);

        $this->expectException(InvalidCredentials::class);

        try {
            ($this->app->make(LoginAdmin::class))('boss@example.com', 'Nope12345', false);
        } finally {
            $this->assertFalse(Auth::guard(Guard::Admin->value)->check());
        }
    }

    public function test_signing_out_drops_the_admin_and_the_session_token(): void
    {
        Admin::factory()->create(['email' => 'boss@example.com', 'password' => 'Password1']);

        ($this->app->make(LoginAdmin::class))('boss@example.com', 'Password1', false);

        $token = session()->token();

        ($this->app->make(LogoutAdmin::class))();

        $this->assertFalse(Auth::guard(Guard::Admin->value)->check());
        $this->assertNotSame($token, session()->token());
    }
}
