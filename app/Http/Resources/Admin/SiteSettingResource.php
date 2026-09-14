<?php

namespace App\Http\Resources\Admin;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin SiteSetting
 */
class SiteSettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'logo_image_url' => $this->resource->logo_image_url,
            'banner_enabled' => $this->resource->banner_enabled,
            'banner_image_url' => $this->resource->banner_image_url,
            'banner_title' => $this->resource->banner_title,
            'banner_subtitle' => $this->resource->banner_subtitle,
            'banner_button_label' => $this->resource->banner_button_label,
            'banner_button_url' => $this->resource->banner_button_url,
            'promo_heading' => $this->resource->promo_heading,
            'promo_body' => $this->resource->promo_body,
            'disclaimer' => $this->resource->disclaimer,
            'contacts_body' => $this->resource->contacts_body,
            'info_body' => $this->resource->info_body,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }
}
