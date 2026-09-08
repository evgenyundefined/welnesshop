<?php

namespace App\Actions\Admin\Statistics;

use App\Enums\OrderStatus;
use App\Enums\StatisticsPeriod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildStatistics
{
    private const int TOP_LIMIT = 10;

    public function __construct(private readonly Config $config) {}

    /** @return array<string, mixed> */
    public function __invoke(StatisticsPeriod $period): array
    {
        $now = CarbonImmutable::now();
        $since = $period->since($now);

        $byStatus = $this->ordersByStatus($since);
        $paidCount = $byStatus[OrderStatus::Paid->value];
        $revenue = $this->paidRevenue($since);

        return [
            'period' => $period,
            'since' => $since,
            'orders' => [
                'total' => array_sum($byStatus),
                ...$byStatus,
            ],
            'revenue' => [
                'currency' => $this->config->string('shop.currency'),
                'paid_minor' => $revenue,
                // An average over zero paid orders is not zero roubles, it is
                // nothing to average, so it stays null rather than dividing.
                'average_minor' => $paidCount === 0 ? null : intdiv($revenue, $paidCount),
            ],
            'customers' => [
                'new' => $this->scope(Customer::query(), $since)->count(),
                'total' => Customer::query()->count(),
                'blocked' => Customer::query()->whereNotNull('blocked_at')->count(),
            ],
            'daily' => $this->daily($since, $now),
            'top_products' => $this->topProducts($since),
            'most_viewed' => $this->mostViewed(),
        ];
    }

    /** @return array<string, int> */
    private function ordersByStatus(?CarbonImmutable $since): array
    {
        $counted = $this->scope(Order::query(), $since)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(static fn (OrderStatus $status): array => [
                $status->value => (int) $counted->get($status->value, 0),
            ])
            ->all();
    }

    private function paidRevenue(?CarbonImmutable $since): int
    {
        return (int) $this->scope(Order::query(), $since)
            ->where('status', OrderStatus::Paid)
            ->sum('total_minor');
    }

    /**
     * One row per day of the period, including the days nothing happened, so
     * the chart has no holes to interpolate over.
     *
     * @return list<array{date: string, orders: int, revenue_minor: int}>
     */
    private function daily(?CarbonImmutable $since, CarbonImmutable $now): array
    {
        $rows = $this->scope(Order::query(), $since)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(CASE WHEN status = ? THEN total_minor ELSE 0 END) as revenue_minor', [
                OrderStatus::Paid->value,
            ])
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $first = $since ?? $this->firstOrderDay($now);

        return collect(CarbonImmutable::parse($first)->toPeriod($now, '1 day'))
            ->map(function (CarbonImmutable $day) use ($rows): array {
                $row = $rows->get($day->toDateString());

                return [
                    'date' => $day->toDateString(),
                    'orders' => (int) ($row->orders ?? 0),
                    'revenue_minor' => (int) ($row->revenue_minor ?? 0),
                ];
            })
            ->all();
    }

    private function firstOrderDay(CarbonImmutable $now): CarbonImmutable
    {
        $earliest = Order::query()->min('created_at');

        return $earliest === null ? $now->startOfDay() : CarbonImmutable::parse($earliest)->startOfDay();
    }

    /**
     * @return Collection<int, OrderItem>
     */
    private function topProducts(?CarbonImmutable $since): Collection
    {
        return OrderItem::query()
            ->selectRaw('product_name, MIN(product_slug) as product_slug')
            ->selectRaw('SUM(quantity) as quantity')
            ->selectRaw('SUM(total_minor) as revenue_minor')
            ->whereHas('order', function (Builder $query) use ($since): void {
                $this->scope($query, $since)->whereNot('status', OrderStatus::Cancelled);
            })
            ->groupBy('product_name')
            ->orderByDesc('quantity')
            ->orderBy('product_name')
            ->limit(self::TOP_LIMIT)
            ->get();
    }

    /**
     * Views are a lifetime counter on the product, so this one ignores the
     * period instead of pretending to slice it.
     *
     * @return Collection<int, Product>
     */
    private function mostViewed(): Collection
    {
        return Product::query()
            ->where('views', '>', 0)
            ->orderByDesc('views')
            ->orderBy('name')
            ->limit(self::TOP_LIMIT)
            ->get(['id', 'slug', 'name', 'views']);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function scope(Builder $query, ?CarbonImmutable $since): Builder
    {
        return $query->when($since, static fn (Builder $scoped, CarbonImmutable $from) => $scoped
            ->where('created_at', '>=', $from));
    }
}
