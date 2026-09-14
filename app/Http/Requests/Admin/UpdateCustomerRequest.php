<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalisesPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCustomerRequest extends FormRequest
{
    use NormalisesPhone;

    protected function prepareForValidation(): void
    {
        $this->normalisePhone('phone');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'between:2,255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($this->route('customer')),
            ],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'password' => ['nullable', 'string', 'max:255', Password::defaults()],
        ];
    }

    /** @return array{name: string, email: string, phone: ?string, password?: string} */
    public function customer(): array
    {
        $attributes = [
            'name' => trim($this->string('name')->toString()),
            'email' => Str::lower(trim($this->string('email')->toString())),
            'phone' => $this->filled('phone') ? trim($this->string('phone')->toString()) : null,
        ];

        if ($this->filled('password')) {
            $attributes['password'] = $this->string('password')->toString();
        }

        return $attributes;
    }
}
