<?php

namespace App\Actions\Seo;

use App\Actions\Site\LoadSiteSettings;
use App\Enums\ProductStatus;
use App\Models\Page;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Seo\PageMeta;
use Illuminate\Config\Repository as Config;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Str;

/**
 * The storefront is rendered in the browser, so a crawler that does not run
 * scripts sees whatever the server put in the head and nothing else. This
 * turns the requested path back into a title, a description and, for a
 * product, the structured data a shop is judged on.
 */
class ResolvePageMeta
{
    public function __construct(
        private readonly LoadSiteSettings $loadSiteSettings,
        private readonly UrlGenerator $url,
        private readonly Config $config,
    ) {}

    public function __invoke(string $path): PageMeta
    {
        $settings = ($this->loadSiteSettings)();
        $path = trim($path, '/');

        return match (true) {
            $path === '' => $this->catalog($settings),
            str_starts_with($path, 'products/') => $this->product(Str::after($path, 'products/'), $settings),
            str_starts_with($path, 'pages/') => $this->page(Str::after($path, 'pages/'), $settings),
            default => $this->private($settings),
        };
    }

    private function catalog(SiteSetting $settings): PageMeta
    {
        $brand = $this->config->string('shop.brand');
        $title = $this->siteTitle($settings);

        return new PageMeta(
            title: $title,
            siteTitle: $title,
            description: $settings->seo_description ?? $this->plain($settings->promo_body),
            canonical: $this->root(),
            imageUrl: $settings->logo_image_url,
            structuredData: [
                '@context' => 'https://schema.org',
                '@type' => 'Store',
                'name' => $brand,
                'url' => $this->root(),
                ...array_filter(['image' => $settings->logo_image_url]),
            ],
        );
    }

    private function product(string $slug, SiteSetting $settings): PageMeta
    {
        $product = Product::query()
            ->with('primaryImage')
            ->where('slug', $slug)
            ->where('status', ProductStatus::Published)
            ->first();

        if ($product === null) {
            return $this->private($settings);
        }

        $image = $product->primaryImage?->url;
        $url = $this->url->to("/products/{$product->slug}");

        return new PageMeta(
            title: $this->titled($product->name),
            siteTitle: $this->siteTitle($settings),
            description: $product->summary,
            canonical: $url,
            imageUrl: $image,
            structuredData: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $product->summary,
                'image' => $image,
                'url' => $url,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => number_format($product->price_minor / 100, 2, '.', ''),
                    'priceCurrency' => $product->currency,
                    'availability' => $product->isAvailable()
                        ? 'https://schema.org/InStock'
                        : 'https://schema.org/OutOfStock',
                    'url' => $url,
                ],
            ]),
        );
    }

    private function page(string $slug, SiteSetting $settings): PageMeta
    {
        $page = Page::query()->where('slug', $slug)->first();

        // An unlisted page opens by its address but has no business in an
        // index; a draft does not open at all.
        if ($page === null || ! $page->visibility->isListedInMenus()) {
            return $this->private($settings);
        }

        return new PageMeta(
            title: $this->titled($page->title),
            siteTitle: $this->siteTitle($settings),
            description: $this->plain($page->body),
            canonical: $this->url->to("/pages/{$page->slug}"),
            imageUrl: $settings->logo_image_url,
        );
    }

    /** Carts, checkouts and the customer's own orders are nobody's search result. */
    private function private(SiteSetting $settings): PageMeta
    {
        $title = $this->siteTitle($settings);

        return new PageMeta(
            title: $title,
            siteTitle: $title,
            indexable: false,
        );
    }

    /** What the browser shows when no page of its own has a title: the catalog. */
    private function siteTitle(SiteSetting $settings): string
    {
        return $settings->seo_title ?? $settings->promo_heading ?? $this->config->string('shop.brand');
    }

    private function root(): string
    {
        return rtrim($this->url->to('/'), '/').'/';
    }

    private function titled(string $title): string
    {
        return $title.' — '.$this->config->string('shop.brand');
    }

    private function plain(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');

        return $text === '' ? null : Str::limit($text, 300);
    }
}
