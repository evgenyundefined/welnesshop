<?php

namespace App\Enums;

enum Guard: string
{
    case Customer = 'web';
    case Admin = 'admin';

    public function middleware(): string
    {
        return 'auth:'.$this->value;
    }
}
