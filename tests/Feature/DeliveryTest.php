<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Models\Customer;
use App\Models\Order;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    private const array CHECKOUT = [
        'contact_name' => 'Иван Петров',
        'contact_email' => 'ivan@example.com',
        'contact_phone' => '+79990000000',
        'shipping_address' => 'Москва, Тверская 1',
        'payment_method' => 'card',
    ];

    public function test_checkout_records_a_courier_delivery_without_a_cost(): void
    {
        $customer = $this->signedInCustomer();
        $this->fillCart();

        $this->postJson(route('api.checkout'), [...self::CHECKOUT, 'delivery_method' => 'courier'])
            ->assertCreated()
            ->assertJsonPath('data.delivery_method', 'courier')
            ->assertJsonPath('data.delivery_cost_minor', 0);

        $this->assertSame(DeliveryMethod::Courier, $customer->orders()->sole()->delivery_method);
    }

    public function test_the_delivery_method_survives_into_the_order_list_and_the_order_page(): void
    {
        $this->signedInCustomer();
        $this->fillCart();

        $number = $this->postJson(route('api.checkout'), [
            ...self::CHECKOUT,
            'delivery_method' => DeliveryMethod::Courier->value,
        ])->assertCreated()->json('data.number');

        $this->getJson(route('api.orders.index'))
            ->assertOk()
            ->assertJsonPath('data.0.delivery_method', DeliveryMethod::Courier->value);

        $this->getJson(route('api.orders.show', $number))
            ->assertOk()
            ->assertJsonPath('data.delivery_method', DeliveryMethod::Courier->value);
    }

    public function test_checkout_refuses_an_unknown_or_missing_delivery_method(): void
    {
        $this->signedInCustomer();
        $this->fillCart();

        $this->postJson(route('api.checkout'), [...self::CHECKOUT, 'delivery_method' => 'teleport'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_method');

        $this->postJson(route('api.checkout'), self::CHECKOUT)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_admin_records_and_then_changes_the_delivery_method(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);

        $this->signInAdmin();

        $order = $this->postJson(route('admin.api.orders.store'), [
            'customer_id' => $customer->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            ...self::CHECKOUT,
            'delivery_method' => DeliveryMethod::Cdek->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.delivery_method', DeliveryMethod::Cdek->value)
            ->json('data');

        $this->putJson(route('admin.api.orders.update', ['order' => $order['id']]), [
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'delivery_method' => DeliveryMethod::Courier->value,
            'contact_name' => $order['contact_name'],
            'contact_email' => $order['contact_email'],
            'contact_phone' => $order['contact_phone'],
            'shipping_address' => $order['shipping_address'],
        ])
            ->assertOk()
            ->assertJsonPath('data.delivery_method', DeliveryMethod::Courier->value);

        $this->assertSame(DeliveryMethod::Courier, Order::query()->find($order['id'])->delivery_method);
    }

    public function test_the_admin_rejects_an_unknown_delivery_method(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);

        $this->signInAdmin();

        $this->postJson(route('admin.api.orders.store'), [
            'customer_id' => $customer->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            ...self::CHECKOUT,
            'delivery_method' => 'pigeon',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_every_delivery_method_has_a_label(): void
    {
        foreach (DeliveryMethod::cases() as $method) {
            $this->assertNotSame('', $method->label());
        }

        $this->assertSame('Курьером', DeliveryMethod::Courier->label());
        $this->assertSame('СДЭК', DeliveryMethod::Cdek->label());
    }

    private function signedInCustomer(): Customer
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'web');

        return $customer;
    }

    private function fillCart(): void
    {
        $product = $this->makeProduct(['stock' => 10]);

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk();
    }
}
