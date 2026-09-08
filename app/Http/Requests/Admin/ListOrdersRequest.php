<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends PaginatedRequest
{
    /** @return array<string, list<mixed>> */
    protected function filterRules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ];
    }

    public function status(): ?OrderStatus
    {
        return $this->enum('status', OrderStatus::class);
    }
}
