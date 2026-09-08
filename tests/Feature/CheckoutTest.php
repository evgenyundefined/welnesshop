<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Payments\PendingPaymentGateway;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    private const array PAYLOAD = [
        'contact_name' => 'Иван Петров',
        'contact_email' => 'ivan@example.com',
        'contact_phone' => '+79990000000',
        'shipping_address' => 'Москва, Тверская 1',
        'comment' => 'Позвонить заранее',
        'payment_method' => 'card',
        'delivery_method' => 'courier',
    ];

    public function test_a_customer_can_turn_the_cart_into_an_order(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['price_minor' => 1_500_00, 'stock' => 4]);

        $this->actingAs($customer);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2]);

        $response = $this->postJson(route('api.checkout'), self::PAYLOAD);

        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::AwaitingPayment->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.total_minor', 3_000_00)
            ->assertJsonPath('data.items.0.product_name', $product->name)
            ->assertJsonPath('data.items.0.quantity', 2);

        $order = Order::query()->where('number', $response->json('data.number'))->sole();

        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(3_000_00, $order->total_minor);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_with_an_empty_cart_is_refused(): void
    {
        $this->actingAs(Customer::factory()->create());

        $this->postJson(route('api.checkout'), self::PAYLOAD)->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_is_refused_when_stock_dropped_after_the_line_was_added(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);

        $this->actingAs($customer);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 4]);

        $product->update(['stock' => 1]);

        $this->postJson(route('api.checkout'), self::PAYLOAD)->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_a_customer_sees_only_their_own_orders(): void
    {
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);

        $this->actingAs($owner);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $number = $this->postJson(route('api.checkout'), self::PAYLOAD)->json('data.number');

        $this->getJson(route('api.orders.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $number);

        $this->actingAs($intruder);

        $this->getJson(route('api.orders.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('api.orders.show', $number))->assertNotFound();
        $this->postJson(route('api.orders.pay', $number))->assertNotFound();
    }

    public function test_the_payment_endpoint_reports_that_no_gateway_is_connected(): void
    {
        $number = $this->placeOrder();

        $this->postJson(route('api.orders.pay', $number))
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.provider', PendingPaymentGateway::PROVIDER)
            ->assertJsonPath('data.confirmation_url', null);

        $order = Order::query()->where('number', $number)->sole();

        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_a_paid_order_cannot_be_paid_again(): void
    {
        $number = $this->placeOrder();

        Order::query()->where('number', $number)->update(['status' => OrderStatus::Paid]);

        $this->postJson(route('api.orders.pay', $number))->assertConflict();
    }

    private function placeOrder(): string
    {
        $product = $this->makeProduct(['stock' => 5]);

        $this->actingAs(Customer::factory()->create());
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);

        return $this->postJson(route('api.checkout'), self::PAYLOAD)->assertCreated()->json('data.number');
    }
}
