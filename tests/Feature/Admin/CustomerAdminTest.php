<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAdmin();
    }

    public function test_the_listing_counts_orders_and_never_leaks_a_password(): void
    {
        $customer = Customer::factory()->create(['password' => 'Password1']);
        $product = $this->makeProduct(['stock' => 10]);
        $this->makeOrder($customer, [$product->id => 1]);
        $this->makeOrder($customer, [$product->id => 2]);

        $this->getJson(route('admin.api.customers.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $customer->id)
            ->assertJsonPath('data.0.orders_count', 2)
            ->assertJsonPath('data.0.is_blocked', false)
            ->assertJsonPath('data.0.blocked_at', null)
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_the_listing_searches_by_name_email_and_phone(): void
    {
        $target = Customer::factory()->create([
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'phone' => '+79991234567',
        ]);
        Customer::factory()->create(['name' => 'Пётр Иванов', 'email' => 'petr@example.com', 'phone' => '+70000000000']);

        foreach (['Иван Петров', 'ivan@', '1234567'] as $term) {
            $this->getJson(route('admin.api.customers.index', ['search' => $term]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $target->id);
        }
    }

    public function test_the_blocked_filter_is_tri_state(): void
    {
        $active = Customer::factory()->create();
        $blocked = Customer::factory()->create();

        $this->postJson(route('admin.api.customers.block', $blocked))->assertOk();

        $this->getJson(route('admin.api.customers.index'))->assertOk()->assertJsonCount(2, 'data');

        $this->getJson(route('admin.api.customers.index', ['blocked' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $blocked->id);

        $this->getJson(route('admin.api.customers.index', ['blocked' => 0]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_the_listing_does_not_count_orders_per_row(): void
    {
        Customer::factory()->count(5)->create();

        DB::enableQueryLog();

        $this->getJson(route('admin.api.customers.index'))->assertOk()->assertJsonCount(5, 'data');

        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));

        DB::disableQueryLog();
    }

    public function test_an_admin_can_edit_a_customer(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'phone' => '+79990000000',
        ]);

        $this->putJson(route('admin.api.customers.update', $customer), [
            'name' => '  Иван Петров  ',
            'email' => '  IVAN.NEW@Example.COM ',
            'phone' => '  +79991112233  ',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Иван Петров')
            ->assertJsonPath('data.email', 'ivan.new@example.com')
            ->assertJsonPath('data.phone', '+7 999 111 22 33');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Иван Петров',
            'email' => 'ivan.new@example.com',
            'phone' => '+7 999 111 22 33',
        ]);
    }

    public function test_an_admin_can_reset_a_customer_password_and_the_customer_can_sign_in_with_it(): void
    {
        $customer = Customer::factory()->create(['email' => 'ivan@example.com', 'password' => 'Password1']);

        $this->putJson(route('admin.api.customers.update', $customer), [
            'name' => $customer->name,
            'email' => $customer->email,
            'password' => 'Freshpass9',
        ])->assertOk();

        $this->assertTrue(Hash::check('Freshpass9', $customer->refresh()->password));

        $this->app['auth']->forgetGuards();

        $this->postJson(route('api.login'), ['email' => 'ivan@example.com', 'password' => 'Password1'])
            ->assertUnauthorized();

        $this->postJson(route('api.login'), ['email' => 'ivan@example.com', 'password' => 'Freshpass9'])
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);
    }

    public function test_omitting_the_password_leaves_it_untouched(): void
    {
        $customer = Customer::factory()->create(['password' => 'Password1']);
        $hash = $customer->password;

        $this->putJson(route('admin.api.customers.update', $customer), [
            'name' => 'Новое имя',
            'email' => $customer->email,
        ])->assertOk();

        $this->assertSame($hash, $customer->refresh()->password);
    }

    public function test_editing_rejects_a_duplicate_email_and_a_weak_password(): void
    {
        Customer::factory()->create(['email' => 'taken@example.com']);
        $customer = Customer::factory()->create(['email' => 'ivan@example.com']);

        $this->putJson(route('admin.api.customers.update', $customer), [
            'name' => 'Иван',
            'email' => 'taken@example.com',
            'password' => '123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertSame('ivan@example.com', $customer->refresh()->email);
    }

    public function test_keeping_a_customers_own_email_is_not_a_duplicate(): void
    {
        $customer = Customer::factory()->create(['email' => 'ivan@example.com']);

        $this->putJson(route('admin.api.customers.update', $customer), [
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
        ])->assertOk();

        $this->assertSame('Иван Петров', $customer->refresh()->name);
    }

    public function test_blocking_and_unblocking_are_reflected_in_the_record(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson(route('admin.api.customers.block', $customer))
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true)
            ->assertJsonPath('data.orders_count', 0);

        $this->assertNotNull($customer->refresh()->blocked_at);

        $this->deleteJson(route('admin.api.customers.unblock', $customer))
            ->assertOk()
            ->assertJsonPath('data.is_blocked', false)
            ->assertJsonPath('data.blocked_at', null);

        $this->assertNull($customer->refresh()->blocked_at);
    }

    public function test_blocking_is_idempotent(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson(route('admin.api.customers.block', $customer))->assertOk();
        $first = $customer->refresh()->blocked_at;

        $this->travel(1)->minutes();

        $this->postJson(route('admin.api.customers.block', $customer))->assertOk()->assertJsonPath('data.is_blocked', true);

        $this->assertTrue($customer->refresh()->isBlocked());
        $this->assertNotSame($first->toString(), $customer->blocked_at->toString());
    }

    public function test_a_customer_record_shows_its_order_count(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 10]);
        $this->makeOrder($customer, [$product->id => 1]);

        $this->getJson(route('admin.api.customers.show', $customer))
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.orders_count', 1);
    }

    public function test_customers_cannot_be_created_or_deleted_through_the_panel(): void
    {
        $customer = Customer::factory()->create();

        $this->postJson(route('admin.api.customers.index'), [])->assertMethodNotAllowed();
        $this->deleteJson(url("/admin/api/customers/{$customer->id}"))->assertMethodNotAllowed();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }
}
