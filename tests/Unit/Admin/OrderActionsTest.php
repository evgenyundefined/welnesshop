<?php

namespace Tests\Unit\Admin;

use App\Actions\Admin\Orders\CreateOrder;
use App\Actions\Admin\Orders\DeleteOrder;
use App\Actions\Admin\Orders\ListOrders;
use App\Actions\Admin\Orders\ShowOrder;
use App\Actions\Admin\Orders\UpdateOrder;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Exceptions\CancelledOrderIsFinal;
use App\Exceptions\ProductNotAvailable;
use App\Models\Customer;
use App\Models\Order;
use Tests\TestCase;

class OrderActionsTest extends TestCase
{
    public function test_creating_an_order_by_hand_snapshots_lines_and_takes_stock(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['name' => 'Эпиталон', 'price_minor' => 120_000, 'stock' => 5]);

        $order = ($this->app->make(CreateOrder::class))($customer, [$product->id => 2], $this->details());

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(240_000, $order->total_minor);
        $this->assertTrue($order->relationLoaded('customer'));

        $line = $order->items()->sole();
        $this->assertSame('Эпиталон', $line->product_name);
        $this->assertSame(120_000, $line->unit_price_minor);
        $this->assertSame(3, $product->refresh()->stock);
    }

    public function test_creating_an_order_refuses_more_units_than_are_in_stock(): void
    {
        $product = $this->makeProduct(['stock' => 1]);

        $this->expectException(ProductNotAvailable::class);

        try {
            ($this->app->make(CreateOrder::class))(Customer::factory()->create(), [$product->id => 2], $this->details());
        } finally {
            $this->assertSame(1, $product->refresh()->stock);
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_creating_an_order_refuses_a_product_that_is_not_published(): void
    {
        $product = $this->makeProduct(['status' => ProductStatus::Draft, 'stock' => 10]);

        $this->expectException(ProductNotAvailable::class);

        try {
            ($this->app->make(CreateOrder::class))(Customer::factory()->create(), [$product->id => 1], $this->details());
        } finally {
            $this->assertSame(10, $product->refresh()->stock);
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_showing_an_order_loads_its_lines_and_customer(): void
    {
        $order = $this->order();

        $loaded = ($this->app->make(ShowOrder::class))($order);

        $this->assertTrue($loaded->relationLoaded('items'));
        $this->assertTrue($loaded->relationLoaded('customer'));
    }

    public function test_marking_paid_stamps_the_date_once_and_leaving_paid_clears_it(): void
    {
        $order = $this->order();
        $update = $this->app->make(UpdateOrder::class);

        $paid = $update($order, ['status' => OrderStatus::Paid]);
        $stamp = $paid->paid_at;

        $this->assertNotNull($stamp);

        $this->travel(1)->minutes();

        $again = $update($order, ['status' => OrderStatus::Paid]);
        $this->assertSame($stamp->toString(), $again->paid_at->toString());

        $back = $update($order, ['status' => OrderStatus::AwaitingPayment]);
        $this->assertNull($back->paid_at);
    }

    public function test_cancelling_returns_stock_exactly_once(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->order($product, 3);
        $update = $this->app->make(UpdateOrder::class);

        $this->assertSame(2, $product->refresh()->stock);

        $update($order, ['status' => OrderStatus::Cancelled]);
        $this->assertSame(5, $product->refresh()->stock);

        $update($order, ['status' => OrderStatus::Cancelled]);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_a_cancelled_order_cannot_move_to_another_status(): void
    {
        $order = $this->order();
        $update = $this->app->make(UpdateOrder::class);

        $update($order, ['status' => OrderStatus::Cancelled]);

        $this->expectException(CancelledOrderIsFinal::class);

        try {
            $update($order, ['status' => OrderStatus::Paid]);
        } finally {
            $this->assertSame(OrderStatus::Cancelled, Order::query()->find($order->id)->status);
        }
    }

    public function test_an_update_without_a_status_keeps_the_current_one(): void
    {
        $order = $this->order();

        $updated = ($this->app->make(UpdateOrder::class))($order, ['contact_name' => 'Анна Смирнова']);

        $this->assertSame(OrderStatus::AwaitingPayment, $updated->status);
        $this->assertSame('Анна Смирнова', $updated->contact_name);
    }

    public function test_deleting_an_open_order_returns_its_units(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->order($product, 3);

        ($this->app->make(DeleteOrder::class))($order);

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_deleting_a_cancelled_order_does_not_return_its_units_twice(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->order($product, 3);

        ($this->app->make(UpdateOrder::class))($order, ['status' => OrderStatus::Cancelled]);
        ($this->app->make(DeleteOrder::class))($order);

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_the_listing_is_newest_first_and_filters_by_status_and_search(): void
    {
        $list = $this->app->make(ListOrders::class);
        $product = $this->makeProduct(['stock' => 20]);

        $older = $this->order($product, 1);
        $older->update(['status' => OrderStatus::Paid]);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = $this->order($product, 1, ['contact_name' => 'Анна Смирнова']);

        $this->assertSame([$newer->id, $older->id], $list(null, null, null)->pluck('id')->all());
        $this->assertSame([$older->id], $list(null, OrderStatus::Paid, null)->pluck('id')->all());
        $this->assertSame([$newer->id], $list('Анна', null, null)->pluck('id')->all());
        $this->assertSame([$older->id], $list($older->number, null, null)->pluck('id')->all());
        $this->assertSame(1, $list(null, null, null)->first()->items_count);
    }

    private function order(mixed $product = null, int $quantity = 1, array $details = []): Order
    {
        $product ??= $this->makeProduct(['stock' => 10]);

        return $this->makeOrder(Customer::factory()->create(), [$product->id => $quantity], $details);
    }

    /** @return array<string, mixed> */
    private function details(): array
    {
        return [
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+79990000000',
            'shipping_address' => 'Москва, Тверская 1',
            'comment' => null,
            'payment_method' => PaymentMethod::Card,
            'delivery_method' => DeliveryMethod::Courier,
        ];
    }
}
