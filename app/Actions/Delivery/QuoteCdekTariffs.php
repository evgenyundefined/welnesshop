<?php

namespace App\Actions\Delivery;

use App\Delivery\Cdek\CdekClient;
use App\Delivery\Cdek\CdekTariff;
use App\Enums\CdekDestination;
use App\Models\Cart;
use Illuminate\Support\Collection;

class QuoteCdekTariffs
{
    public function __construct(
        private readonly CdekClient $cdek,
        private readonly WeighCart $weighCart,
    ) {}

    /**
     * The carrier answers with every tariff for the direction; only the ones
     * ending where the buyer asked are offered.
     *
     * @return Collection<int, CdekTariff>
     */
    public function __invoke(Cart $cart, int $cityCode, CdekDestination $destination, ?string $pointCode = null): Collection
    {
        return $this->cdek
            ->tariffs($cityCode, ($this->weighCart)($cart), $pointCode)
            ->filter(static fn (CdekTariff $tariff): bool => $tariff->goesTo($destination))
            ->sortBy(static fn (CdekTariff $tariff): int => $tariff->costMinor)
            ->values();
    }
}
