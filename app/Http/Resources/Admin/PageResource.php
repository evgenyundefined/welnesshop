<?php

namespace App\Http\Resources\Admin;

use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'position' => $this->resource->position,
            'visibility' => $this->resource->visibility,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }
}
