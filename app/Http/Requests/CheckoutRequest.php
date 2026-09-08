<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'between:2,255'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'between:5,32'],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'shipping_address' => ['required', 'string', 'between:5,1000'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array{
     *     contact_name: string,
     *     contact_email: string,
     *     contact_phone: string,
     *     delivery_method: DeliveryMethod,
     *     shipping_address: string,
     *     comment: ?string,
     *     payment_method: PaymentMethod
     * }
     */
    public function details(): array
    {
        return [
            'contact_name' => trim($this->string('contact_name')->toString()),
            'contact_email' => Str::lower(trim($this->string('contact_email')->toString())),
            'contact_phone' => trim($this->string('contact_phone')->toString()),
            'delivery_method' => $this->enum('delivery_method', DeliveryMethod::class),
            'shipping_address' => trim($this->string('shipping_address')->toString()),
            'comment' => $this->filled('comment') ? trim($this->string('comment')->toString()) : null,
            'payment_method' => $this->enum('payment_method', PaymentMethod::class),
        ];
    }
}
