<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Payments\PendingPaymentGateway;
use App\Payments\YooKassaGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YooKassaPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('shop.integrations.online_payment', true);
        config()->set('services.yookassa.shop_id', '123456');
        config()->set('services.yookassa.secret_key', 'test_secret');
        config()->set('services.yookassa.base_url', 'https://api.yookassa.ru/v3');
    }

    /** @return array<string, mixed> */
    private function payment(string $status, string $number, string $id = 'pay-1'): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'paid' => $status === 'succeeded',
            'amount' => ['value' => '1000.00', 'currency' => 'RUB'],
            'confirmation' => [
                'type' => 'redirect',
                'confirmation_url' => 'https://yoomoney.ru/checkout/payments/v2/contract?orderId='.$id,
            ],
            'metadata' => ['order_number' => $number],
        ];
    }

    private function order(): Order
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'web');

        return $this->makeOrder($customer, [$this->makeProduct(['stock' => 5, 'price_minor' => 500_00])->id => 2]);
    }

    public function test_the_gateway_is_yookassa_once_its_credentials_are_set(): void
    {
        $this->assertInstanceOf(YooKassaGateway::class, $this->app->make(PaymentGateway::class));
    }

    public function test_without_credentials_the_shop_falls_back_to_saying_payment_is_off(): void
    {
        config()->set('services.yookassa.shop_id', null);
        $this->app->forgetInstance(PaymentGateway::class);

        $this->assertInstanceOf(PendingPaymentGateway::class, $this->app->make(PaymentGateway::class));
    }

    public function test_paying_an_order_opens_a_payment_and_hands_back_the_confirmation_url(): void
    {
        $order = $this->order();
        Http::fake(['*/payments' => Http::response($this->payment('pending', $order->number))]);

        $this->postJson(route('api.orders.pay', $order->number))
            ->assertOk()
            ->assertJsonPath('data.provider', 'yookassa')
            ->assertJsonPath('data.status', 'awaiting_gateway')
            ->assertJsonPath('data.confirmation_url', fn (string $url): bool => str_contains($url, 'yoomoney.ru'));

        $order->refresh();

        $this->assertSame(PaymentStatus::AwaitingGateway, $order->payment_status);
        $this->assertSame('pay-1', $order->payment_external_id);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);

        Http::assertSent(function (Request $request) use ($order): bool {
            $body = $request->data();

            return $request['amount']['value'] === '1000.00'
                && $body['capture'] === true
                && $body['metadata']['order_number'] === $order->number
                && str_contains($request->header('Idempotence-Key')[0], (string) $order->id)
                && str_contains($request->header('Authorization')[0], 'Basic ');
        });
    }

    public function test_the_payment_carries_a_receipt_the_register_will_accept(): void
    {
        $order = $this->order();
        Http::fake(['*/payments' => Http::response($this->payment('pending', $order->number))]);

        $this->postJson(route('api.orders.pay', $order->number))->assertOk();

        Http::assertSent(function (Request $request) use ($order): bool {
            $receipt = $request->data()['receipt'];
            $line = $order->items->sole();

            $this->assertSame($order->contact_email, $receipt['customer']['email']);
            $this->assertSame('79990000000', $receipt['customer']['phone']);
            $this->assertSame($line->product_name, $receipt['items'][0]['description']);
            $this->assertSame($line->quantity, $receipt['items'][0]['quantity']);
            $this->assertSame('500.00', $receipt['items'][0]['amount']['value']);
            $this->assertSame(1, $receipt['items'][0]['vat_code']);
            $this->assertSame('commodity', $receipt['items'][0]['payment_subject']);
            $this->assertSame('full_prepayment', $receipt['items'][0]['payment_mode']);

            return true;
        });
    }

    public function test_delivery_is_its_own_line_so_the_receipt_adds_up_to_the_charge(): void
    {
        $order = $this->order();
        $order->forceFill(['delivery_cost_minor' => 390_50, 'total_minor' => 1_390_50])->save();

        Http::fake(['*/payments' => Http::response($this->payment('pending', $order->number))]);

        $this->postJson(route('api.orders.pay', $order->number))->assertOk();

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $items = $body['receipt']['items'];

            $this->assertCount(2, $items);
            $this->assertSame('service', $items[1]['payment_subject']);
            $this->assertSame('390.50', $items[1]['amount']['value']);

            $sum = collect($items)->sum(
                static fn (array $item): float => (float) $item['amount']['value'] * $item['quantity'],
            );

            $this->assertSame($body['amount']['value'], number_format($sum, 2, '.', ''));

            return true;
        });
    }

    public function test_a_shop_without_fiscalisation_sends_no_receipt(): void
    {
        config()->set('services.yookassa.receipt', false);

        $order = $this->order();
        Http::fake(['*/payments' => Http::response($this->payment('pending', $order->number))]);

        $this->postJson(route('api.orders.pay', $order->number))->assertOk();

        Http::assertSent(static fn (Request $request): bool => ! array_key_exists('receipt', $request->data()));
    }

    public function test_the_vat_rate_and_tax_system_come_from_configuration(): void
    {
        config()->set('services.yookassa.vat_code', 4);
        config()->set('services.yookassa.tax_system_code', 2);

        $order = $this->order();
        Http::fake(['*/payments' => Http::response($this->payment('pending', $order->number))]);

        $this->postJson(route('api.orders.pay', $order->number))->assertOk();

        Http::assertSent(static fn (Request $request): bool => $request->data()['receipt']['items'][0]['vat_code'] === 4
            && $request->data()['receipt']['tax_system_code'] === 2);
    }

    public function test_two_attempts_to_pay_one_order_carry_the_same_idempotence_key(): void
    {
        $order = $this->order();
        Http::fake(['*/payments' => Http::response($this->payment('pending', $order->number))]);

        $this->postJson(route('api.orders.pay', $order->number))->assertOk();
        $this->postJson(route('api.orders.pay', $order->number))->assertOk();

        $keys = [];
        Http::assertSent(static function (Request $request) use (&$keys): bool {
            $keys[] = $request->header('Idempotence-Key')[0];

            return true;
        });

        $this->assertCount(1, array_unique($keys), 'a repeated attempt must not open a second payment');
    }

    public function test_a_settled_order_is_not_offered_for_payment_again(): void
    {
        $order = $this->order();
        Http::fake(['*/payments/pay-1' => Http::response($this->payment('succeeded', $order->number))]);

        $this->postJson(route('api.payments.yookassa.webhook'), ['object' => ['id' => 'pay-1']])->assertNoContent();

        $this->getJson(route('api.orders.show', $order->number))
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // The storefront hides the button on this status; the endpoint refuses
        // it regardless, which is what keeps a stale page honest.
        $this->postJson(route('api.orders.pay', $order->number))->assertStatus(409);
    }

    public function test_a_refusal_from_the_acquirer_reads_as_a_refusal(): void
    {
        $order = $this->order();
        Http::fake(['*/payments' => Http::response(['description' => 'Invalid shop id'], 400)]);

        $this->postJson(route('api.orders.pay', $order->number))
            ->assertStatus(502)
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Invalid shop id'));

        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
    }

    public function test_the_webhook_marks_the_order_paid_after_reading_the_payment_back(): void
    {
        $order = $this->order();
        Http::fake(['*/payments/pay-1' => Http::response($this->payment('succeeded', $order->number))]);

        $this->postJson(route('api.payments.yookassa.webhook'), [
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => ['id' => 'pay-1', 'status' => 'succeeded'],
        ])->assertNoContent();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(PaymentStatus::Succeeded, $order->payment_status);
        $this->assertNotNull($order->paid_at);

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/payments/pay-1'));
    }

    public function test_a_forged_notification_moves_nothing_because_the_acquirer_is_asked(): void
    {
        $order = $this->order();
        // The acquirer says the payment is still pending, whatever the body claimed.
        Http::fake(['*/payments/pay-1' => Http::response($this->payment('pending', $order->number))]);

        $this->postJson(route('api.payments.yookassa.webhook'), [
            'type' => 'notification',
            'event' => 'payment.succeeded',
            'object' => ['id' => 'pay-1', 'status' => 'succeeded', 'paid' => true],
        ])->assertNoContent();

        $order->refresh();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_a_cancelled_payment_leaves_the_order_unpaid(): void
    {
        $order = $this->order();
        Http::fake(['*/payments/pay-1' => Http::response($this->payment('canceled', $order->number))]);

        $this->postJson(route('api.payments.yookassa.webhook'), [
            'object' => ['id' => 'pay-1'],
        ])->assertNoContent();

        $order->refresh();

        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_the_same_notification_twice_pays_the_order_once(): void
    {
        $order = $this->order();
        Http::fake(['*/payments/pay-1' => Http::response($this->payment('succeeded', $order->number))]);

        $this->postJson(route('api.payments.yookassa.webhook'), ['object' => ['id' => 'pay-1']])->assertNoContent();
        $paidAt = $order->refresh()->paid_at;

        $this->travel(1)->hours();
        $this->postJson(route('api.payments.yookassa.webhook'), ['object' => ['id' => 'pay-1']])->assertNoContent();

        $this->assertTrue($paidAt->equalTo($order->refresh()->paid_at), 'paid_at must not move on a repeat');
    }

    public function test_a_notification_for_an_unknown_order_is_acknowledged_without_a_change(): void
    {
        Http::fake(['*/payments/pay-9' => Http::response($this->payment('succeeded', 'WLN-NOPE', 'pay-9'))]);

        $this->postJson(route('api.payments.yookassa.webhook'), ['object' => ['id' => 'pay-9']])->assertNoContent();

        $this->assertSame(0, Order::query()->where('payment_external_id', 'pay-9')->count());
    }

    public function test_a_notification_without_a_payment_id_is_ignored(): void
    {
        Http::fake();

        $this->postJson(route('api.payments.yookassa.webhook'), ['type' => 'notification'])->assertNoContent();

        Http::assertNothingSent();
    }

    public function test_the_webhook_needs_no_session_and_no_csrf_token(): void
    {
        $order = $this->order();
        Http::fake(['*/payments/pay-1' => Http::response($this->payment('succeeded', $order->number))]);

        $this->app['auth']->forgetGuards();
        $this->defaultCookies = [];

        $this->post(route('api.payments.yookassa.webhook'), ['object' => ['id' => 'pay-1']])->assertNoContent();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_a_cancelled_order_is_not_resurrected_by_a_late_payment(): void
    {
        $order = $this->order();
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();

        Http::fake(['*/payments/pay-1' => Http::response($this->payment('succeeded', $order->number))]);

        $this->postJson(route('api.payments.yookassa.webhook'), ['object' => ['id' => 'pay-1']])->assertNoContent();

        $order->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(PaymentStatus::Succeeded, $order->payment_status);
    }
}
