<?php

namespace App\Actions\Catalog;

use App\Models\Product;

class RecordProductView
{
    /**
     * Every open counts, with no attempt to recognise a repeat visitor. The
     * increment is a single UPDATE, so simultaneous opens cannot lose a count
     * the way a read-modify-write would.
     */
    public function __invoke(Product $product): void
    {
        $product->increment('views');
    }
}
