<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * One browser can hold both a storefront and a panel session, because both
 * guards ride the same session cookie. Neither side may evict the other.
 */
class SharedSessionTest extends TestCase
{
    private const PASSWORD = 'Password1';

    public function test_blocking_a_customer_does_not_sign_the_admin_out_of_the_same_browser(): void
    {
        $admin = Admin::factory()->create(['password' => self::PASSWORD]);
        $customer = Customer::factory()->create(['password' => self::PASSWORD]);

        $this->carrySession($this->signInAsAdmin($admin));
        $this->carrySession($this->signInAsCustomer($customer));

        $this->carrySession(
            $this->postJson(route('admin.api.customers.block', $customer))
                ->assertOk()
                ->assertJsonPath('data.is_blocked', true),
        );

        $this->carrySession($this->getJson(route('api.me'))->assertForbidden());

        $this->getJson(route('admin.api.me'))->assertOk()->assertJsonPath('data.id', $admin->id);

        $this->deleteJson(route('admin.api.customers.unblock', $customer))
            ->assertOk()
            ->assertJsonPath('data.is_blocked', false);
    }

    public function test_a_customer_logging_out_leaves_the_admin_signed_in(): void
    {
        $admin = Admin::factory()->create(['password' => self::PASSWORD]);
        $customer = Customer::factory()->create(['password' => self::PASSWORD]);

        $this->carrySession($this->signInAsAdmin($admin));
        $this->carrySession($this->signInAsCustomer($customer));

        $this->carrySession($this->postJson(route('api.logout'))->assertNoContent());

        $this->getJson(route('api.me'))->assertUnauthorized();
        $this->getJson(route('admin.api.me'))->assertOk()->assertJsonPath('data.id', $admin->id);
    }

    public function test_an_admin_logging_out_leaves_the_customer_signed_in(): void
    {
        $admin = Admin::factory()->create(['password' => self::PASSWORD]);
        $customer = Customer::factory()->create(['password' => self::PASSWORD]);

        $this->carrySession($this->signInAsAdmin($admin));
        $this->carrySession($this->signInAsCustomer($customer));

        $this->carrySession($this->postJson(route('admin.api.logout'))->assertNoContent());

        $this->getJson(route('admin.api.me'))->assertUnauthorized();
        $this->getJson(route('api.me'))->assertOk()->assertJsonPath('data.id', $customer->id);
    }

    public function test_signing_out_still_rotates_the_csrf_token(): void
    {
        $customer = Customer::factory()->create(['password' => self::PASSWORD]);

        $this->carrySession($this->signInAsCustomer($customer));
        $before = session()->token();

        $this->postJson(route('api.logout'))->assertNoContent();

        $this->assertNotSame($before, session()->token());
    }

    /**
     * A page load fires several storefront requests off the same session; the
     * eviction turns the first one away and the rest arrive as a guest, and
     * none of them may leave the session without the administrator.
     */
    public function test_the_requests_that_follow_an_eviction_never_drop_the_admin(): void
    {
        $admin = Admin::factory()->create(['password' => self::PASSWORD]);
        $customer = Customer::factory()->create(['password' => self::PASSWORD]);

        $this->carrySession($this->signInAsAdmin($admin));
        $this->carrySession($this->signInAsCustomer($customer));
        $this->carrySession($this->postJson(route('admin.api.customers.block', $customer))->assertOk());

        $this->carrySession($this->getJson(route('api.me'))->assertForbidden());

        $this->carrySession($this->getJson(route('api.products'))->assertOk());
        $this->carrySession($this->getJson(route('api.cart.show'))->assertOk());
        $this->carrySession($this->getJson(route('api.orders.index'))->assertUnauthorized());

        $this->getJson(route('admin.api.me'))->assertOk()->assertJsonPath('data.id', $admin->id);
    }

    private function signInAsAdmin(Admin $admin): TestResponse
    {
        return $this->postJson(route('admin.api.login'), [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ])->assertOk();
    }

    private function signInAsCustomer(Customer $customer): TestResponse
    {
        return $this->postJson(route('api.login'), [
            'email' => $customer->email,
            'password' => self::PASSWORD,
        ])->assertOk();
    }
}
