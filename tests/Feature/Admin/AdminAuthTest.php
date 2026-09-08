<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Customer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    public function test_admin_can_sign_in_and_stays_signed_in(): void
    {
        $admin = Admin::factory()->create(['email' => 'boss@example.com', 'password' => 'Password1']);

        $response = $this->postJson(route('admin.api.login'), [
            'email' => '  BOSS@Example.COM ',
            'password' => 'Password1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.email', 'boss@example.com')
            ->assertJsonMissingPath('data.password');

        $this->carrySession($response);

        $this->getJson(route('admin.api.me'))->assertOk()->assertJsonPath('data.id', $admin->id);
    }

    public function test_a_wrong_password_leaves_no_session_behind(): void
    {
        Admin::factory()->create(['email' => 'boss@example.com', 'password' => 'Password1']);

        $response = $this->postJson(route('admin.api.login'), [
            'email' => 'boss@example.com',
            'password' => 'WrongPassword1',
        ]);

        $response->assertUnauthorized()->assertJsonPath('message', 'Неверный e-mail или пароль.');

        $this->carrySession($response);

        $this->getJson(route('admin.api.me'))->assertUnauthorized();
    }

    public function test_logout_ends_the_admin_session(): void
    {
        $admin = Admin::factory()->create(['password' => 'Password1']);

        $login = $this->postJson(route('admin.api.login'), [
            'email' => $admin->email,
            'password' => 'Password1',
        ])->assertOk();

        $this->carrySession($login);

        $logout = $this->postJson(route('admin.api.logout'))->assertNoContent();

        $this->carrySession($logout);

        $this->getJson(route('admin.api.me'))->assertUnauthorized();
        $this->getJson(route('admin.api.categories.index'))->assertUnauthorized();
    }

    #[DataProvider('guardedEndpoints')]
    public function test_a_guest_cannot_reach_a_guarded_admin_endpoint(string $method, string $route): void
    {
        $this->json($method, route($route))->assertUnauthorized();
    }

    #[DataProvider('guardedEndpoints')]
    public function test_a_signed_in_customer_cannot_reach_a_guarded_admin_endpoint(string $method, string $route): void
    {
        $customer = Customer::factory()->create(['password' => 'Password1']);

        $login = $this->postJson(route('api.login'), [
            'email' => $customer->email,
            'password' => 'Password1',
        ])->assertOk();

        $this->carrySession($login);

        $this->json($method, route($route))->assertUnauthorized();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guardedEndpoints(): array
    {
        return [
            'me' => ['GET', 'admin.api.me'],
            'logout' => ['POST', 'admin.api.logout'],
            'categories' => ['GET', 'admin.api.categories.index'],
            'products' => ['GET', 'admin.api.products.index'],
            'customers' => ['GET', 'admin.api.customers.index'],
            'orders' => ['GET', 'admin.api.orders.index'],
        ];
    }

    public function test_an_admin_session_does_not_grant_storefront_customer_access(): void
    {
        $admin = Admin::factory()->create(['password' => 'Password1']);

        $login = $this->postJson(route('admin.api.login'), [
            'email' => $admin->email,
            'password' => 'Password1',
        ])->assertOk();

        $this->carrySession($login);

        $this->getJson(route('api.me'))->assertUnauthorized();
        $this->getJson(route('api.orders.index'))->assertUnauthorized();
        $this->postJson(route('api.checkout'))->assertUnauthorized();
    }

    public function test_a_customer_password_does_not_open_the_admin_panel(): void
    {
        Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $this->postJson(route('admin.api.login'), [
            'email' => 'buyer@example.com',
            'password' => 'Password1',
        ])->assertUnauthorized();

        $this->assertGuest('admin');
    }

    public function test_an_admin_password_does_not_open_the_storefront(): void
    {
        Admin::factory()->create(['email' => 'boss@example.com', 'password' => 'Password1']);

        $this->postJson(route('api.login'), [
            'email' => 'boss@example.com',
            'password' => 'Password1',
        ])->assertUnauthorized();

        $this->assertGuest();
    }
}
