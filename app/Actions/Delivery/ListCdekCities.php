<?php

namespace App\Actions\Delivery;

use App\Delivery\Cdek\CdekCity;
use App\Delivery\Cdek\CdekClient;
use Illuminate\Support\Collection;

class ListCdekCities
{
    public function __construct(private readonly CdekClient $cdek) {}

    /** @return Collection<int, CdekCity> */
    public function __invoke(string $query): Collection
    {
        return $this->cdek->cities($query);
    }
}
