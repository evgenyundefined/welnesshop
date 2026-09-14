<?php

namespace App\Http\Resources\Delivery;

use App\Delivery\Cdek\CdekTariff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CdekTariff */
class CdekTariffResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'cost_minor' => $this->resource->costMinor,
            'days_min' => $this->resource->daysMin,
            'days_max' => $this->resource->daysMax,
        ];
    }
}
