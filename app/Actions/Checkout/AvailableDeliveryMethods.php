<?php

namespace App\Actions\Checkout;

use App\Delivery\Cdek\CdekClient;
use App\Enums\DeliveryMethod;

/**
 * One list, used both to build the checkout form and to validate what comes
 * back from it, so the form can never offer what the server would refuse.
 */
class AvailableDeliveryMethods
{
    public function __construct(private readonly CdekClient $cdek) {}

    /** @return list<DeliveryMethod> */
    public function __invoke(): array
    {
        return array_values(array_filter(
            DeliveryMethod::cases(),
            fn (DeliveryMethod $method): bool => ! $method->isCarrier() || $this->cdek->isEnabled(),
        ));
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(static fn (DeliveryMethod $method): string => $method->value, $this());
    }
}
