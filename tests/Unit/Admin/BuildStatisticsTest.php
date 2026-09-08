<?php

namespace Tests\Unit\Admin;

use App\Actions\Admin\Statistics\BuildStatistics;
use App\Enums\OrderStatus;
use App\Enums\StatisticsPeriod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Tests\TestCase;

class BuildStatisticsTest extends TestCase
{
    public function test_orders_are_counted_by_status(): void
    {
        $product = $this->makeProduct(['stock' => 100]);

        $this->order($product, OrderStatus::Paid);
        $this->order($product, OrderStatus::Paid);
        $this->order($product, OrderStatus::Cancelled);
        $this->order($product);

        $orders = $this->build()['orders'];

        $this->assertSame(4, $orders['total']);
        $this->assertSame(2, $orders[OrderStatus::Paid->value]);
        $this->assertSame(1, $orders[OrderStatus::Cancelled->value]);
        $this->assertSame(1, $orders[OrderStatus::AwaitingPayment->value]);
    }

    public function test_revenue_counts_paid_orders_only(): void
    {
        $product = $this->makeProduct(['price_minor' => 100_00, 'stock' => 100]);

        $this->order($product, OrderStatus::Paid, 3);
        $this->order($product, OrderStatus::Cancelled, 5);
        $this->order($product, quantity: 7);

        $revenue = $this->build()['revenue'];

        $this->assertSame(300_00, $revenue['paid_minor']);
        $this->assertSame(300_00, $revenue['average_minor']);
        $this->assertSame(config('shop.currency'), $revenue['currency']);
    }

    public function test_the_average_is_the_mean_of_the_paid_orders(): void
    {
        $product = $this->makeProduct(['price_minor' => 100_00, 'stock' => 100]);

        $this->order($product, OrderStatus::Paid, 1);
        $this->order($product, OrderStatus::Paid, 2);

        $this->assertSame(150_00, $this->build()['revenue']['average_minor']);
    }

    public function test_without_a_paid_order_there_is_nothing_to_average(): void
    {
        $this->order($this->makeProduct(['stock' => 10]));

        $revenue = $this->build()['revenue'];

        $this->assertSame(0, $revenue['paid_minor']);
        $this->assertNull($revenue['average_minor']);
    }

    public function test_a_period_leaves_out_what_happened_before_it(): void
    {
        $product = $this->makeProduct(['price_minor' => 100_00, 'stock' => 100]);

        $old = $this->order($product, OrderStatus::Paid);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->order($product, OrderStatus::Paid);

        $this->assertSame(1, $this->build(StatisticsPeriod::Month)['orders']['total']);
        $this->assertSame(100_00, $this->build(StatisticsPeriod::Month)['revenue']['paid_minor']);

        $this->assertSame(2, $this->build(StatisticsPeriod::All)['orders']['total']);
        $this->assertSame(200_00, $this->build(StatisticsPeriod::All)['revenue']['paid_minor']);
    }

    public function test_the_edge_of_the_period_is_included(): void
    {
        $product = $this->makeProduct(['stock' => 10]);

        $edge = $this->order($product);
        $edge->forceFill(['created_at' => now()->subDays(6)->startOfDay()])->save();

        $this->assertSame(1, $this->build(StatisticsPeriod::Week)['orders']['total']);

        $edge->forceFill(['created_at' => now()->subDays(7)->endOfDay()->subSecond()])->save();

        $this->assertSame(0, $this->build(StatisticsPeriod::Week)['orders']['total']);
    }

    public function test_the_daily_series_covers_every_day_of_the_period(): void
    {
        $daily = $this->build(StatisticsPeriod::Week)['daily'];

        $this->assertCount(7, $daily);
        $this->assertSame(now()->subDays(6)->toDateString(), $daily[0]['date']);
        $this->assertSame(now()->toDateString(), $daily[6]['date']);
        $this->assertSame([0], array_unique(array_column($daily, 'orders')));
    }

