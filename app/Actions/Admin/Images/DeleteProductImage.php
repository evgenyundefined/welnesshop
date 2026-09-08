<?php

namespace App\Actions\Admin\Images;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class DeleteProductImage
{
    public function __construct(private readonly Filesystem $disk) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(Product $product, ProductImage $image): void
    {
        DB::transaction(function () use ($product, $image): void {
            $wasCover = $image->is_primary;

            $image->delete();

            // Removing the cover promotes whatever now leads the gallery, so the
            // catalog never falls back to a placeholder while photos remain.
            if ($wasCover) {
                $product->images()->first()?->update(['is_primary' => true]);
            }
        });

        $this->disk->delete($image->path);
    }
}
