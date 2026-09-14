<?php

namespace App\Delivery\Cdek;

final readonly class CdekPoint
{
    public function __construct(
        public string $code,
        public string $name,
        public string $address,
        public ?string $workTime,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromResponse(array $payload): self
    {
        /** @var array<string, mixed> $location */
        $location = $payload['location'] ?? [];

        return new self(
            code: (string) ($payload['code'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            address: (string) ($location['address_full'] ?? $location['address'] ?? ''),
            workTime: isset($payload['work_time']) ? (string) $payload['work_time'] : null,
        );
    }
}
