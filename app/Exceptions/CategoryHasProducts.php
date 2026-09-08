<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CategoryHasProducts extends ShopException
{
    public function __construct()
    {
        parent::__construct('Нельзя удалить категорию, в которой есть товары.');
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
