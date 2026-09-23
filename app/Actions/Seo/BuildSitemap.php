<?php

namespace App\Actions\Seo;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Collection;

class BuildSitemap
{
    public function __construct(private readonly UrlGenerator $url) {}

    /**
     * Only what a stranger may open and what the shop wants found: an unlisted
     * page answers on its address but is deliberately absent here, and so is
     * anything behind a login.
     *
     * @return Collection<int, array{loc: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    public function __invoke(): Collection
    {
        return collect([$this->entry($this->root(), null, 'daily', '1.0')])
            ->merge($this->categories())
            ->merge($this->pages())
            ->merge($this->products());
    }

    /** @return Collection<int, array<string, ?string>> */
    private function categories(): Collection
    {
        return Category::query()
            ->orderBy('position')
            ->get(['slug', 'updated_at'])
            ->map(fn (Category $category): array => $this->entry(
                $this->root().'?category='.$category->slug,
                $category->updated_at?->toAtomString(),
                'weekly',
                '0.6',
            ));
    }

    /** @return Collection<int, array<string, ?string>> */
    private function pages(): Collection
    {
        return Page::query()
            ->listedInMenus()
            ->orderBy('position')
            ->get(['slug', 'updated_at'])
            ->map(fn (Page $page): array => $this->entry(
                $this->url->to("/pages/{$page->slug}"),
                $page->updated_at?->toAtomString(),
                'monthly',
                '0.5',
            ));
    }

    /** @return Collection<int, array<string, ?string>> */
    private function products(): Collection
    {
        return Product::query()
            ->onSale()
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->map(fn (Product $product): array => $this->entry(
                $this->url->to("/products/{$product->slug}"),
                $product->updated_at?->toAtomString(),
                'weekly',
                '0.8',
            ));
    }

    /** The generator drops the trailing slash; the canonical tag carries it. */
    private function root(): string
    {
        return rtrim($this->url->to('/'), '/').'/';
    }

    /** @return array<string, ?string> */
    private function entry(string $loc, ?string $lastmod, string $changefreq, string $priority): array
    {
        return ['loc' => $loc, 'lastmod' => $lastmod, 'changefreq' => $changefreq, 'priority' => $priority];
    }
}
