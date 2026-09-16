<?php

namespace Tests\Feature;

use App\Actions\Orders\AnnounceOrder;
use App\Mail\NewOrderMail;
use App\Mail\OrderPlacedMail;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WholesaleOrderTest extends TestCase
{
    private const CHECKOUT = [
        'contact_name' => 'Иван Петров',
        'contact_email' => 'ivan@example.com',
        'contact_phone' => '+7 912 345 67 89',
        'delivery_method' => 'courier',
        'shipping_address' => 'Москва, Тверская 1',
        'payment_method' => 'on_agreement',
    ];

    private function wholesaleProduct(int $minimum = 10, int $stock = 500): Product
    {
        return Product::factory()
            ->for(Category::factory()->wholesale($minimum))
            ->create(['stock' => $stock, 'price_minor' => 1_000_00]);
    }

    public function test_a_wholesale_product_says_so_next_to_its_price(): void
    {
        $product = $this->wholesaleProduct();

        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.wholesale_only', true)
            ->assertJsonPath('data.min_order_quantity', 10);

        // The grid needs it on every card, not only on the product page.
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.wholesale_only', true)
            ->assertJsonPath('data.0.min_order_quantity', 10);
    }

    public function test_a_retail_product_carries_no_wholesale_terms(): void
    {
        $product = $this->makeProduct();

        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.wholesale_only', false)
            ->assertJsonPath('data.min_order_quantity', 1);
    }

    #[DataProvider('shortQuantities')]
    public function test_the_cart_refuses_less_than_the_minimum(int $quantity): void
    {
        $product = $this->wholesaleProduct();

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertStatus(422)
            ->assertJsonPath('message', "Товар «{$product->name}» продаётся от 10 шт.");

        $this->assertDatabaseCount('cart_items', 0);
    }

    /** @return array<string, array{int}> */
    public static function shortQuantities(): array
    {
        return ['one' => [1], 'nine' => [9]];
    }

    public function test_the_cart_accepts_the_minimum(): void
    {
        $product = $this->wholesaleProduct();

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 10])->assertOk();

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 10]);
    }

    public function test_lowering_the_quantity_in_the_cart_is_refused_too(): void
    {
        $product = $this->wholesaleProduct();

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 10])->assertOk();
        $item = $this->getJson('/api/cart')->json('data.items.0.id');

        // The side door: the line is already in the cart and only its number
        // is edited.
        $this->patchJson("/api/cart/items/{$item}", ['quantity' => 5])
            ->assertStatus(422)
            ->assertJsonPath('message', "Товар «{$product->name}» продаётся от 10 шт.");

        $this->assertDatabaseHas('cart_items', ['id' => $item, 'quantity' => 10]);
    }

    public function test_checkout_refuses_a_cart_that_fell_under_the_minimum(): void
    {
        $customer = Customer::factory()->create();
        $category = Category::factory()->create(['min_order_quantity' => 1]);
        $product = Product::factory()->for($category)->create(['stock' => 100, 'price_minor' => 1_000_00]);

        $this->actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 3])->assertOk();

        // The rule arrives after the cart was filled: checkout is the gate
        // that has to hold, not only the moment the line was added.
        $category->update(['min_order_quantity' => 10]);

        $this->postJson('/api/checkout', self::CHECKOUT)
            ->assertStatus(422)
            ->assertJsonPath('message', "Товар «{$product->name}» продаётся от 10 шт.");

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_administrator_is_not_bound_by_the_minimum(): void
    {
        $product = $this->wholesaleProduct();
        $customer = Customer::factory()->create();

        // A manager entering an order by hand is agreeing the terms on the
        // phone; the storefront rule is not theirs.
        $order = $this->makeOrder($customer, [$product->id => 2]);

        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 2]);
    }

    public function test_an_order_reaches_the_buyer_the_shop_and_the_chat(): void
    {
        Mail::fake();
        config()->set('shop.orders_email', 'orders@example.com');

        $customer = Customer::factory()->create();
        $product = $this->wholesaleProduct();

        $this->actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 10])->assertOk();
        $number = $this->postJson('/api/checkout', self::CHECKOUT)->assertCreated()->json('data.number');

        Mail::assertQueued(
            OrderPlacedMail::class,
            static fn (OrderPlacedMail $mail): bool => $mail->hasTo('ivan@example.com')
                && $mail->order->number === $number,
        );

        Mail::assertQueued(
            NewOrderMail::class,
            static fn (NewOrderMail $mail): bool => $mail->hasTo('orders@example.com'),
        );
    }

    public function test_without_a_shop_address_only_the_buyer_is_written_to(): void
    {
        Mail::fake();
        config()->set('shop.orders_email', null);

        $customer = Customer::factory()->create();
        $product = $this->wholesaleProduct();

        $this->actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 10])->assertOk();
        $this->postJson('/api/checkout', self::CHECKOUT)->assertCreated();

        Mail::assertQueued(OrderPlacedMail::class);
        Mail::assertNotQueued(NewOrderMail::class);
    }

    public function test_the_order_still_stands_when_the_chat_cannot_be_reached(): void
    {
        Mail::fake();
        config()->set('services.telegram.bot_token', 'token');
        config()->set('services.telegram.chat_id', '-100');

        $customer = Customer::factory()->create();
        $product = $this->wholesaleProduct();
        $order = $this->makeOrder($customer, [$product->id => 10]);

        // Telegram is a convenience: the buyer has already been told the order
        // went through, so a refusal there cannot undo it.
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false], 500),
        ]);

        $this->app->make(AnnounceOrder::class)($order);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
