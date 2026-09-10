<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\StatisticsPeriod;
use App\Models\Customer;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    public function test_the_dashboard_reports_what_the_shop_did(): void
    {
        $this->signInAdmin();

        $product = $this->makeProduct(['name' => 'Эпиталон', 'price_minor' => 100_00, 'stock' => 100, 'views' => 12]);
        $customer = Customer::factory()->create();

        $this->makeOrder($customer, [$product->id => 2])->update(['status' => OrderStatus::Paid]);
        $this->makeOrder($customer, [$product->id => 1]);

        $this->getJson(route('admin.api.statistics'))
            ->assertOk()
            ->assertJsonPath('data.period', StatisticsPeriod::Month->value)
            ->assertJsonPath('data.since', now()->subDays(29)->toDateString())
            ->assertJsonPath('data.orders.total', 2)
            ->assertJsonPath('data.orders.paid', 1)
            ->assertJsonPath('data.orders.awaiting_payment', 1)
            ->assertJsonPath('data.orders.cancelled', 0)
            ->assertJsonPath('data.revenue.paid_minor', 200_00)
            ->assertJsonPath('data.revenue.average_minor', 200_00)
            ->assertJsonPath('data.customers.new', 1)
            ->assertJsonCount(30, 'data.daily')
            ->assertJsonPath('data.top_products.0.name', 'Эпиталон')
            ->assertJsonPath('data.top_products.0.quantity', 3)
            ->assertJsonPath('data.most_viewed.0.views', 12);
    }

    public function test_the_period_can_be_narrowed(): void
    {
        $this->signInAdmin();

        $this->getJson(route('admin.api.statistics', ['period' => StatisticsPeriod::Week->value]))
            ->assertOk()
            ->assertJsonPath('data.period', 'week')
            ->assertJsonCount(7, 'data.daily');

        $this->getJson(route('admin.api.statistics', ['period' => StatisticsPeriod::All->value]))
            ->assertOk()
            ->assertJsonPath('data.period', 'all')
            ->assertJsonPath('data.since', null);
    }

    public function test_an_unknown_period_is_refused(): void
    {
        $this->signInAdmin();

        $this->getJson(route('admin.api.statistics', ['period' => 'decade']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_an_empty_shop_still_answers(): void
    {
        $this->signInAdmin();

        $this->getJson(route('admin.api.statistics', ['period' => StatisticsPeriod::All->value]))
            ->assertOk()
            ->assertJsonPath('data.orders.total', 0)
            ->assertJsonPath('data.revenue.paid_minor', 0)
            ->assertJsonPath('data.revenue.average_minor', null)
            ->assertJsonPath('data.top_products', [])
            ->assertJsonPath('data.most_viewed', []);
    }

    public function test_the_dashboard_is_closed_to_everyone_but_an_admin(): void
    {
        $this->getJson(route('admin.api.statistics'))->assertUnauthorized();

        $this->actingAs(Customer::factory()->create(), 'web');

        $this->getJson(route('admin.api.statistics'))->assertUnauthorized();
    }

    public function test_opening_a_product_page_counts_every_time(): void
    {
        $product = $this->makeProduct();

        $this->assertSame(0, $product->views);

        foreach (range(1, 3) as $ignored) {
            $this->getJson(route('api.products.show', $product))->assertOk();
        }

        $this->assertSame(3, $product->refresh()->views, 'repeat opens are not deduplicated');
    }

    public function test_the_dashboard_carries_a_view_series_the_opens_move(): void
    {
        $product = $this->makeProduct();

        $this->getJson(route('api.products.show', $product))->assertOk();
        $this->getJson(route('api.products.show', $product))->assertOk();

        $this->signInAdmin();

        $series = $this->getJson(route('admin.api.statistics', ['period' => 'week']))
            ->assertOk()
            ->assertJsonCount(7, 'data.views_daily')
            ->json('data.views_daily');

        $this->assertSame(2, collect($series)->firstWhere('date', now()->toDateString())['views']);
    }

    public function test_a_product_hidden_from_the_catalog_collects_no_views(): void
    {
        $draft = $this->makeProduct(['status' => ProductStatus::Draft]);

        $this->getJson(route('api.products.show', $draft))->assertNotFound();

        $this->assertSame(0, $draft->refresh()->views);
        $this->assertDatabaseCount('product_view_daily', 0);
    }

    public function test_the_listing_endpoint_does_not_count_as_a_view(): void
    {
        $product = $this->makeProduct();

        $this->getJson(route('api.products'))->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame(0, $product->refresh()->views);
    }

    public function test_views_reach_the_admin_product_payload(): void
    {
        $product = $this->makeProduct();

        $this->getJson(route('api.products.show', $product))->assertOk();

        $this->signInAdmin();

        $this->getJson(route('admin.api.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.views', 1);

        $this->getJson(route('admin.api.products.index'))
            ->assertOk()
            ->assertJsonPath('data.0.views', 1);
    }

    public function test_a_product_created_in_the_admin_starts_at_no_views(): void
    {
        $this->signInAdmin();

        $this->postJson(route('admin.api.products.store'), [
            'category_id' => $this->makeProduct()->category_id,
            'name' => 'Новинка',
            'status' => ProductStatus::Published->value,
            'price_minor' => 50_000,
            'stock' => 4,
        ])
            ->assertCreated()
            ->assertJsonPath('data.views', 0);
    }

    public function test_the_storefront_payload_keeps_the_counter_to_itself(): void
    {
        $product = $this->makeProduct();

        $payload = $this->getJson(route('api.products.show', $product))->assertOk()->json('data');

        $this->assertArrayNotHasKey('views', $payload);
    }

    public function test_saving_a_product_cannot_forge_its_view_count(): void
    {
        $product = $this->makeProduct(['views' => 7]);

        $this->signInAdmin();

        $this->putJson(route('admin.api.products.update', $product), [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status->value,
            'price_minor' => $product->price_minor,
            'stock' => $product->stock,
            'views' => 9_000,
        ])->assertOk();

        $this->assertSame(7, $product->refresh()->views);
    }
}
