<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CartItemNotFound extends ShopException
{
    public function __construct()
    {
        parent::__construct('Позиция корзины не найдена.');
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
