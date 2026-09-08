<?php

namespace Tests\Unit\Admin;

use App\Actions\Admin\Images\AddProductImages;
use App\Actions\Admin\Images\DeleteProductImage;
use App\Actions\Admin\Images\SetPrimaryProductImage;
use App\Exceptions\TooManyProductImages;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_the_first_upload_becomes_the_cover_and_the_rest_do_not(): void
    {
        $product = $this->makeProduct();

        $images = ($this->app->make(AddProductImages::class))($product, [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ]);

        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        $this->assertSame([1, 2], $images->pluck('position')->all());
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_uploaded_files_land_on_the_disk_under_the_product(): void
    {
        $product = $this->makeProduct();

        $image = ($this->app->make(AddProductImages::class))($product, [
            UploadedFile::fake()->image('photo.jpg'),
        ])->sole();

        Storage::assertExists($image->path);
        $this->assertStringStartsWith("products/{$product->id}/", $image->path);
    }

    public function test_a_later_upload_never_steals_the_cover(): void
    {
        $product = $this->makeProduct();
        $add = $this->app->make(AddProductImages::class);

        $cover = $add($product, [UploadedFile::fake()->image('one.jpg')])->sole();
        $later = $add($product, [UploadedFile::fake()->image('two.jpg')])->sole();

        $this->assertTrue($cover->refresh()->is_primary);
        $this->assertFalse($later->refresh()->is_primary);
        $this->assertSame(2, $later->position);
    }

    public function test_the_gallery_is_capped(): void
    {
        config(['shop.images.max_per_product' => 2]);

        $product = $this->makeProduct();
        $add = $this->app->make(AddProductImages::class);

        $add($product, [UploadedFile::fake()->image('one.jpg')]);

        $this->expectException(TooManyProductImages::class);

        try {
            $add($product, [UploadedFile::fake()->image('two.jpg'), UploadedFile::fake()->image('three.jpg')]);
        } finally {
            $this->assertSame(1, $product->images()->count());
        }
    }

    public function test_choosing_a_cover_leaves_exactly_one(): void
    {
        $product = $this->makeProduct();
        $cover = ProductImage::factory()->for($product)->primary()->create(['position' => 1]);
        $other = ProductImage::factory()->for($product)->create(['position' => 2]);
        $third = ProductImage::factory()->for($product)->create(['position' => 3]);

        ($this->app->make(SetPrimaryProductImage::class))($product, $other);

        $this->assertFalse($cover->refresh()->is_primary);
        $this->assertTrue($other->refresh()->is_primary);
        $this->assertFalse($third->refresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_choosing_the_current_cover_again_changes_nothing(): void
    {
        $product = $this->makeProduct();
        $cover = ProductImage::factory()->for($product)->primary()->create();
        ProductImage::factory()->for($product)->create();

        ($this->app->make(SetPrimaryProductImage::class))($product, $cover);

        $this->assertTrue($cover->refresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_a_chosen_cover_does_not_touch_another_products_gallery(): void
    {
        $product = $this->makeProduct();
        $other = $this->makeProduct();
        $foreignCover = ProductImage::factory()->for($other)->primary()->create();
        $image = ProductImage::factory()->for($product)->create();

        ($this->app->make(SetPrimaryProductImage::class))($product, $image);

        $this->assertTrue($foreignCover->refresh()->is_primary);
    }

    public function test_deleting_the_cover_promotes_the_next_photo(): void
    {
        $product = $this->makeProduct();
        $add = $this->app->make(AddProductImages::class);

        [$cover, $second] = $add($product, [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ])->all();

        ($this->app->make(DeleteProductImage::class))($product, $cover);

        $this->assertDatabaseMissing('product_images', ['id' => $cover->id]);
        Storage::assertMissing($cover->path);
        $this->assertTrue($second->refresh()->is_primary);
    }

    public function test_deleting_a_plain_photo_leaves_the_cover_alone(): void
    {
        $product = $this->makeProduct();
        $add = $this->app->make(AddProductImages::class);

        [$cover, $second] = $add($product, [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ])->all();

        ($this->app->make(DeleteProductImage::class))($product, $second);

        $this->assertTrue($cover->refresh()->is_primary);
        Storage::assertExists($cover->path);
    }

    public function test_deleting_the_last_photo_leaves_no_cover_behind(): void
    {
        $product = $this->makeProduct();
        $image = ($this->app->make(AddProductImages::class))($product, [
            UploadedFile::fake()->image('one.jpg'),
        ])->sole();

        ($this->app->make(DeleteProductImage::class))($product, $image);

        $this->assertSame(0, $product->images()->count());
        $this->assertNull($product->fresh()->primaryImage);
    }

    public function test_deleting_a_product_takes_its_gallery_rows_with_it(): void
    {
        $product = $this->makeProduct();
        $image = ProductImage::factory()->for($product)->primary()->create();

        $product->delete();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    public function test_the_gallery_puts_the_cover_first_and_then_orders_by_position(): void
    {
        $product = $this->makeProduct();
        $third = ProductImage::factory()->for($product)->create(['position' => 3]);
        $first = ProductImage::factory()->for($product)->create(['position' => 1]);
        $cover = ProductImage::factory()->for($product)->primary()->create(['position' => 9]);

        $this->assertSame(
            [$cover->id, $first->id, $third->id],
            $product->images()->pluck('id')->all(),
        );
    }
}
