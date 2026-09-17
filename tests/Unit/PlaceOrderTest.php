<?php

namespace Tests\Unit;

use App\Actions\Orders\PlaceOrder;
use App\Enums\Currency;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\CartIsEmpty;
use App\Exceptions\ProductNotAvailable;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    private PlaceOrder $placeOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->placeOrder = $this->app->make(PlaceOrder::class);
    }

    public function test_it_snapshots_lines_decrements_stock_and_empties_the_cart(): void
    {
        $customer = Customer::factory()->create();
        $cart = $this->guestCart();
        $product = $this->makeProduct(['name' => 'Epitalon', 'price_minor' => 1_200_00, 'stock' => 5]);

        $cart->items()->create(['product_id' => $product->id, 'quantity' => 3]);

        $order = ($this->placeOrder)($customer, $cart->fresh(), $this->details());

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(PaymentMethod::Card, $order->payment_method);
        $this->assertSame(3_600_00, $order->total_minor);
        $this->assertSame('Иван Петров', $order->contact_name);
        $this->assertSame('ivan@example.com', $order->contact_email);
        $this->assertSame('+79990000000', $order->contact_phone);
        $this->assertSame('Москва, Тверская 1', $order->shipping_address);
        $this->assertSame('Позвонить заранее', $order->comment);

        $line = $order->items()->sole();
        $this->assertSame($product->id, $line->product_id);
        $this->assertSame('Epitalon', $line->product_name);
        $this->assertSame($product->slug, $line->product_slug);
        $this->assertSame(1_200_00, $line->unit_price_minor);
        $this->assertSame(3, $line->quantity);
        $this->assertSame(3_600_00, $line->total_minor);

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(0, $cart->items()->count());
    }

    public function test_the_snapshot_survives_a_later_catalog_edit(): void
    {
        $order = $this->place(['price_minor' => 1_000_00, 'stock' => 5], 1);
        $product = $order->items()->sole()->product;

        $product->update(['price_minor' => 9_999_00, 'name' => 'Переименован']);

        $line = $order->items()->sole();

        $this->assertSame(1_000_00, $line->unit_price_minor);
        $this->assertSame(1_000_00, $order->fresh()->total_minor);
        $this->assertNotSame('Переименован', $line->product_name);
    }

    public function test_it_can_order_the_full_remaining_stock(): void
    {
        $order = $this->place(['stock' => 3], 3);

        $this->assertSame(3, $order->items()->sole()->quantity);
        $this->assertSame(0, $order->items()->sole()->product->stock);
    }

    public function test_it_refuses_an_empty_cart(): void
    {
        $this->expectException(CartIsEmpty::class);

        try {
            ($this->placeOrder)(Customer::factory()->create(), $this->guestCart(), $this->details());
        } finally {
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_it_refuses_a_line_that_exceeds_the_current_stock(): void
    {
        $customer = Customer::factory()->create();
        $cart = $this->guestCart();
        $product = $this->makeProduct(['stock' => 5]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 4]);

        $product->update(['stock' => 2]);

        $this->expectException(ProductNotAvailable::class);

        try {
            ($this->placeOrder)($customer, $cart->fresh(), $this->details());
        } finally {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(2, $product->fresh()->stock);
            $this->assertSame(1, $cart->items()->count());
        }
    }

    public function test_it_reads_the_products_in_a_fixed_number_of_queries_whatever_the_line_count(): void
    {
        $this->assertSame(
            $this->productSelectsWhilePlacingOrder(2),
            $this->productSelectsWhilePlacingOrder(6),
        );
    }

    private function productSelectsWhilePlacingOrder(int $lines): int
    {
        $customer = Customer::factory()->create();
        $cart = $this->guestCart();

        collect(range(1, $lines))->each(function () use ($cart): void {
            $cart->items()->create(['product_id' => $this->makeProduct(['stock' => 5])->id, 'quantity' => 1]);
        });

        $selects = 0;
        DB::listen(function ($query) use (&$selects): void {
            if (str_contains($query->sql, 'select') && str_contains($query->sql, '"products"')) {
                $selects++;
            }
        });

        $order = ($this->placeOrder)($customer, Cart::query()->findOrFail($cart->id), $this->details());

        $this->assertSame($lines, $order->items()->count());

        return $selects;
    }

    public function test_the_order_number_uses_the_configured_prefix_and_shape(): void
    {
        config()->set('shop.order_number_prefix', 'SHOP');

        $this->assertMatchesRegularExpression('/^SHOP-\d{8}-[A-Z0-9]{6}$/', $this->place(['stock' => 5], 1)->number);
    }

    public function test_the_order_currency_comes_from_configuration(): void
    {
        config()->set('shop.currency', 'USD');

        $this->assertSame(Currency::Usd, $this->place(['stock' => 5], 1)->currency);
    }

    public function test_it_retries_until_the_generated_number_is_free(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);

        $carts = collect(range(1, 2))->map(function () use ($product): Cart {
            $cart = $this->guestCart();
            $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

            return $cart;
        });

        // The first order takes AAAAAA, so the second must skip its own AAAAAA draw.
        Str::createRandomStringsUsingSequence(['AAAAAA', 'AAAAAA', 'BBBBBB']);

        try {
            $taken = ($this->placeOrder)($customer, $carts[0]->fresh(), $this->details())->number;
            $next = ($this->placeOrder)($customer, $carts[1]->fresh(), $this->details())->number;
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertStringEndsWith('AAAAAA', $taken);
        $this->assertStringEndsWith('BBBBBB', $next);
    }

    public function test_it_generates_a_unique_number_per_order(): void
    {
        $numbers = collect(range(1, 3))->map(fn (): string => $this->place(['stock' => 5], 1)->number);

        $this->assertCount(3, $numbers->unique());
        $numbers->each(fn (string $number) => $this->assertStringStartsWith('WLN-', $number));
    }

    private function place(array $productAttributes, int $quantity): Order
    {
        $cart = $this->guestCart();
        $product = $this->makeProduct($productAttributes);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity]);

        return ($this->placeOrder)(Customer::factory()->create(), $cart->fresh(), $this->details());
    }

    private function guestCart(): Cart
    {
        return Cart::query()->create(['token' => (string) Str::uuid()]);
    }

    /**
     * @return array{
     *     contact_name: string,
     *     contact_email: string,
     *     contact_phone: string,
     *     shipping_address: string,
     *     comment: ?string,
     *     payment_method: PaymentMethod
     * }
     */
    private function details(): array
    {
        return [
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+79990000000',
            'shipping_address' => 'Москва, Тверская 1',
            'comment' => 'Позвонить заранее',
            'payment_method' => PaymentMethod::Card,
            'delivery_method' => DeliveryMethod::Courier,
        ];
    }
}
