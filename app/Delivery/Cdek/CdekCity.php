<?php

namespace App\Delivery\Cdek;

final readonly class CdekCity
{
    public function __construct(
        public int $code,
        public string $name,
        public ?string $region,
        public ?string $fullName,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromResponse(array $payload): self
    {
        $city = (string) ($payload['city'] ?? '');
        $region = isset($payload['region']) ? (string) $payload['region'] : null;

        return new self(
            code: (int) ($payload['code'] ?? 0),
            name: $city,
            region: $region,
            fullName: $region === null || $region === $city ? $city : "{$city}, {$region}",
        );
    }
}
