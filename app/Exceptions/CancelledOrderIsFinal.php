<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CancelledOrderIsFinal extends ShopException
{
    public function __construct()
    {
        parent::__construct('Отменённый заказ нельзя вернуть в работу — оформите новый.');
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
