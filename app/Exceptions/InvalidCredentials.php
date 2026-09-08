<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class InvalidCredentials extends ShopException
{
    public function __construct()
    {
        parent::__construct('Неверный e-mail или пароль.');
    }

    public function status(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
