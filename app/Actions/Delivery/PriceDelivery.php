<?php

namespace App\Actions\Delivery;

use App\Delivery\Cdek\CdekClient;
use App\Delivery\Cdek\CdekPoint;
use App\Delivery\Cdek\CdekTariff;
use App\Delivery\Cdek\CdekUnavailable;
use App\Enums\CdekDestination;
use App\Enums\DeliveryMethod;
use App\Models\Cart;

class PriceDelivery
{
    public function __construct(
        private readonly CdekClient $cdek,
        private readonly QuoteCdekTariffs $quoteCdekTariffs,
    ) {}

    /**
     * Prices the delivery again from the carrier at the moment of checkout.
     * Whatever the browser said the tariff costs is ignored: the only number
     * that reaches the order is the one CDEK quotes here.
     *
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     *
     * @throws CdekUnavailable
     */
    public function __invoke(Cart $cart, DeliveryMethod $method, array $selection): array
    {
        if (! $method->isCarrier()) {
            return ['delivery_cost_minor' => 0];
        }

        $cityCode = (int) $selection['cdek_city_code'];
        $destination = CdekDestination::from((string) $selection['cdek_destination']);
        $point = $destination === CdekDestination::Point
            ? $this->point($cityCode, (string) ($selection['cdek_point_code'] ?? ''))
            : null;

        $tariff = $this->tariff($cart, $cityCode, $destination, $point?->code, (int) $selection['cdek_tariff_code']);

        return array_filter([
            // A parcel sent to a pickup point is addressed by that point, so
            // the order carries its address rather than an empty field.
            'shipping_address' => $point?->address,
            'delivery_cost_minor' => $tariff->costMinor,
            'cdek_city_code' => (string) $cityCode,
            'cdek_city_name' => $this->cdek->city($cityCode)?->fullName,
            'cdek_tariff_code' => $tariff->code,
            'cdek_tariff_name' => $tariff->name,
            'cdek_point_code' => $point?->code,
            'cdek_point_address' => $point?->address,
            'delivery_days_min' => $tariff->daysMin,
            'delivery_days_max' => $tariff->daysMax,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @throws CdekUnavailable */
    private function point(int $cityCode, string $code): CdekPoint
    {
        $point = $this->cdek->points($cityCode)->firstWhere('code', $code);

        if (! $point instanceof CdekPoint) {
            throw CdekUnavailable::because('Выбранный пункт выдачи больше не доступен в этом городе.');
        }

        return $point;
    }

    /** @throws CdekUnavailable */
    private function tariff(Cart $cart, int $cityCode, CdekDestination $destination, ?string $pointCode, int $code): CdekTariff
    {
        $tariff = ($this->quoteCdekTariffs)($cart, $cityCode, $destination, $pointCode)->firstWhere('code', $code);

        if (! $tariff instanceof CdekTariff) {
            throw CdekUnavailable::because('Выбранный тариф больше не доступен, выберите другой способ доставки.');
        }

        return $tariff;
    }
}
