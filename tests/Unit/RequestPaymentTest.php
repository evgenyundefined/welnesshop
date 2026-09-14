<?php

namespace Tests\Unit;

use App\Actions\Orders\RequestPayment;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotPayable;
use App\Models\Customer;
use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Payments\PaymentIntent;
use App\Payments\PendingPaymentGateway;
use Tests\TestCase;

class RequestPaymentTest extends TestCase
{
    public function test_the_default_gateway_leaves_the_payment_pending(): void
    {
        $order = $this->order();

        $intent = $this->app->make(RequestPayment::class)($order);

        $this->assertSame(PaymentStatus::Pending, $intent->status);
        $this->assertSame(PendingPaymentGateway::PROVIDER, $intent->provider);
        $this->assertNull($intent->confirmationUrl);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_it_stores_the_status_reported_by_the_gateway(): void
    {
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => new class implements PaymentGateway
        {
            public function isLive(): bool
            {
                return true;
            }

            public function createPayment(Order $order): PaymentIntent
            {
                return new PaymentIntent(
                    status: PaymentStatus::AwaitingGateway,
                    provider: 'acquirer',
                    message: 'Перенаправьте покупателя',
                    confirmationUrl: 'https://acquirer.test/pay/1',
                    externalId: 'ext-1',
                );
            }
        });

        $order = $this->order();

        $intent = $this->app->make(RequestPayment::class)($order);

        $this->assertSame('https://acquirer.test/pay/1', $intent->confirmationUrl);
        $this->assertSame('ext-1', $intent->externalId);
        $this->assertSame(PaymentStatus::AwaitingGateway, $order->fresh()->payment_status);
    }

    public function test_it_refuses_an_order_that_is_not_awaiting_payment(): void
    {
        foreach ([OrderStatus::Paid, OrderStatus::Cancelled] as $status) {
            $order = $this->order(['status' => $status]);

            try {
                $this->app->make(RequestPayment::class)($order);
                $this->fail(sprintf('A %s order accepted a payment.', $status->value));
            } catch (OrderNotPayable) {
                $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
            }
        }
    }

    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => 'WLN-TEST-'.fake()->unique()->bothify('??####'),
            'customer_id' => Customer::factory()->create()->id,
            'status' => OrderStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'delivery_method' => DeliveryMethod::Courier,
            'currency' => config('shop.currency'),
            'total_minor' => 1_000_00,
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+79990000000',
            'shipping_address' => 'Москва, Тверская 1',
            ...$attributes,
        ]);
    }
}
