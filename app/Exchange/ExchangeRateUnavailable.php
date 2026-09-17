<?php

namespace App\Exchange;

use App\Enums\Currency;
use App\Exceptions\ShopException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Без курса цену в чужой валюте не назвать. Считать её по выдуманному курсу
 * хуже, чем отказать: ошибка уедет в заказ и в платёж.
 */
class ExchangeRateUnavailable extends ShopException
{
    public static function for(Currency $currency): self
    {
        return new self(sprintf(
            'Курс %s сейчас недоступен, оформление в этой валюте временно невозможно.',
            $currency->value,
        ));
    }

    public function status(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
