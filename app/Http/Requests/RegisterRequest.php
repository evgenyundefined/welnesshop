<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalisesPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use NormalisesPhone;

    protected function prepareForValidation(): void
    {
        $this->normalisePhone('phone');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'between:2,255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::PATTERN],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
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
