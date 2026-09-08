<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductGalleryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->signInAdmin();
    }

    public function test_an_admin_uploads_photos_and_gets_the_whole_gallery_back(): void
    {
        $product = $this->makeProduct();

        $response = $this->postJson(route('admin.api.products.images.store', $product), [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_primary', true)
            ->assertJsonPath('data.1.is_primary', false)
            ->assertJsonPath('data.0.url', fn (string $url): bool => str_contains($url, '/storage/products/'));

        $this->assertSame(2, $product->images()->count());
        Storage::assertExists($product->images()->pluck('path')->all());
    }

    public function test_the_gallery_can_be_read_back(): void
    {
        $product = $this->makeProduct();
        $cover = ProductImage::factory()->for($product)->primary()->create();

        $this->getJson(route('admin.api.products.images.index', $product))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cover->id)
            ->assertJsonPath('data.0.is_primary', true);
    }

    public function test_an_admin_picks_which_photo_the_catalog_shows(): void
    {
        $product = $this->makeProduct();
        $cover = ProductImage::factory()->for($product)->primary()->create(['position' => 1]);
        $wanted = ProductImage::factory()->for($product)->create(['position' => 2]);

        $this->putJson(route('admin.api.products.images.primary', [$product, $wanted]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $wanted->id)
            ->assertJsonPath('data.0.is_primary', true)
            ->assertJsonPath('data.1.id', $cover->id)
            ->assertJsonPath('data.1.is_primary', false);

        $this->getJson(route('api.products'))
            ->assertOk()
            ->assertJsonPath('data.0.cover.id', $wanted->id);
    }

    public function test_the_chosen_cover_survives_a_product_edit(): void
    {
        $product = $this->makeProduct();
        ProductImage::factory()->for($product)->primary()->create();
        $wanted = ProductImage::factory()->for($product)->create();

        $this->putJson(route('admin.api.products.images.primary', [$product, $wanted]))->assertOk();

        $this->putJson(route('admin.api.products.update', $product), [
            'category_id' => $product->category_id,
            'name' => 'Другое название',
            'slug' => $product->slug,
            'status' => $product->status->value,
            'price_minor' => $product->price_minor,
            'stock' => $product->stock,
        ])
            ->assertOk()
            ->assertJsonPath('data.cover.id', $wanted->id);
    }

    public function test_deleting_a_photo_returns_the_remaining_gallery(): void
    {
        $product = $this->makeProduct();

        $gallery = $this->postJson(route('admin.api.products.images.store', $product), [
            'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ])->assertCreated()->json('data');

        $this->deleteJson(route('admin.api.products.images.destroy', [$product, $gallery[0]['id']]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $gallery[1]['id'])
            ->assertJsonPath('data.0.is_primary', true, 'deleting the cover has to promote the next photo');

        $this->assertDatabaseMissing('product_images', ['id' => $gallery[0]['id']]);
    }

    public function test_a_photo_from_another_product_cannot_be_touched(): void
    {
        $product = $this->makeProduct();
        $other = $this->makeProduct();
        $foreign = ProductImage::factory()->for($other)->primary()->create();

        $this->putJson(route('admin.api.products.images.primary', [$product, $foreign]))->assertNotFound();
        $this->deleteJson(route('admin.api.products.images.destroy', [$product, $foreign]))->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $foreign->id, 'is_primary' => true]);
    }

    public function test_uploads_are_validated(): void
    {
        $product = $this->makeProduct();

        $this->postJson(route('admin.api.products.images.store', $product), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');

        $this->postJson(route('admin.api.products.images.store', $product), [
            'images' => [UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf')],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images.0');

        $this->postJson(route('admin.api.products.images.store', $product), [
            'images' => [UploadedFile::fake()->create('huge.jpg', 6_000, 'image/jpeg')],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images.0');

        $this->assertSame(0, $product->images()->count());
    }

    public function test_the_gallery_cap_is_enforced_through_the_endpoint(): void
    {
        config(['shop.images.max_per_product' => 2]);

        $product = $this->makeProduct();

        $this->postJson(route('admin.api.products.images.store', $product), [
            'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ])->assertCreated();

        $this->postJson(route('admin.api.products.images.store', $product), [
            'images' => [UploadedFile::fake()->image('three.jpg')],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'К товару можно приложить не больше 2 фотографий.');

        $this->assertSame(2, $product->images()->count());
    }

    public function test_an_unknown_product_is_a_not_found(): void
    {
        $this->getJson(route('admin.api.products.images.index', ['product' => 999]))->assertNotFound();
    }

    public function test_the_gallery_is_closed_to_everyone_but_an_admin(): void
    {
        $product = $this->makeProduct();
        $image = ProductImage::factory()->for($product)->primary()->create();

        foreach ([null, Customer::factory()->create()] as $identity) {
            $this->app['auth']->forgetGuards();
            $this->defaultCookies = [];

            if ($identity !== null) {
                $this->actingAs($identity, 'web');
            }

            $this->getJson(route('admin.api.products.images.index', $product))->assertUnauthorized();
            $this->postJson(route('admin.api.products.images.store', $product), [
                'images' => [UploadedFile::fake()->image('hack.jpg')],
            ])->assertUnauthorized();
            $this->putJson(route('admin.api.products.images.primary', [$product, $image]))->assertUnauthorized();
            $this->deleteJson(route('admin.api.products.images.destroy', [$product, $image]))->assertUnauthorized();
        }

        $this->assertSame(1, $product->images()->count());
    }

    public function test_the_storefront_never_exposes_a_gallery_write(): void
    {
        $product = $this->makeProduct();

        $this->postJson("/api/products/{$product->slug}/images", [
            'images' => [UploadedFile::fake()->image('hack.jpg')],
        ])->assertNotFound();

        $this->assertSame(0, $product->images()->count());
    }
}