    public function test_the_daily_series_carries_orders_and_paid_revenue(): void
    {
        $product = $this->makeProduct(['price_minor' => 100_00, 'stock' => 100]);

        $this->order($product, OrderStatus::Paid, 2);
        $this->order($product, OrderStatus::Cancelled, 4);

        $today = collect($this->build(StatisticsPeriod::Week)['daily'])->firstWhere('date', now()->toDateString());

        $this->assertSame(2, $today['orders']);
        $this->assertSame(200_00, $today['revenue_minor'], 'a cancelled order is not revenue');
    }

    public function test_top_products_rank_by_quantity_and_skip_cancelled_orders(): void
    {
        $popular = $this->makeProduct(['name' => 'Популярный', 'price_minor' => 10_00, 'stock' => 100]);
        $quiet = $this->makeProduct(['name' => 'Тихий', 'price_minor' => 10_00, 'stock' => 100]);
        $refused = $this->makeProduct(['name' => 'Отменённый', 'price_minor' => 10_00, 'stock' => 100]);

        $this->order($popular, quantity: 5);
        $this->order($quiet, quantity: 2);
        $this->order($refused, OrderStatus::Cancelled, 50);

        $top = $this->build()['top_products'];

        $this->assertSame(['Популярный', 'Тихий'], $top->pluck('product_name')->all());
        $this->assertSame([5, 2], $top->pluck('quantity')->map(intval(...))->all());
        $this->assertSame(50_00, (int) $top->first()->revenue_minor);
    }

    public function test_top_products_add_up_the_same_product_across_orders(): void
    {
        $product = $this->makeProduct(['name' => 'Эпиталон', 'price_minor' => 10_00, 'stock' => 100]);

        $this->order($product, quantity: 2);
        $this->order($product, OrderStatus::Paid, 3);

        $top = $this->build()['top_products'];

        $this->assertCount(1, $top);
        $this->assertSame(5, (int) $top->first()->quantity);
    }

    public function test_most_viewed_ranks_by_the_lifetime_counter(): void
    {
        $watched = $this->makeProduct(['name' => 'Смотрят', 'views' => 40]);
        $glanced = $this->makeProduct(['name' => 'Заглядывают', 'views' => 5]);
        $this->makeProduct(['name' => 'Никто', 'views' => 0]);

        $viewed = $this->build()['most_viewed'];

        $this->assertSame([$watched->id, $glanced->id], $viewed->pluck('id')->all());
        $this->assertSame(40, $viewed->first()->views);
    }

    public function test_customers_are_counted_new_total_and_blocked(): void
    {
        Customer::factory()->create();
        Customer::factory()->create(['blocked_at' => now()]);
        Customer::factory()->create()->forceFill(['created_at' => now()->subDays(40)])->save();

        $customers = $this->build(StatisticsPeriod::Month)['customers'];

        $this->assertSame(2, $customers['new']);
        $this->assertSame(3, $customers['total']);
        $this->assertSame(1, $customers['blocked']);
    }

    public function test_an_empty_shop_reports_zeroes_rather_than_failing(): void
    {
        $statistics = $this->build(StatisticsPeriod::All);

        $this->assertSame(0, $statistics['orders']['total']);
        $this->assertSame(0, $statistics['revenue']['paid_minor']);
        $this->assertNull($statistics['revenue']['average_minor']);
        $this->assertCount(1, $statistics['daily'], 'with no orders the series is just today');
        $this->assertCount(0, $statistics['top_products']);
        $this->assertCount(0, $statistics['most_viewed']);
    }

    /** @return array<string, mixed> */
    private function build(StatisticsPeriod $period = StatisticsPeriod::Month): array
    {
        return ($this->app->make(BuildStatistics::class))($period);
    }

    private function order(Product $product, ?OrderStatus $status = null, int $quantity = 1): Order
    {
        $order = $this->makeOrder(Customer::factory()->create(), [$product->id => $quantity]);

        if ($status !== null) {
            $order->update(['status' => $status]);
        }

        return $order;
    }
}
