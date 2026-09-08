<?php

namespace Tests\Unit\Admin;

use App\Actions\Admin\Customers\BlockCustomer;
use App\Actions\Admin\Customers\ListCustomers;
use App\Actions\Admin\Customers\UnblockCustomer;
use App\Actions\Admin\Customers\UpdateCustomer;
use App\Models\Customer;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerActionsTest extends TestCase
{
    public function test_blocking_stamps_the_time_and_unblocking_clears_it(): void
    {
        $customer = Customer::factory()->create();
        $block = $this->app->make(BlockCustomer::class);
        $unblock = $this->app->make(UnblockCustomer::class);

        $blocked = $block($customer);

        $this->assertTrue($blocked->isBlocked());
        $this->assertSame(0, $blocked->orders_count);
        $this->assertNotNull(Customer::query()->find($customer->id)->blocked_at);

        $active = $unblock($customer);

        $this->assertFalse($active->isBlocked());
        $this->assertNull(Customer::query()->find($customer->id)->blocked_at);
    }

    public function test_blocking_does_not_touch_anything_else_on_the_record(): void
    {
        $customer = Customer::factory()->create(['name' => 'Иван', 'password' => 'Password1']);
        $hash = $customer->password;

        ($this->app->make(BlockCustomer::class))($customer);

        $fresh = Customer::query()->find($customer->id);

        $this->assertSame('Иван', $fresh->name);
        $this->assertSame($hash, $fresh->password);
    }

    public function test_updating_hashes_a_new_password_and_keeps_the_old_one_otherwise(): void
    {
        $customer = Customer::factory()->create(['password' => 'Password1']);
        $hash = $customer->password;
        $update = $this->app->make(UpdateCustomer::class);

        $update($customer, ['name' => 'Новое имя', 'email' => 'new@example.com', 'phone' => null]);

        $this->assertSame($hash, Customer::query()->find($customer->id)->password);

        $update($customer, [
            'name' => 'Новое имя',
            'email' => 'new@example.com',
            'phone' => null,
            'password' => 'Freshpass9',
        ]);

        $stored = Customer::query()->find($customer->id)->password;

        $this->assertNotSame('Freshpass9', $stored);
        $this->assertTrue(Hash::check('Freshpass9', $stored));
    }

    public function test_updating_cannot_smuggle_the_blocked_flag_through_mass_assignment(): void
    {
        $customer = Customer::factory()->create();
        ($this->app->make(BlockCustomer::class))($customer);

        $this->expectException(MassAssignmentException::class);

        try {
            ($this->app->make(UpdateCustomer::class))($customer, [
                'name' => 'Иван',
                'email' => 'ivan@example.com',
                'phone' => null,
                'blocked_at' => null,
            ]);
        } finally {
            $this->assertTrue(Customer::query()->find($customer->id)->isBlocked());
        }
    }

    public function test_the_listing_filters_sorts_and_counts(): void
    {
        $list = $this->app->make(ListCustomers::class);

        $older = Customer::factory()->create(['name' => 'Иван Петров', 'created_at' => now()->subDay()]);
        $newer = Customer::factory()->create(['name' => 'Анна Смирнова']);
        ($this->app->make(BlockCustomer::class))($older);

        $product = $this->makeProduct(['stock' => 5]);
        $this->makeOrder($older, [$product->id => 1]);

        $all = $list(null, null, null);
        $this->assertSame([$newer->id, $older->id], $all->pluck('id')->all());

        $this->assertSame([$older->id], $list(null, true, null)->pluck('id')->all());
        $this->assertSame([$newer->id], $list(null, false, null)->pluck('id')->all());
        $this->assertSame([$older->id], $list('Петров', null, null)->pluck('id')->all());
        $this->assertSame(1, $list('Петров', null, null)->first()->orders_count);
        $this->assertSame(1, $list(null, null, 1)->perPage());
    }

    public function test_the_listing_falls_back_to_the_configured_page_size(): void
    {
        config(['shop.admin_per_page' => 3]);

        Customer::factory()->count(5)->create();

        $page = ($this->app->make(ListCustomers::class))(null, null, null);

        $this->assertSame(3, $page->perPage());
        $this->assertCount(3, $page->items());
        $this->assertSame(5, $page->total());
    }
}
