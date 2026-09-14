<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class CdekCitiesRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'between:2,120'],
        ];
    }

    public function search(): string
    {
        return trim($this->string('query')->toString());
    }
}
