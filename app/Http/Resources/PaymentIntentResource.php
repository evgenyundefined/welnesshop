<?php

namespace App\Http\Resources;

use App\Payments\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentIntent
 */
class PaymentIntentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource->status->value,
            'provider' => $this->resource->provider,
            'message' => $this->resource->message,
            'confirmation_url' => $this->resource->confirmationUrl,
            'external_id' => $this->resource->externalId,
        ];
    }
}
