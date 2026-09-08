<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CustomerIsBlocked extends ShopException
{
    public function __construct()
    {
        parent::__construct('Аккаунт заблокирован. Обратитесь в поддержку.');
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
