<?php

namespace App\Http\Resources\Delivery;

use App\Delivery\Cdek\CdekPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CdekPoint */
class CdekPointResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'address' => $this->resource->address,
            'work_time' => $this->resource->workTime,
        ];
    }
}
