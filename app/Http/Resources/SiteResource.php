<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * @property array{
 *     settings: SiteSetting,
 *     pages: Collection<int, Page>,
 *     categories: Collection<int, Category>,
 *     products: Collection<int, Product>
 * } $resource
 */
class SiteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = $this->resource['settings'];

        return [
            'banner' => $this->banner($settings),
            'promo' => $settings->promo_heading === null && $settings->promo_body === null ? null : [
                'heading' => $settings->promo_heading,
                'body_html' => $settings->promo_body === null ? null : Str::markdown($settings->promo_body, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]),
            ],
            'disclaimer' => $settings->disclaimer,
            'contacts' => [
                'email' => $settings->contact_email,
                'phone' => $settings->contact_phone,
            ],
            'pages' => PageLinkResource::collection($this->resource['pages']),
            'categories' => $this->resource['categories']->map(static fn (Category $category): array => [
                'slug' => $category->slug,
                'name' => $category->name,
            ]),
            'products' => $this->resource['products']->map(static fn (Product $product): array => [
                'slug' => $product->slug,
                'name' => $product->name,
            ]),
        ];
    }

    /**
     * A banner without a picture has nothing to show, so it is reported as
     * absent rather than as an empty frame the storefront has to guard against.
     *
     * @return ?array<string, mixed>
     */
    private function banner(SiteSetting $settings): ?array
    {
        if (! $settings->banner_enabled || $settings->banner_image_path === null) {
            return null;
        }

        return [
            'image_url' => $settings->banner_image_url,
            'title' => $settings->banner_title,
            'subtitle' => $settings->banner_subtitle,
            'button_label' => $settings->banner_button_label,
            'button_url' => $settings->banner_button_url,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }
}
