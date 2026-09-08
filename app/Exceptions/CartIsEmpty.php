<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CartIsEmpty extends ShopException
{
    public function __construct()
    {
        parent::__construct('Корзина пуста.');
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
