<?php

namespace App\Actions\Site;

use App\Models\Product;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;

class ListFooterProducts
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return Collection<int, Product>
     */
    public function __invoke(): Collection
    {
        return Product::query()
            ->onSale()
            ->inRandomOrder()
            ->limit($this->config->integer('shop.footer_products'))
            ->get(['id', 'slug', 'name']);
    }
}
