<?php

namespace App\Http\Requests\Admin;

class ListCustomersRequest extends PaginatedRequest
{
    /** @return array<string, list<mixed>> */
    protected function filterRules(): array
    {
        return [
            'blocked' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Tri-state: absent means every customer, otherwise only the blocked or
     * only the active ones.
     */
    public function blocked(): ?bool
    {
        return $this->filled('blocked') ? $this->boolean('blocked') : null;
    }
}
