<?php

namespace Tests\Unit;

use App\Actions\Auth\LoginCustomer;
use App\Actions\Auth\LogoutCustomer;
use App\Actions\Auth\RegisterCustomer;
use App\Exceptions\InvalidCredentials;
use App\Models\Customer;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Session\Store as Session;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerSessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = $this->app->make(Session::class);
        $this->session->start();
    }

    public function test_registration_hashes_the_password_and_signs_the_customer_in(): void
    {
        $customer = $this->app->make(RegisterCustomer::class)([
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'phone' => '+79990000000',
            'password' => 'Password1',
        ]);

        $this->assertNotSame('Password1', $customer->password);
        $this->assertTrue(Hash::check('Password1', $customer->password));
        $this->assertSame($customer->id, $this->guard()->id());
    }

    public function test_signing_in_rotates_the_session_id_and_the_csrf_token(): void
    {
        Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $sessionId = $this->session->getId();
        $csrfToken = $this->session->token();

        $this->app->make(LoginCustomer::class)('buyer@example.com', 'Password1', false);

        $this->assertNotSame($sessionId, $this->session->getId());
        $this->assertNotSame($csrfToken, $this->session->token());
    }

    public function test_an_unknown_email_is_rejected(): void
    {
        $this->expectException(InvalidCredentials::class);

        try {
            $this->app->make(LoginCustomer::class)('nobody@example.com', 'Password1', false);
        } finally {
            $this->assertNull($this->guard()->user());
        }
    }

    public function test_signing_out_clears_the_guard_and_the_session_payload(): void
    {
        $customer = Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $this->app->make(LoginCustomer::class)('buyer@example.com', 'Password1', false);
        $this->assertSame($customer->id, $this->guard()->id());

        $this->session->put('cart_token', 'a-guest-cart-token');
        $csrfToken = $this->session->token();

        $this->app->make(LogoutCustomer::class)();

        $this->assertNull($this->guard()->user());
        $this->assertFalse($this->session->has('cart_token'), 'signing out must not leave session state behind');
        $this->assertNotEmpty($this->session->token());
        $this->assertNotSame($csrfToken, $this->session->token());
    }

    private function guard(): StatefulGuard
    {
        return $this->app->make(AuthFactory::class)->guard();
    }
}
