<?php

namespace App\Http\Requests\Delivery;

use App\Enums\CdekDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CdekTariffsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'city_code' => ['required', 'integer', 'min:1'],
            'destination' => ['required', Rule::enum(CdekDestination::class)],
            'point_code' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function cityCode(): int
    {
        return $this->integer('city_code');
    }

    public function destination(): CdekDestination
    {
        return $this->enum('destination', CdekDestination::class);
    }

    public function pointCode(): ?string
    {
        return $this->filled('point_code') ? $this->string('point_code')->toString() : null;
    }
}
