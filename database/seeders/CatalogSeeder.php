<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * The source list carries no prices, so each product gets a stable
     * placeholder derived from its slug within its category's range.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const array PRICE_RANGES_MINOR = [
        'wellness' => [500_000, 25_000_000],
        'peptidy' => [300_000, 6_000_000],
        'vitaminy-i-dobavki' => [150_000, 900_000],
        'beauty' => [250_000, 1_500_000],
        'inektsionnye-preparaty' => [600_000, 9_000_000],
        'ingalyatory' => [800_000, 2_500_000],
    ];

    private const int PRICE_STEP_MINOR = 100_00;

    private const int MIN_STOCK = 5;

    private const int STOCK_SPREAD = 56;

    public function __construct(private readonly Config $config) {}

    public function run(): void
    {
        $catalog = $this->catalog();

        $categories = collect($catalog['categories'])->mapWithKeys(
            fn (array $data): array => [$data['slug'] => Category::query()->updateOrCreate(['slug' => $data['slug']], $data)],
        );

        $usedSlugs = [];

        foreach ($catalog['products'] as $data) {
            $slug = $this->uniqueSlug($data['name'], $usedSlugs);
            $usedSlugs[] = $slug;

            Product::query()->updateOrCreate(['slug' => $slug], [
                'category_id' => $categories[$data['category']]->id,
                'name' => $data['name'],
                'summary' => $data['summary'],
                'maturity' => $data['maturity'],
                'supplier' => $data['supplier'],
                'source_url' => $data['source_url'],
                'status' => ProductStatus::Published,
                'price_minor' => $this->priceMinor($data['category'], $slug),
                'currency' => $this->config->string('shop.currency'),
                'stock' => $this->stock($slug),
            ]);
        }
    }

    /**
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>}
     */
    protected function catalog(): array
    {
        return require __DIR__.'/Catalog/catalog.php';
    }

    /**
     * @param  list<string>  $usedSlugs
     */
    private function uniqueSlug(string $name, array $usedSlugs): string
    {
        $base = Str::slug($name, '-', 'ru');
        $base = $base !== '' ? $base : 'product';

        $slug = $base;
        $suffix = 2;

        while (in_array($slug, $usedSlugs, true)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function priceMinor(string $categorySlug, string $slug): int
    {
        [$min, $max] = self::PRICE_RANGES_MINOR[$categorySlug];
        $steps = intdiv($max - $min, self::PRICE_STEP_MINOR) + 1;

        return $min + crc32($slug) % $steps * self::PRICE_STEP_MINOR;
    }

    private function stock(string $slug): int
    {
        return self::MIN_STOCK + crc32('stock-'.$slug) % self::STOCK_SPREAD;
    }
}
