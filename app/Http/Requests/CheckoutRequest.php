<?php

namespace App\Http\Requests;

use App\Enums\CdekDestination;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\NormalisesPhone;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    use NormalisesPhone;

    protected function prepareForValidation(): void
    {
        $this->normalisePhone('contact_phone');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'between:2,255'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            // A pickup point is an address of its own, so the buyer is only
            // asked to type one when the parcel is coming to them.
            'shipping_address' => [
                Rule::requiredIf(fn (): bool => ! $this->deliversToPoint()),
                'nullable',
                'string',
                'between:5,1000',
            ],
            // nullable as well as conditionally required: a courier order
            // still posts these fields, empty, and an empty value there is
            // simply nothing rather than a malformed number.
            'cdek_city_code' => [Rule::requiredIf($this->deliversByCarrier()), 'nullable', 'integer', 'min:1'],
            'cdek_destination' => [Rule::requiredIf($this->deliversByCarrier()), 'nullable', Rule::enum(CdekDestination::class)],
            'cdek_tariff_code' => [Rule::requiredIf($this->deliversByCarrier()), 'nullable', 'integer', 'min:1'],
            'cdek_point_code' => [Rule::requiredIf(fn (): bool => $this->deliversToPoint()), 'nullable', 'string', 'max:32'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    public function deliversByCarrier(): bool
    {
        return $this->enum('delivery_method', DeliveryMethod::class)?->isCarrier() === true;
    }

    public function deliversToPoint(): bool
    {
        return $this->deliversByCarrier()
            && $this->enum('cdek_destination', CdekDestination::class) === CdekDestination::Point;
    }

    /** @return array<string, mixed> */
    public function deliverySelection(): array
    {
        return [
            'cdek_city_code' => $this->integer('cdek_city_code'),
            'cdek_destination' => $this->string('cdek_destination')->toString(),
            'cdek_tariff_code' => $this->integer('cdek_tariff_code'),
            'cdek_point_code' => $this->filled('cdek_point_code') ? $this->string('cdek_point_code')->toString() : null,
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
            'contact_phone' => $this->string('contact_phone')->toString(),
            'delivery_method' => $this->enum('delivery_method', DeliveryMethod::class),
            'shipping_address' => trim($this->string('shipping_address')->toString()),
            'comment' => $this->filled('comment') ? trim($this->string('comment')->toString()) : null,
            'payment_method' => $this->enum('payment_method', PaymentMethod::class),
        ];
    }
}
