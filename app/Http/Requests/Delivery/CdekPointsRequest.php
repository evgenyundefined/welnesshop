<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class CdekPointsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'city_code' => ['required', 'integer', 'min:1'],
        ];
    }

    public function cityCode(): int
    {
        return $this->integer('city_code');
    }
}
