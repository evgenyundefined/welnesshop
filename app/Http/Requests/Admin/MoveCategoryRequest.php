<?php

namespace App\Http\Requests\Admin;

use App\Enums\CategoryMove;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveCategoryRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(CategoryMove::class)],
        ];
    }

    public function direction(): CategoryMove
    {
        return $this->enum('direction', CategoryMove::class);
    }
}
