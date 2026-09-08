<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
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
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'max:255',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.regex' => 'Пароль должен содержать заглавную букву, строчную букву и цифру.',
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
