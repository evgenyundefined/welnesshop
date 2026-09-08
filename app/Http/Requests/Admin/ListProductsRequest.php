<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Validation\Rule;

class ListProductsRequest extends PaginatedRequest
{
    /** @return array<string, list<mixed>> */
    protected function filterRules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
        ];
    }

    public function categoryId(): ?int
    {
        return $this->filled('category_id') ? $this->integer('category_id') : null;
    }

    public function status(): ?ProductStatus
    {
        return $this->enum('status', ProductStatus::class);
    }
}
