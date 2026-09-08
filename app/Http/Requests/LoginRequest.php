<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function email(): string
    {
        return Str::lower(trim($this->string('email')->toString()));
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }

    public function remember(): bool
    {
        return $this->boolean('remember');
    }
}
