<?php

namespace App\Exceptions;

use App\Models\Product;
use Symfony\Component\HttpFoundation\Response;

class ProductNotAvailable extends ShopException
{
    public static function forProduct(Product $product): self
    {
        return new self(sprintf('Товар «%s» недоступен в запрошенном количестве.', $product->name));
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
