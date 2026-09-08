<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ShopException extends Exception
{
    abstract public function status(): int;

    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], $this->status());
    }
}
