<?php

namespace Tests\Unit;

use App\Actions\Cart\AddCartItem;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotAvailable;
use App\Models\Cart;
use Illuminate\Support\Str;
use Tests\TestCase;

class AddCartItemTest extends TestCase
{
    private AddCartItem $addCartItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addCartItem = $this->app->make(AddCartItem::class);
    }

    public function test_it_creates_a_line(): void
    {
        $cart = $this->guestCart();
        $product = $this->makeProduct(['stock' => 5]);

        $item = ($this->addCartItem)($cart, $product, 2);

        $this->assertSame(2, $item->quantity);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_it_sums_the_quantity_of_an_existing_line(): void
    {
        $cart = $this->guestCart();
        $product = $this->makeProduct(['stock' => 5]);

        ($this->addCartItem)($cart, $product, 2);
        $item = ($this->addCartItem)($cart, $product, 3);

        $this->assertSame(5, $item->quantity);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_it_accepts_exactly_the_remaining_stock(): void
    {
        $cart = $this->guestCart();
        $product = $this->makeProduct(['stock' => 3]);

        $this->assertSame(3, ($this->addCartItem)($cart, $product, 3)->quantity);
    }

    public function test_it_refuses_more_than_the_remaining_stock(): void
    {
        $cart = $this->guestCart();
        $product = $this->makeProduct(['stock' => 2]);

        $this->expectException(ProductNotAvailable::class);

        try {
            ($this->addCartItem)($cart, $product, 3);
        } finally {
            $this->assertSame(0, $cart->items()->count());
        }
    }

    public function test_it_refuses_products_that_are_not_published(): void
    {
        $cart = $this->guestCart();

        foreach ([ProductStatus::Draft, ProductStatus::Archived] as $status) {
            $product = $this->makeProduct(['stock' => 10, 'status' => $status]);

            try {
                ($this->addCartItem)($cart, $product, 1);
                $this->fail(sprintf('A %s product was added to the cart.', $status->value));
            } catch (ProductNotAvailable) {
                $this->assertSame(0, $cart->items()->count());
            }
        }
    }

    public function test_it_refuses_more_than_the_configured_maximum(): void
    {
        config()->set('shop.max_item_quantity', 3);

        $cart = $this->guestCart();
        $product = $this->makeProduct(['stock' => 100]);

        $this->expectException(ProductNotAvailable::class);

        $this->app->make(AddCartItem::class)($cart, $product, 4);
    }

    private function guestCart(): Cart
    {
        return Cart::query()->create(['token' => (string) Str::uuid()]);
    }
}
