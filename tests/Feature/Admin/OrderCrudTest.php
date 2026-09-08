<?php

namespace Tests\Feature\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAdmin();
    }

    public function test_the_listing_carries_the_customer_and_the_line_count(): void
    {
        $customer = Customer::factory()->create(['name' => 'Иван Петров']);
        $first = $this->makeProduct(['stock' => 10]);
        $second = $this->makeProduct(['stock' => 10]);
        $order = $this->makeOrder($customer, [$first->id => 1, $second->id => 2]);

        $this->getJson(route('admin.api.orders.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.number', $order->number)
            ->assertJsonPath('data.0.items_count', 2)
            ->assertJsonPath('data.0.customer.id', $customer->id)
            ->assertJsonPath('data.0.customer.name', 'Иван Петров');
    }

    public function test_the_listing_filters_by_status_and_searches_by_number_and_contact(): void
    {
        $product = $this->makeProduct(['stock' => 20]);
        $paid = $this->makeOrder(Customer::factory()->create(), [$product->id => 1], [
            'contact_name' => 'Анна Смирнова',
            'contact_email' => 'anna@example.com',
        ]);
        $paid->update(['status' => OrderStatus::Paid]);
        $pending = $this->makeOrder(Customer::factory()->create(), [$product->id => 1]);

        $this->getJson(route('admin.api.orders.index', ['status' => OrderStatus::Paid->value]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paid->id);

        $this->getJson(route('admin.api.orders.index', ['search' => $pending->number]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->getJson(route('admin.api.orders.index', ['search' => 'anna@example.com']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paid->id);
    }

    public function test_the_listing_does_not_query_per_row(): void
    {
        $product = $this->makeProduct(['stock' => 50]);

        foreach (range(1, 4) as $ignored) {
            $this->makeOrder(Customer::factory()->create(), [$product->id => 1]);
        }

        DB::enableQueryLog();

        $this->getJson(route('admin.api.orders.index'))->assertOk()->assertJsonCount(4, 'data');

        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));

        DB::disableQueryLog();
    }

    public function test_an_admin_can_read_one_order_with_its_lines(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['name' => 'Эпиталон', 'price_minor' => 120_000, 'stock' => 10]);
        $order = $this->makeOrder($customer, [$product->id => 2]);

        $this->getJson(route('admin.api.orders.show', ['order' => $order->id]))
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.total_minor', 240_000)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'Эпиталон')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.customer.id', $customer->id);
    }

    public function test_an_admin_can_create_an_order_by_hand_and_it_takes_stock(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['price_minor' => 100_000, 'stock' => 5]);

        $this->postJson(route('admin.api.orders.store'), [
            'customer_id' => $customer->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 3]],
            'contact_name' => 'Иван Петров',
            'contact_email' => 'IVAN@Example.com',
            'contact_phone' => '+79990000000',
            'shipping_address' => 'Москва, Тверская 1',
            'payment_method' => PaymentMethod::Invoice->value,
            'delivery_method' => DeliveryMethod::TransportCompany->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.status', OrderStatus::AwaitingPayment->value)
            ->assertJsonPath('data.payment_status', PaymentStatus::Pending->value)
            ->assertJsonPath('data.payment_method', PaymentMethod::Invoice->value)
            ->assertJsonPath('data.total_minor', 300_000)
            ->assertJsonPath('data.contact_email', 'ivan@example.com');

        $this->assertSame(2, $product->refresh()->stock);
    }

    public function test_a_hand_written_order_shows_up_in_the_customers_own_order_list(): void
    {
        $customer = Customer::factory()->create(['password' => 'Password1']);
        $product = $this->makeProduct(['stock' => 5]);

        $this->postJson(route('admin.api.orders.store'), [
            'customer_id' => $customer->id,
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+79990000000',
            'shipping_address' => 'Москва, Тверская 1',
            'payment_method' => PaymentMethod::Card->value,
            'delivery_method' => DeliveryMethod::Courier->value,
        ])->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->actingAs($customer, 'web');

        $this->getJson(route('api.orders.index'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_creating_an_order_cannot_oversell_or_reach_a_hidden_product(): void
    {
        $customer = Customer::factory()->create();
        $short = $this->makeProduct(['stock' => 1]);
        $draft = $this->makeProduct(['status' => ProductStatus::Draft, 'stock' => 10]);

        $details = [
            'customer_id' => $customer->id,
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+79990000000',
            'shipping_address' => 'Москва, Тверская 1',
            'payment_method' => PaymentMethod::Card->value,
            'delivery_method' => DeliveryMethod::Courier->value,
        ];

        $this->postJson(route('admin.api.orders.store'), [
            ...$details,
            'lines' => [['product_id' => $short->id, 'quantity' => 2]],
        ])->assertUnprocessable();

        $this->postJson(route('admin.api.orders.store'), [
            ...$details,
            'lines' => [['product_id' => $draft->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $short->refresh()->stock);
    }

    public function test_creating_an_order_validates_its_input(): void
    {
        $this->postJson(route('admin.api.orders.store'), [
            'customer_id' => 999,
            'lines' => [],
            'contact_email' => 'not-an-email',
            'payment_method' => 'bitcoin',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_id',
                'lines',
                'contact_name',
                'contact_email',
                'contact_phone',
                'shipping_address',
                'payment_method',
            ]);
    }

    public function test_the_same_product_cannot_appear_twice_in_one_order(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 10]);

        $this->postJson(route('admin.api.orders.store'), [
            'customer_id' => $customer->id,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+79990000000',
            'shipping_address' => 'Москва, Тверская 1',
            'payment_method' => PaymentMethod::Card->value,
            'delivery_method' => DeliveryMethod::Courier->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.1.product_id');
    }

    public function test_marking_an_order_paid_stamps_the_payment_date(): void
    {
        $order = $this->makeOrder(Customer::factory()->create(), [$this->makeProduct(['stock' => 5])->id => 1]);

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), [
            ...$this->payload($order),
            'status' => OrderStatus::Paid->value,
            'payment_status' => PaymentStatus::Succeeded->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Paid->value)
            ->assertJsonPath('data.paid_at', fn (?string $value): bool => $value !== null);

        $this->assertNotNull($order->refresh()->paid_at);
    }

    public function test_cancelling_an_order_returns_its_units_to_stock(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder(Customer::factory()->create(), [$product->id => 3]);

        $this->assertSame(2, $product->refresh()->stock);

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), [
            ...$this->payload($order),
            'status' => OrderStatus::Cancelled->value,
        ])->assertOk()->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_cancelling_twice_does_not_invent_stock(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder(Customer::factory()->create(), [$product->id => 3]);

        $payload = [...$this->payload($order), 'status' => OrderStatus::Cancelled->value];

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), $payload)->assertOk();
        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), $payload)->assertOk();

        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_a_cancelled_order_cannot_be_revived(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder(Customer::factory()->create(), [$product->id => 3]);

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), [
            ...$this->payload($order),
            'status' => OrderStatus::Cancelled->value,
        ])->assertOk();

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), [
            ...$this->payload($order),
            'status' => OrderStatus::Paid->value,
        ])
            ->assertConflict()
            ->assertJsonPath('message', 'Отменённый заказ нельзя вернуть в работу — оформите новый.');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_a_cancelled_order_is_no_longer_payable_by_the_customer(): void
    {
        $customer = Customer::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder($customer, [$product->id => 1]);

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), [
            ...$this->payload($order),
            'status' => OrderStatus::Cancelled->value,
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs($customer, 'web');

        $this->postJson(route('api.orders.pay', $order))->assertConflict();
    }

    public function test_an_admin_can_correct_the_contact_details(): void
    {
        $order = $this->makeOrder(Customer::factory()->create(), [$this->makeProduct(['stock' => 5])->id => 1]);

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), [
            ...$this->payload($order),
            'contact_name' => '  Анна Смирнова  ',
            'contact_email' => '  ANNA@Example.com ',
            'shipping_address' => 'Санкт-Петербург, Невский 2',
            'comment' => 'Позвонить заранее',
        ])
            ->assertOk()
            ->assertJsonPath('data.contact_name', 'Анна Смирнова')
            ->assertJsonPath('data.contact_email', 'anna@example.com')
            ->assertJsonPath('data.comment', 'Позвонить заранее');

        $this->assertSame('Санкт-Петербург, Невский 2', $order->refresh()->shipping_address);
    }

    public function test_updating_an_order_validates_its_input(): void
    {
        $order = $this->makeOrder(Customer::factory()->create(), [$this->makeProduct(['stock' => 5])->id => 1]);

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), [
            'status' => 'shipped',
            'payment_status' => 'refunded',
            'payment_method' => 'bitcoin',
            'contact_name' => 'И',
            'contact_email' => 'nope',
            'contact_phone' => '1',
            'shipping_address' => 'x',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'payment_status',
                'payment_method',
                'contact_name',
                'contact_email',
                'contact_phone',
                'shipping_address',
            ]);
    }

    public function test_deleting_an_open_order_returns_its_units_and_removes_the_lines(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder(Customer::factory()->create(), [$product->id => 3]);

        $this->deleteJson(route('admin.api.orders.destroy', ['order' => $order->id]))->assertNoContent();

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
    }

    public function test_deleting_a_cancelled_order_does_not_return_its_units_twice(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder(Customer::factory()->create(), [$product->id => 3]);

        $this->putJson(route('admin.api.orders.update', ['order' => $order->id]), [
            ...$this->payload($order),
            'status' => OrderStatus::Cancelled->value,
        ])->assertOk();

        $this->deleteJson(route('admin.api.orders.destroy', ['order' => $order->id]))->assertNoContent();

        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_an_unknown_order_is_a_not_found(): void
    {
        $this->getJson(route('admin.api.orders.show', ['order' => 999]))->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function payload(Order $order): array
    {
        return [
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'payment_method' => $order->payment_method->value,
            'delivery_method' => $order->delivery_method->value,
            'contact_name' => $order->contact_name,
            'contact_email' => $order->contact_email,
            'contact_phone' => $order->contact_phone,
            'shipping_address' => $order->shipping_address,
            'comment' => $order->comment,
        ];
    }
}
