<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class OrderNotPayable extends ShopException
{
    public function __construct()
    {
        parent::__construct('Заказ не ожидает оплаты.');
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
