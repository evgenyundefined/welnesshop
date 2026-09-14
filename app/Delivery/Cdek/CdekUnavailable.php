<?php

namespace App\Delivery\Cdek;

use App\Exceptions\ShopException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The carrier could not be reached or refused the request. It reads as a plain
 * refusal rather than a 500: an order priced by guesswork is worse than a
 * customer asked to pick again.
 */
class CdekUnavailable extends ShopException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function notConfigured(): self
    {
        return new self('Доставка СДЭК не настроена: не заданы CDEK_ACCOUNT, CDEK_PASSWORD или CDEK_FROM_CITY_CODE.');
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
