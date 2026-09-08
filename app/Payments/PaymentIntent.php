<?php

namespace App\Payments;

use App\Enums\PaymentStatus;

final readonly class PaymentIntent
{
    public function __construct(
        public PaymentStatus $status,
        public string $provider,
        public string $message,
        public ?string $confirmationUrl = null,
        public ?string $externalId = null,
    ) {}
}
