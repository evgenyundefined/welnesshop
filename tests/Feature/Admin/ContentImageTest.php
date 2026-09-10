<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_an_admin_uploads_a_picture_for_a_page_and_gets_its_address(): void
    {
        $this->signInAdmin();

        $url = $this->postJson(route('admin.api.content.images.store'), [
            'image' => UploadedFile::fake()->image('shema.png'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.url', fn (string $url): bool => str_starts_with($url, '/storage/content/'))
            ->json('data.url');

        Storage::assertExists(str_replace('/storage/', '', $url));
    }

    public function test_the_upload_is_validated(): void
    {
        $this->signInAdmin();

        $this->postJson(route('admin.api.content.images.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->postJson(route('admin.api.content.images.store'), [
            'image' => UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->postJson(route('admin.api.content.images.store'), [
            'image' => UploadedFile::fake()->create('huge.jpg', 6_000, 'image/jpeg'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_only_an_admin_can_upload_content_pictures(): void
    {
        foreach ([null, Customer::factory()->create()] as $identity) {
            $this->app['auth']->forgetGuards();
            $this->defaultCookies = [];

            if ($identity !== null) {
                $this->actingAs($identity, 'web');
            }

            $this->postJson(route('admin.api.content.images.store'), [
                'image' => UploadedFile::fake()->image('hack.jpg'),
            ])->assertUnauthorized();
        }
    }
}
