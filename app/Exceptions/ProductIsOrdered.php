<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ProductIsOrdered extends ShopException
{
    public function __construct()
    {
        parent::__construct('Товар уже есть в заказах — его можно только перевести в архив.');
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
