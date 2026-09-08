<?php

namespace App\Http\Requests\Admin;

use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;

abstract class PaginatedRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(Config $config): array
    {
        return [
            ...$this->filterRules(),
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$config->integer('shop.max_admin_per_page')],
        ];
    }

    /** @return array<string, list<mixed>> */
    protected function filterRules(): array
    {
        return [];
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->toString() : null;
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
