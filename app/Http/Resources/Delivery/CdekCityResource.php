<?php

namespace App\Http\Resources\Delivery;

use App\Delivery\Cdek\CdekCity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CdekCity */
class CdekCityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'full_name' => $this->resource->fullName,
        ];
    }
}
