<?php

namespace App\Enums;

enum ProductSort: string
{
    case Name = 'name';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case Newest = 'newest';

    /** @return array{0: string, 1: string} */
    public function toOrderBy(): array
    {
        return match ($this) {
            self::Name => ['name', 'asc'],
            self::PriceAsc => ['price_minor', 'asc'],
            self::PriceDesc => ['price_minor', 'desc'],
            self::Newest => ['created_at', 'desc'],
        };
    }
}
