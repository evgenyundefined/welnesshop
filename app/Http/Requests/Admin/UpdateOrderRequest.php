<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'contact_name' => ['required', 'string', 'between:2,255'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'between:5,32'],
            'shipping_address' => ['required', 'string', 'between:5,1000'],
            'delivery_cost_minor' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, mixed> */
    public function order(): array
    {
        return [
            'status' => $this->enum('status', OrderStatus::class),
            'payment_status' => $this->enum('payment_status', PaymentStatus::class),
            'payment_method' => $this->enum('payment_method', PaymentMethod::class),
            'delivery_method' => $this->enum('delivery_method', DeliveryMethod::class),
            'contact_name' => trim($this->string('contact_name')->toString()),
            'contact_email' => Str::lower(trim($this->string('contact_email')->toString())),
            'contact_phone' => trim($this->string('contact_phone')->toString()),
            'shipping_address' => trim($this->string('shipping_address')->toString()),
            'delivery_cost_minor' => $this->integer('delivery_cost_minor'),
            'comment' => $this->filled('comment') ? trim($this->string('comment')->toString()) : null,
        ];
    }
}
