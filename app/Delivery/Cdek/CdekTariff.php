<?php

namespace App\Delivery\Cdek;

use App\Enums\CdekDestination;

final readonly class CdekTariff
{
    public function __construct(
        public int $code,
        public string $name,
        public ?string $description,
        public int $mode,
        public int $costMinor,
        public ?int $daysMin,
        public ?int $daysMax,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromResponse(array $payload): self
    {
        return new self(
            code: (int) ($payload['tariff_code'] ?? 0),
            name: (string) ($payload['tariff_name'] ?? ''),
            description: isset($payload['tariff_description']) ? (string) $payload['tariff_description'] : null,
            mode: (int) ($payload['delivery_mode'] ?? 0),
            // The carrier quotes in roubles with a fraction; the shop keeps
            // money in minor units and never in floats.
            costMinor: (int) round(((float) ($payload['delivery_sum'] ?? 0)) * 100),
            daysMin: isset($payload['period_min']) ? (int) $payload['period_min'] : null,
            daysMax: isset($payload['period_max']) ? (int) $payload['period_max'] : null,
        );
    }

    public function goesTo(CdekDestination $destination): bool
    {
        return $destination->matchesMode($this->mode);
    }
}
