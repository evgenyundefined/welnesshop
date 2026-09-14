<?php

namespace App\Http\Requests\Concerns;

use App\Support\PhoneNumber;

trait NormalisesPhone
{
    /**
     * Runs before validation so the rule sees one shape whatever was typed:
     * 8 999 …, +7(999)…, or eleven bare digits all become +7 999 … .
     */
    protected function normalisePhone(string $field): void
    {
        if (! $this->has($field)) {
            return;
        }

        $this->merge([$field => PhoneNumber::format($this->input($field))]);
    }
}
