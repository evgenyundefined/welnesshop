<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Exchange\CbrRates;
use App\Exchange\ConvertMoney;
use App\Exchange\ExchangeRateUnavailable;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    /** Ответ ЦБ как он есть: windows-1251, запятая и номинал. */
    private function cbrXml(string $usd = '92,5000'): string
    {
        $xml = <<<XML
            <?xml version="1.0" encoding="windows-1251"?>
            <ValCurs Date="17.09.2026" name="Foreign Currency Market">
                <Valute ID="R01235"><NumCode>840</NumCode><CharCode>USD</CharCode><Nominal>1</Nominal><Name>Доллар США</Name><Value>{$usd}</Value></Valute>
                <Valute ID="R01820"><NumCode>392</NumCode><CharCode>JPY</CharCode><Nominal>100</Nominal><Name>Японских иен</Name><Value>62,1000</Value></Valute>
            </ValCurs>
            XML;

        return (string) mb_convert_encoding($xml, 'Windows-1251', 'UTF-8');
    }

    private function fakeCbr(string $usd = '92,5000'): void
    {
        Http::fake(['cbr.ru/*' => Http::response($this->cbrXml($usd))]);
    }

    private function rates(): CbrRates
    {
        return $this->app->make(CbrRates::class);
    }

    private function convert(): ConvertMoney
    {
        return $this->app->make(ConvertMoney::class);
    }

    private function usdProduct(int $priceMinor = 120_00): Product
    {
        return Product::factory()
            ->for(Category::factory()->create(['min_order_quantity' => 1]))
            ->create(['price_minor' => $priceMinor, 'currency' => Currency::Usd, 'stock' => 50]);
    }

    public function test_the_rate_is_read_from_the_bank(): void
    {
        $this->fakeCbr();

        $this->assertSame(92.5, $this->rates()->rate(Currency::Usd));
    }

    public function test_a_nominal_of_a_hundred_is_a_rate_for_one(): void
    {
        $this->fakeCbr();

        // ЦБ публикует иену за сто единиц; курс одной — сотая доля.
        $this->assertSame(0.621, $this->rates()->rates()['JPY']);
    }

    public function test_the_settlement_currency_needs_no_bank(): void
    {
        Http::fake();

        $this->assertSame(1.0, $this->rates()->rate(Currency::Rub));
        Http::assertNothingSent();
    }

    public function test_the_bank_is_asked_once_a_day(): void
    {
        $this->fakeCbr();

        $this->rates()->rates();
        $this->rates()->rates();

        Http::assertSentCount(1);
    }

    public function test_yesterday_rate_carries_the_shop_through_an_outage(): void
    {
        $this->fakeCbr('90,0000');
        $this->rates()->rates();

        Cache::forget('cbr.rates.today');
        Http::fake(fn () => throw new ConnectionException('timed out'));

        // Витрина не закрывается из-за молчания ЦБ: вчерашний курс лучше,
        // чем пустой каталог, и честнее, чем выдуманное число.
        $this->assertSame(90.0, $this->rates()->rate(Currency::Usd));
    }

    public function test_without_any_known_rate_the_shop_refuses_to_guess(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->expectException(ExchangeRateUnavailable::class);

        ($this->convert())(120_00, Currency::Usd);
    }

    public function test_a_price_in_dollars_becomes_roubles(): void
    {
        $this->fakeCbr();

        $this->assertSame(11_100_00, ($this->convert())(120_00, Currency::Usd));
    }

    public function test_the_catalog_shows_the_price_in_the_currency_it_was_set_in(): void
    {
        $this->fakeCbr();
        $product = $this->usdProduct();

        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.price_minor', 120_00);

        // Пересчёт витрина делает сама, поэтому курс ей и отдаётся.
        $this->getJson('/api/site')
            ->assertOk()
            ->assertJsonPath('data.currency', 'RUB')
            ->assertJsonPath('data.rates.USD', 92.5);
    }

    public function test_the_cart_counts_in_one_currency(): void
    {
        $this->fakeCbr();
        $this->actingAs(Customer::factory()->create());

        $inDollars = $this->usdProduct(120_00);
        $inRoubles = Product::factory()
            ->for(Category::factory()->create(['min_order_quantity' => 1]))
            ->create(['price_minor' => 1_000_00, 'currency' => Currency::Rub, 'stock' => 50]);

        $this->postJson('/api/cart/items', ['product_id' => $inDollars->id, 'quantity' => 2])->assertOk();
        $response = $this->postJson('/api/cart/items', ['product_id' => $inRoubles->id, 'quantity' => 1])->assertOk();

        // 2 × 120 $ по 92,5 = 22 200 ₽, плюс тысяча рублями.
        $response
            ->assertJsonPath('data.currency', 'RUB')
            ->assertJsonPath('data.total_minor', 23_200_00);

        $dollarLine = collect($response->json('data.items'))
            ->firstWhere('original_currency', 'USD');

        $this->assertSame(120_00, $dollarLine['original_unit_price_minor']);
        $this->assertSame(11_100_00, $dollarLine['unit_price_minor']);
    }

    public function test_the_order_keeps_the_rate_it_was_priced_at(): void
    {
        $this->fakeCbr();
        $customer = Customer::factory()->create();
        $product = $this->usdProduct(120_00);

        $this->actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $number = $this->postJson('/api/checkout', [
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+7 912 345 67 89',
            'delivery_method' => 'courier',
            'shipping_address' => 'Москва, Тверская 1',
            'payment_method' => 'on_agreement',
        ])->assertCreated()->json('data.number');

        // Завтра курс другой, а сумму заказа надо будет объяснить: поэтому
        // позиция помнит и ценник в долларах, и курс, по которому считали.
        $this->assertDatabaseHas('orders', ['number' => $number, 'currency' => 'RUB', 'total_minor' => 22_200_00]);
        $this->assertDatabaseHas('order_items', [
            'original_currency' => 'USD',
            'original_unit_price_minor' => 120_00,
            'unit_price_minor' => 11_100_00,
            'exchange_rate' => 92.5,
        ]);
    }

    public function test_a_rouble_order_records_a_rate_of_one(): void
    {
        $this->fakeCbr();
        $customer = Customer::factory()->create();
        $product = Product::factory()
            ->for(Category::factory()->create(['min_order_quantity' => 1]))
            ->create(['price_minor' => 500_00, 'currency' => Currency::Rub, 'stock' => 10]);

        $order = $this->makeOrder($customer, [$product->id => 1]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'original_currency' => 'RUB',
            'unit_price_minor' => 500_00,
            'exchange_rate' => 1,
        ]);
    }

    public function test_an_administrator_sets_the_currency_on_the_product(): void
    {
        $this->signInAdmin();
        $category = Category::factory()->create();

        $this->postJson(route('admin.api.products.store'), [
            'category_id' => $category->id,
            'name' => 'Эпиталон',
            'price_minor' => 120_00,
            'currency' => 'usd',
            'stock' => 5,
            'status' => 'published',
        ])->assertCreated()->assertJsonPath('data.currency', 'USD');

        $this->assertDatabaseHas('products', ['name' => 'Эпиталон', 'currency' => 'USD']);
    }
}
