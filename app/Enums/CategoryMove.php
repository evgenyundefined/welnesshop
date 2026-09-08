<?php

namespace App\Enums;

enum CategoryMove: string
{
    case Up = 'up';
    case Down = 'down';

    public function offset(): int
    {
        return match ($this) {
            self::Up => -1,
            self::Down => 1,
        };
    }
}
