<?php

namespace App\Enums;

/**
 * The catalog says how much is left in words, not in numbers: an exact count
 * tells a competitor how well a position sells, and tells a buyer nothing he
 * needs. The admin keeps the real number.
 */
enum StockLevel: string
{
    case OutOfStock = 'out_of_stock';
    case Last = 'last';
    case Few = 'few';
    case Enough = 'enough';
    case Many = 'many';

    public static function fromQuantity(int $stock): self
    {
        return match (true) {
            $stock <= 0 => self::OutOfStock,
            $stock === 1 => self::Last,
            $stock <= 10 => self::Few,
            $stock <= 20 => self::Enough,
            default => self::Many,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OutOfStock => 'Нет в наличии',
            self::Last => 'Последний',
            self::Few => 'Мало',
            self::Enough => 'Достаточно',
            self::Many => 'Много',
        };
    }
}
