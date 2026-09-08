<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use Tests\TestCase;

class CartTest extends TestCase
{
    public function test_a_guest_cart_survives_between_requests(): void
    {
        $product = $this->makeProduct(['price_minor' => 1_000_00, 'stock' => 5]);

        $added = $this->postJson(route('api.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $added->assertOk()
            ->assertJsonPath('data.total_quantity', 2)
            ->assertJsonPath('data.total_minor', 2_000_00);

        $this->carrySession($added);

        $this->getJson(route('api.cart.show'))
            ->assertOk()
            ->assertJsonPath('data.total_quantity', 2)
            ->assertJsonPath('data.items.0.product.id', $product->id);
    }

    public function test_adding_the_same_product_twice_sums_the_quantity(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 10]);

        $this->actingAs($customer);

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 3])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.total_quantity', 5);

        $this->assertSame(5, (int) CartItem::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_a_line_may_hold_exactly_the_remaining_stock_but_no_more(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 4]);

        $this->actingAs($customer);

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.total_quantity', 4);

        $item = CartItem::query()->where('product_id', $product->id)->sole();

        $this->patchJson(route('api.cart.items.update', $item), ['quantity' => 4])->assertOk();
        $this->patchJson(route('api.cart.items.update', $item), ['quantity' => 5])->assertUnprocessable();

        $this->assertSame(4, $item->fresh()->quantity);
    }

    public function test_the_cart_refuses_more_than_the_available_stock(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 2]);

        $this->actingAs($customer);

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 3])
            ->assertUnprocessable();

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_topping_up_an_existing_line_beyond_stock_is_refused(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 3]);

        $this->actingAs($customer);

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertUnprocessable();

        $this->assertSame(2, (int) CartItem::query()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_a_customer_can_update_remove_and_clear_lines(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['price_minor' => 500_00, 'stock' => 10]);

        $this->actingAs($customer);

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $item = CartItem::query()->where('product_id', $product->id)->sole();

        $this->patchJson(route('api.cart.items.update', $item), ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.total_minor', 2_000_00);

        $this->deleteJson(route('api.cart.items.destroy', $item))
            ->assertOk()
            ->assertJsonPath('data.total_quantity', 0);

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2]);

        $this->deleteJson(route('api.cart.clear'))->assertOk()->assertJsonCount(0, 'data.items');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_a_customer_cannot_touch_a_line_of_another_cart(): void
    {
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 10]);

        $this->actingAs($owner);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2]);

        $item = CartItem::query()->where('product_id', $product->id)->sole();

        $this->actingAs($intruder);

        $this->patchJson(route('api.cart.items.update', $item), ['quantity' => 9])->assertNotFound();
        $this->deleteJson(route('api.cart.items.destroy', $item))->assertNotFound();

        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_the_guest_cart_is_merged_on_sign_in(): void
    {
        $customer = Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);
        $product = $this->makeProduct(['stock' => 10]);

        $added = $this->postJson(route('api.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->carrySession($added);

        $login = $this->postJson(route('api.login'), [
            'email' => 'buyer@example.com',
            'password' => 'Password1',
        ])->assertOk();

        $this->carrySession($login);

        $this->getJson(route('api.cart.show'))->assertOk()->assertJsonPath('data.total_quantity', 2);

        $cart = Cart::query()->where('customer_id', $customer->id)->sole();
        $this->assertSame(2, (int) $cart->items()->sum('quantity'));
        $this->assertSame(0, Cart::query()->whereNull('customer_id')->count());
    }

    public function test_the_guest_cart_is_merged_on_registration(): void
    {
        $product = $this->makeProduct(['stock' => 10]);

        $added = $this->postJson(route('api.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $this->carrySession($added);

        $registered = $this->postJson(route('api.register'), [
            'name' => 'Новый покупатель',
            'email' => 'new@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertCreated();

        $this->carrySession($registered);

        $this->getJson(route('api.cart.show'))->assertOk()->assertJsonPath('data.total_quantity', 3);
    }
}
