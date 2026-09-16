<?php

namespace App\Http\Resources;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @property array{
 *     settings: SiteSetting,
 *     pages: Collection<int, Page>,
 *     categories: Collection<int, Category>,
 *     products: Collection<int, Product>,
 *     online_payment: bool,
 *     max_item_quantity: int,
 *     delivery_methods: list<DeliveryMethod>,
 *     payment_methods: list<PaymentMethod>
 * } $resource
 */
class SiteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = $this->resource['settings'];

        return [
            'online_payment' => $this->resource['online_payment'],
            'max_item_quantity' => $this->resource['max_item_quantity'],
            // Named here rather than in the checkout form: what the shop
            // offers is decided in one place and validated against the same
            // list when the order comes back.
            'delivery_methods' => array_map(static fn (DeliveryMethod $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ], $this->resource['delivery_methods']),
            'payment_methods' => array_map(static fn (PaymentMethod $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
            ], $this->resource['payment_methods']),
            'logo_url' => $settings->logo_image_url,
            'banner' => $this->banner($settings),
            'promo' => $settings->promo_heading === null && $settings->promo_body === null ? null : [
                'heading' => $settings->promo_heading,
                'body_html' => $settings->promo_body,
            ],
            'disclaimer' => $settings->disclaimer,
            'contacts_html' => $settings->contacts_body,
            'info_html' => $settings->info_body,
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
