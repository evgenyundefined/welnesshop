<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Payments\PaymentGateway;
use App\Payments\PendingPaymentGateway;
use App\Payments\YooKassaGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Both integrations stay configured and stay switched off: the shop asked for
 * a pause, not for a teardown, so every path that could still reach them has
 * to be closed — not just the one the form offers.
 */
class IntegrationSwitchTest extends TestCase
{
    private function configureBoth(): void
    {
        config()->set('services.yookassa.shop_id', '123456');
        config()->set('services.yookassa.secret_key', 'test_secret');
        config()->set('services.cdek.account', 'account');
        config()->set('services.cdek.password', 'secret');
        config()->set('services.cdek.from_city_code', 44);
    }

    private function cart(): Product
    {
        $product = Product::factory()
            ->for(Category::factory()->create(['min_order_quantity' => 1]))
            ->create(['stock' => 100, 'price_minor' => 1_000_00]);

        $this->actingAs(Customer::factory()->create());
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        return $product;
    }

    public function test_online_payment_is_off_even_with_working_keys(): void
    {
        $this->configureBoth();

        $this->assertInstanceOf(PendingPaymentGateway::class, $this->app->make(PaymentGateway::class));

        $this->getJson('/api/site')
            ->assertOk()
            ->assertJsonPath('data.online_payment', false);
    }

    public function test_the_switch_brings_the_gateway_back(): void
    {
        $this->configureBoth();
        config()->set('shop.integrations.online_payment', true);

        $this->assertInstanceOf(YooKassaGateway::class, $this->app->make(PaymentGateway::class));
    }

    public function test_the_checkout_refuses_an_online_payment_method(): void
    {
        $this->configureBoth();
        $this->cart();

        // Hidden in the form is not enough: the field is posted by hand here,
        // the way anybody with the browser console would.
        foreach (['card', 'sbp'] as $method) {
            $this->postJson('/api/checkout', [
                'contact_name' => 'Иван Петров',
                'contact_email' => 'ivan@example.com',
                'contact_phone' => '+7 912 345 67 89',
                'delivery_method' => 'courier',
                'shipping_address' => 'Москва, Тверская 1',
                'payment_method' => $method,
            ])->assertStatus(422)->assertJsonValidationErrors('payment_method');
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_storefront_is_offered_only_offline_payment(): void
    {
        $this->configureBoth();

        $offered = collect($this->getJson('/api/site')->assertOk()->json('data.payment_methods'))
            ->pluck('value')
            ->all();

        $this->assertSame(['invoice', 'on_agreement'], $offered);
    }

    public function test_cdek_is_not_offered_as_a_delivery_method(): void
    {
        $this->configureBoth();

        $offered = collect($this->getJson('/api/site')->assertOk()->json('data.delivery_methods'))
            ->pluck('value')
            ->all();

        $this->assertSame(['courier'], $offered);
    }

    public function test_the_checkout_refuses_cdek_delivery(): void
    {
        $this->configureBoth();
        $this->cart();

        $this->postJson('/api/checkout', [
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+7 912 345 67 89',
            'delivery_method' => 'cdek',
            'cdek_city_code' => 44,
            'cdek_destination' => 'point',
            'cdek_tariff_code' => 136,
            'cdek_point_code' => 'MSK1',
            'shipping_address' => '',
            'payment_method' => 'on_agreement',
        ])->assertStatus(422)->assertJsonValidationErrors('delivery_method');

        $this->assertDatabaseCount('orders', 0);
    }

    /** The lookup endpoints are a way to the carrier of their own. */
    #[DataProvider('cdekEndpoints')]
    public function test_the_cdek_endpoints_are_closed(string $method, string $path, array $payload): void
    {
        $this->configureBoth();
        $this->actingAs(Customer::factory()->create());

        $this->json($method, $path, $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Доставка СДЭК временно недоступна.');
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function cdekEndpoints(): array
    {
        return [
            'cities' => ['get', '/api/delivery/cdek/cities?query=Москва', []],
            'points' => ['get', '/api/delivery/cdek/points?city_code=44', []],
            'tariffs' => ['post', '/api/delivery/cdek/tariffs', ['city_code' => 44, 'destination' => 'point']],
        ];
    }

    public function test_the_admin_sees_configured_but_switched_off(): void
    {
        $this->signInAdmin();
        $this->configureBoth();

        $this->getJson(route('admin.api.site.show'))
            ->assertOk()
            ->assertJsonPath('integrations.cdek.enabled', false)
            ->assertJsonPath('integrations.cdek.configured', true)
            ->assertJsonPath('integrations.payments.enabled', false)
            ->assertJsonPath('integrations.payments.configured', true);
    }
}
