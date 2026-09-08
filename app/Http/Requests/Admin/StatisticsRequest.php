<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatisticsPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatisticsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::enum(StatisticsPeriod::class)],
        ];
    }

    public function period(): StatisticsPeriod
    {
        return $this->enum('period', StatisticsPeriod::class) ?? StatisticsPeriod::Month;
    }
}
