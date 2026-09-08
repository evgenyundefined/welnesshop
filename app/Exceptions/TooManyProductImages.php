<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class TooManyProductImages extends ShopException
{
    public function __construct(int $limit)
    {
        parent::__construct("К товару можно приложить не больше {$limit} фотографий.");
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
