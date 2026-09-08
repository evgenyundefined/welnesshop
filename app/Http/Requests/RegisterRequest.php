<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'between:2,255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
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

    /** @return array{name: string, email: string, phone: ?string, password: string} */
    public function customer(): array
    {
        return [
            'name' => trim($this->string('name')->toString()),
            'email' => Str::lower(trim($this->string('email')->toString())),
            'phone' => $this->filled('phone') ? trim($this->string('phone')->toString()) : null,
            'password' => $this->string('password')->toString(),
        ];
    }
}
