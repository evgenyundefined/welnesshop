<?php

namespace Tests\Unit;

use App\Actions\Cart\MergeGuestCart;
use App\Actions\Cart\ResolveCart;
use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Session\Store as Session;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartSessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = $this->app->make(Session::class);
        $this->session->start();
    }

    public function test_resolve_reuses_the_guest_cart_stored_in_the_session(): void
    {
        $resolve = $this->app->make(ResolveCart::class);

        $first = $resolve(null);
        $second = $resolve(null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->token, $this->sessionToken());
        $this->assertSame(1, Cart::query()->whereNull('customer_id')->count());
    }

    public function test_resolve_starts_a_new_guest_cart_when_the_token_is_unknown(): void
    {
        $this->rememberToken((string) Str::uuid());

        $cart = $this->app->make(ResolveCart::class)(null);

        $this->assertNotNull($cart->token);
        $this->assertSame($cart->token, $this->sessionToken());
    }

    public function test_resolve_returns_one_persistent_cart_per_customer(): void
    {
        $customer = Customer::factory()->create();
        $resolve = $this->app->make(ResolveCart::class);

        $first = $resolve($customer);
        $second = $resolve($customer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($customer->id, $first->customer_id);
        $this->assertSame(1, Cart::query()->where('customer_id', $customer->id)->count());
    }

    public function test_merge_moves_guest_lines_into_a_fresh_customer_cart(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 10]);

        $guestCart = $this->guestCart();
        $guestCart->items()->create(['product_id' => $product->id, 'quantity' => 2]);
        $this->rememberToken($guestCart->token);

        $cart = $this->app->make(MergeGuestCart::class)($customer);

        $this->assertSame($customer->id, $cart->customer_id);
        $this->assertSame(2, (int) $cart->items()->where('product_id', $product->id)->value('quantity'));
        $this->assertNull(Cart::query()->find($guestCart->id));
        $this->assertNull($this->sessionToken());
    }

    public function test_merge_sums_quantities_when_both_carts_hold_the_same_product(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 10]);

        $customerCart = Cart::query()->create(['customer_id' => $customer->id]);
        $customerCart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $guestCart = $this->guestCart();
        $guestCart->items()->create(['product_id' => $product->id, 'quantity' => 2]);
        $this->rememberToken($guestCart->token);

        $cart = $this->app->make(MergeGuestCart::class)($customer);

        $this->assertSame($customerCart->id, $cart->id);
        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(3, (int) $cart->items()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_the_merged_quantity_is_capped_at_the_configured_maximum(): void
    {
        config()->set('shop.max_item_quantity', 4);

        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 100]);

        $customerCart = Cart::query()->create(['customer_id' => $customer->id]);
        $customerCart->items()->create(['product_id' => $product->id, 'quantity' => 3]);

        $guestCart = $this->guestCart();
        $guestCart->items()->create(['product_id' => $product->id, 'quantity' => 3]);
        $this->rememberToken($guestCart->token);

        $cart = $this->app->make(MergeGuestCart::class)($customer);

        $this->assertSame(4, (int) $cart->items()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_merge_never_takes_over_a_cart_that_already_belongs_to_someone(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 10]);

        $otherCart = Cart::query()->create(['customer_id' => $otherCustomer->id, 'token' => (string) Str::uuid()]);
        $otherCart->items()->create(['product_id' => $product->id, 'quantity' => 7]);
        $this->rememberToken($otherCart->token);

        $cart = $this->app->make(MergeGuestCart::class)($customer);

        $this->assertNotSame($otherCart->id, $cart->id);
        $this->assertSame(0, $cart->items()->count());
        $this->assertSame(7, (int) $otherCart->items()->value('quantity'));
    }

    public function test_merge_returns_the_existing_customer_cart_when_there_is_no_guest_cart(): void
    {
        $customer = Customer::factory()->create();
        $existing = Cart::query()->create(['customer_id' => $customer->id]);

        $this->assertSame($existing->id, $this->app->make(MergeGuestCart::class)($customer)->id);
    }

    private function guestCart(): Cart
    {
        return Cart::query()->create(['token' => (string) Str::uuid()]);
    }

    private function rememberToken(string $token): void
    {
        $this->session->put(config()->string('shop.cart_session_key'), $token);
    }

    private function sessionToken(): ?string
    {
        return $this->session->get(config()->string('shop.cart_session_key'));
    }
}
