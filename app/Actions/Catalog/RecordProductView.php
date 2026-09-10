<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use App\Models\ProductViewDaily;
use Carbon\CarbonImmutable;

class RecordProductView
{
    /**
     * Every open counts, with no attempt to recognise a repeat visitor. Both
     * counters move by a single statement, so simultaneous opens cannot lose a
     * count the way a read-modify-write would.
     */
    public function __invoke(Product $product): void
    {
        $product->increment('views');

        $day = CarbonImmutable::now()->toDateString();

        // The row exists on all but the day's first view, so the update is the
        // usual path and the insert is the fallback that races only once a day.
        if ($this->increment($product, $day) === 0 && $this->insert($product, $day) === 0) {
            $this->increment($product, $day);
        }
    }

    private function increment(Product $product, string $day): int
    {
        return ProductViewDaily::query()
            ->where('product_id', $product->id)
            ->where('viewed_on', $day)
            ->increment('views');
    }

    private function insert(Product $product, string $day): int
    {
        return ProductViewDaily::query()->insertOrIgnore([
            'product_id' => $product->id,
            'viewed_on' => $day,
            'views' => 1,
        ]);
    }
}
