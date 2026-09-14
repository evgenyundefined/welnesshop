<?php

namespace Tests\Feature;

use App\Actions\Delivery\WeighCart;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CdekDeliveryTest extends TestCase
{
    private const string CHECKOUT = 'api.checkout';

    private const array CONTACTS = [
        'contact_name' => 'Иван Петров',
        'contact_email' => 'ivan@example.com',
        'contact_phone' => '+79990000000',
        'payment_method' => 'card',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cdek.account', 'account');
        config()->set('services.cdek.password', 'secret');
        config()->set('services.cdek.from_city_code', 44);
        config()->set('services.cdek.base_url', 'https://api.edu.cdek.ru/v2');
    }

    /** @param array<string, mixed> $overrides */
    private function fakeCdek(array $overrides = []): void
    {
        Http::fake([
            '*/oauth/token*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/location/cities*' => Http::response($overrides['cities'] ?? [
                ['code' => 270, 'city' => 'Новосибирск', 'region' => 'Новосибирская обл.'],
            ]),
            '*/deliverypoints*' => Http::response($overrides['points'] ?? [
                [
                    'code' => 'NSK77',
                    'name' => 'Постамат на Ленина',
                    'location' => ['address_full' => 'Новосибирск, Ленина 1'],
                    'work_time' => 'Пн-Пт 10:00-20:00',
                ],
            ]),
            '*/calculator/tarifflist*' => Http::response($overrides['tariffs'] ?? [
                'tariff_codes' => [
                    [
                        'tariff_code' => 136,
                        'tariff_name' => 'Посылка склад-склад',
                        'tariff_description' => 'Экономичная',
                        'delivery_mode' => 4,
                        'delivery_sum' => 390.5,
                        'period_min' => 2,
                        'period_max' => 3,
                    ],
                    [
                        'tariff_code' => 137,
                        'tariff_name' => 'Посылка склад-дверь',
                        'delivery_mode' => 3,
                        'delivery_sum' => 590.0,
                        'period_min' => 2,
                        'period_max' => 4,
                    ],
                ],
                'errors' => [],
            ]),
        ]);
    }

    private function signIn(): Customer
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'web');

        return $customer;
    }

    private function fillCart(int $price = 1_000_00, ?int $weight = 500): int
    {
        $product = $this->makeProduct(['stock' => 10, 'price_minor' => $price, 'weight_grams' => $weight]);

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        return $product->id;
    }

    public function test_a_city_is_looked_up_by_name(): void
    {
        $this->fakeCdek();

        $this->getJson(route('api.delivery.cdek.cities', ['query' => 'Новос']))
            ->assertOk()
            ->assertJsonPath('data.0.code', 270)
            ->assertJsonPath('data.0.full_name', 'Новосибирск, Новосибирская обл.');
    }

    public function test_pickup_points_are_listed_for_a_city(): void
    {
        $this->fakeCdek();

        $this->getJson(route('api.delivery.cdek.points', ['city_code' => 270]))
            ->assertOk()
            ->assertJsonPath('data.0.code', 'NSK77')
            ->assertJsonPath('data.0.address', 'Новосибирск, Ленина 1');
    }

    public function test_only_the_tariffs_that_end_where_the_buyer_asked_are_offered(): void
    {
        $this->fakeCdek();
        $this->signIn();
        $this->fillCart();

        $this->postJson(route('api.delivery.cdek.tariffs'), ['city_code' => 270, 'destination' => 'point'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 136)
            ->assertJsonPath('data.0.cost_minor', 390_50);

        $this->postJson(route('api.delivery.cdek.tariffs'), ['city_code' => 270, 'destination' => 'door'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 137);
    }

    public function test_the_carrier_is_asked_about_the_weight_of_the_cart_in_hand(): void
    {
        $this->fakeCdek();
        $this->signIn();
        $this->fillCart(weight: 750);

        $this->postJson(route('api.delivery.cdek.tariffs'), ['city_code' => 270, 'destination' => 'door'])->assertOk();

        Http::assertSent(static fn (Request $request): bool => ! str_contains($request->url(), 'tarifflist')
            || $request->data()['packages'][0]['weight'] === 1500);
    }

    public function test_a_product_without_a_weight_falls_back_to_the_configured_one(): void
    {
        config()->set('shop.default_product_weight_grams', 400);

        $this->signIn();
        $this->fillCart(weight: null);

        $cart = Cart::query()->with('items.product')->sole();

        $this->assertSame(800, ($this->app->make(WeighCart::class))($cart));
    }

    public function test_an_order_to_a_pickup_point_carries_the_quote_the_carrier_gave(): void
    {
        $this->fakeCdek();
        $customer = $this->signIn();
        $this->fillCart();

        $this->postJson(route(self::CHECKOUT), [
            ...self::CONTACTS,
            'delivery_method' => 'cdek',
            'cdek_city_code' => 270,
            'cdek_destination' => 'point',
            'cdek_point_code' => 'NSK77',
            'cdek_tariff_code' => 136,
        ])
            ->assertCreated()
            ->assertJsonPath('data.delivery_cost_minor', 390_50)
            ->assertJsonPath('data.total_minor', 2_000_00 + 390_50)
            ->assertJsonPath('data.cdek_point_address', 'Новосибирск, Ленина 1')
            ->assertJsonPath('data.shipping_address', 'Новосибирск, Ленина 1');

        $order = $customer->orders()->sole();

        $this->assertSame('270', $order->cdek_city_code);
        $this->assertSame('Новосибирск, Новосибирская обл.', $order->cdek_city_name);
        $this->assertSame(136, $order->cdek_tariff_code);
        $this->assertSame('Посылка склад-склад', $order->cdek_tariff_name);
        $this->assertSame([2, 3], [$order->delivery_days_min, $order->delivery_days_max]);
    }

    public function test_an_order_to_the_door_keeps_the_address_the_buyer_typed(): void
    {
        $this->fakeCdek();
        $this->signIn();
        $this->fillCart();

        $this->postJson(route(self::CHECKOUT), [
            ...self::CONTACTS,
            'delivery_method' => 'cdek',
            'shipping_address' => 'Новосибирск, Ленина 5, кв. 3',
            'cdek_city_code' => 270,
            'cdek_destination' => 'door',
            'cdek_tariff_code' => 137,
        ])
            ->assertCreated()
            ->assertJsonPath('data.shipping_address', 'Новосибирск, Ленина 5, кв. 3')
            ->assertJsonPath('data.delivery_cost_minor', 590_00)
            ->assertJsonPath('data.cdek_point_address', null);
    }

    public function test_a_tariff_the_carrier_does_not_offer_is_refused(): void
    {
        $this->fakeCdek();
        $this->signIn();
        $this->fillCart();

        $this->postJson(route(self::CHECKOUT), [
            ...self::CONTACTS,
            'delivery_method' => 'cdek',
            'shipping_address' => 'Новосибирск, Ленина 5',
            'cdek_city_code' => 270,
            'cdek_destination' => 'door',
            // Offered, but to a pickup point rather than to the door.
            'cdek_tariff_code' => 136,
        ])->assertUnprocessable();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_pickup_point_from_another_city_is_refused(): void
    {
        $this->fakeCdek();
        $this->signIn();
        $this->fillCart();

        $this->postJson(route(self::CHECKOUT), [
            ...self::CONTACTS,
            'delivery_method' => 'cdek',
            'cdek_city_code' => 270,
            'cdek_destination' => 'point',
            'cdek_point_code' => 'MSK1',
            'cdek_tariff_code' => 136,
        ])->assertUnprocessable();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_the_carrier_refusing_the_calculation_stops_the_order(): void
    {
        $this->fakeCdek(['tariffs' => [
            'tariff_codes' => [],
            'errors' => [['code' => 'v2_to_location_invalid', 'message' => 'Неверный город получателя']],
        ]]);
        $this->signIn();
        $this->fillCart();

        $this->postJson(route(self::CHECKOUT), [
            ...self::CONTACTS,
            'delivery_method' => 'cdek',
            'shipping_address' => 'Новосибирск, Ленина 5',
            'cdek_city_code' => 270,
            'cdek_destination' => 'door',
            'cdek_tariff_code' => 137,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Неверный город получателя');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_courier_order_never_reaches_the_carrier(): void
    {
        $this->fakeCdek();
        $this->signIn();
        $this->fillCart();

        $this->postJson(route(self::CHECKOUT), [
            ...self::CONTACTS,
            'delivery_method' => 'courier',
            'shipping_address' => 'Москва, Тверская 1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.delivery_cost_minor', 0)
            ->assertJsonPath('data.total_minor', 2_000_00);

        Http::assertNothingSent();
    }

    public function test_a_courier_order_tolerates_the_empty_carrier_fields_the_form_posts(): void
    {
        $this->signIn();
        $this->fillCart();

        // The checkout form submits every field it has, so a courier order
        // arrives with the CDEK ones present and empty.
        $this->postJson(route(self::CHECKOUT), [
            ...self::CONTACTS,
            'delivery_method' => 'courier',
            'shipping_address' => 'Москва, Тверская 1',
            'cdek_city_code' => null,
            'cdek_destination' => null,
            'cdek_point_code' => null,
            'cdek_tariff_code' => null,
        ])
            ->assertCreated()
            ->assertJsonPath('data.delivery_cost_minor', 0);
    }

    public function test_the_carrier_fields_are_required_only_for_a_carrier_order(): void
    {
        $this->signIn();
        $this->fillCart();

        $this->postJson(route(self::CHECKOUT), [
            ...self::CONTACTS,
            'delivery_method' => 'cdek',
            'shipping_address' => 'Новосибирск, Ленина 5',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cdek_city_code', 'cdek_destination', 'cdek_tariff_code']);
    }

    public function test_an_unconfigured_integration_says_so_instead_of_failing(): void
    {
        config()->set('services.cdek.account', '');
        $this->signIn();
        $this->fillCart();

        $this->postJson(route('api.delivery.cdek.tariffs'), ['city_code' => 270, 'destination' => 'door'])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'CDEK_ACCOUNT'));
    }

    public function test_an_expired_token_is_refreshed_once_and_the_call_retried(): void
    {
        Http::fakeSequence()
            ->push(['access_token' => 'stale', 'expires_in' => 3600])
            ->push(['message' => 'unauthorized'], 401)
            ->push(['access_token' => 'fresh', 'expires_in' => 3600])
            ->push([['code' => 270, 'city' => 'Новосибирск', 'region' => 'Новосибирская обл.']]);

        $this->getJson(route('api.delivery.cdek.cities', ['query' => 'Новос']))
            ->assertOk()
            ->assertJsonPath('data.0.code', 270);
    }
}
