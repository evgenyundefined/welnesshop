<?php

namespace App\Actions\Delivery;

use App\Delivery\Cdek\CdekClient;
use App\Delivery\Cdek\CdekPoint;
use Illuminate\Support\Collection;

class ListCdekPoints
{
    public function __construct(private readonly CdekClient $cdek) {}

    /** @return Collection<int, CdekPoint> */
    public function __invoke(int $cityCode): Collection
    {
        return $this->cdek->points($cityCode);
    }
}
