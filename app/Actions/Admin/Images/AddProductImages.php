<?php

namespace App\Actions\Admin\Images;

use App\Exceptions\TooManyProductImages;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AddProductImages
{
    public function __construct(
        private readonly Config $config,
        private readonly Filesystem $disk,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return Collection<int, ProductImage>
     *
     * @throws TooManyProductImages
     * @throws \Throwable
     */
    public function __invoke(Product $product, array $files): Collection
    {
        $limit = $this->config->integer('shop.images.max_per_product');

        if ($product->images()->count() + count($files) > $limit) {
            throw new TooManyProductImages($limit);
        }

        return DB::transaction(function () use ($product, $files): Collection {
            $position = (int) $product->images()->max('position');
            $hasCover = $product->images()->where('is_primary', true)->exists();

            $images = new Collection;

            foreach ($files as $file) {
                $images->push($product->images()->create([
                    'path' => $this->disk->putFile('products/'.$product->id, $file),
                    'position' => ++$position,
                    // A product is never left without a cover: the very first
                    // upload becomes one on its own.
                    'is_primary' => ! $hasCover && $images->isEmpty(),
                ]));
            }

            return $images;
        });
    }
}
