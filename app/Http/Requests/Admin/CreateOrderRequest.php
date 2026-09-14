<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(Config $config): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.$config->integer('shop.max_item_quantity')],
            'contact_name' => ['required', 'string', 'between:2,255'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'between:5,32'],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'shipping_address' => ['required', 'string', 'between:5,1000'],
            'delivery_cost_minor' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /** @return array<int, int> product id => quantity */
    public function quantities(): array
    {
        return collect($this->array('lines'))
            ->mapWithKeys(static fn (array $line): array => [(int) $line['product_id'] => (int) $line['quantity']])
            ->all();
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return [
            'contact_name' => trim($this->string('contact_name')->toString()),
            'contact_email' => Str::lower(trim($this->string('contact_email')->toString())),
            'contact_phone' => trim($this->string('contact_phone')->toString()),
            'delivery_method' => $this->enum('delivery_method', DeliveryMethod::class),
            'shipping_address' => trim($this->string('shipping_address')->toString()),
            'delivery_cost_minor' => $this->integer('delivery_cost_minor'),
            'comment' => $this->filled('comment') ? trim($this->string('comment')->toString()) : null,
            'payment_method' => $this->enum('payment_method', PaymentMethod::class),
        ];
    }
}
