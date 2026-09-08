<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_customer_can_register_and_stays_signed_in(): void
    {
        $response = $this->postJson(route('api.register'), [
            'name' => 'Иван Петров',
            'email' => 'Ivan@Example.COM',
            'phone' => '+79990000000',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'ivan@example.com');

        $customer = Customer::query()->where('email', 'ivan@example.com')->sole();
        $this->assertTrue(Hash::check('Password1', $customer->password));

        $this->carrySession($response);

        $this->getJson(route('api.me'))->assertOk()->assertJsonPath('data.id', $customer->id);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        Customer::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(route('api.register'), [
            'name' => 'Тест',
            'email' => 'taken@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, Customer::query()->where('email', 'taken@example.com')->count());
    }

    public function test_registration_requires_a_matching_confirmation(): void
    {
        $this->postJson(route('api.register'), [
            'name' => 'Тест',
            'email' => 'fresh@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password2',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('customers', ['email' => 'fresh@example.com']);
    }

    public function test_customer_can_sign_in(): void
    {
        $customer = Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $response = $this->postJson(route('api.login'), [
            'email' => '  BUYER@Example.COM ',
            'password' => 'Password1',
        ]);

        $response->assertOk()->assertJsonPath('data.id', $customer->id);

        $this->carrySession($response);

        $this->getJson(route('api.me'))->assertOk()->assertJsonPath('data.id', $customer->id);
    }

    public function test_a_wrong_password_is_rejected_and_starts_no_session(): void
    {
        Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $response = $this->postJson(route('api.login'), [
            'email' => 'buyer@example.com',
            'password' => 'WrongPassword1',
        ])->assertUnauthorized();

        $this->carrySession($response);

        $this->getJson(route('api.me'))->assertUnauthorized();
    }

    public function test_guests_cannot_reach_authenticated_endpoints(): void
    {
        $this->getJson(route('api.me'))->assertUnauthorized();
        $this->getJson(route('api.orders.index'))->assertUnauthorized();
        $this->postJson(route('api.checkout'))->assertUnauthorized();
        $this->postJson(route('api.logout'))->assertUnauthorized();
    }

    public function test_logout_closes_every_authenticated_route(): void
    {
        Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $login = $this->postJson(route('api.login'), [
            'email' => 'buyer@example.com',
            'password' => 'Password1',
        ])->assertOk();

        $this->carrySession($login);

        $logout = $this->postJson(route('api.logout'))->assertNoContent();

        $this->carrySession($logout);

        $this->getJson(route('api.me'))->assertUnauthorized();
        $this->getJson(route('api.orders.index'))->assertUnauthorized();
        $this->postJson(route('api.checkout'))->assertUnauthorized();
    }
}
