<?php

namespace App\Enums;

/**
 * Валюта, в которой назначена цена товара. Магазин рассчитывается в одной
 * валюте (`shop.currency`), поэтому всё остальное — корзина, заказ, платёж —
 * приводится к ней по курсу ЦБ.
 */
enum Currency: string
{
    case Rub = 'RUB';
    case Usd = 'USD';

    public function sign(): string
    {
        return match ($this) {
            self::Rub => '₽',
            self::Usd => '$',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Rub => 'Рубль',
            self::Usd => 'Доллар США',
        };
    }

    /** Как это показывает витрина: сумма, пробел, знак — русская запись. */
    public function format(int $minor): string
    {
        $amount = number_format($minor / 100, $minor % 100 === 0 ? 0 : 2, ',', ' ');

        return $amount.' '.$this->sign();
    }
}
