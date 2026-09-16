<?php

namespace App\Actions\Checkout;

use App\Enums\PaymentMethod;
use Illuminate\Config\Repository as Config;

/**
 * One list, used both to build the checkout form and to validate what comes
 * back from it, so the form can never offer what the server would refuse.
 * Order is carried over from the enum: the form selects the first entry.
 */
class AvailablePaymentMethods
{
    public function __construct(private readonly Config $config) {}

    /** @return list<PaymentMethod> */
    public function __invoke(): array
    {
        $online = $this->config->boolean('shop.integrations.online_payment');

        return array_values(array_filter(
            PaymentMethod::cases(),
            static fn (PaymentMethod $method): bool => $online || ! $method->isOnline(),
        ));
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(static fn (PaymentMethod $method): string => $method->value, $this());
    }
}
