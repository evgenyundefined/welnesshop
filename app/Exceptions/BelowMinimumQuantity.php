<?php

namespace App\Exceptions;

use App\Models\Product;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wholesale positions are sold by the box. The refusal names the box so the
 * buyer can act on it instead of guessing which number would be accepted.
 */
class BelowMinimumQuantity extends ShopException
{
    public static function forProduct(Product $product): self
    {
        return new self(sprintf(
            'Товар «%s» продаётся от %d шт.',
            $product->name,
            $product->minOrderQuantity(),
        ));
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
