<?php

namespace App\Payments;

use App\Exceptions\ShopException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The acquirer could not be reached or refused to open a payment. The buyer is
 * told to try again rather than shown a 500 on an order that already exists.
 */
class PaymentFailed extends ShopException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function status(): int
    {
        return Response::HTTP_BAD_GATEWAY;
    }
}
