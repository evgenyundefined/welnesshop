<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Page;
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

    public function test_a_deleted_page_takes_its_pictures_with_it(): void
    {
        $this->signInAdmin();

        $lonely = $this->uploadPicture('lonely.png');
        $shared = $this->uploadPicture('shared.png');

        $page = $this->createPage('Доставка', "<img src=\"{$lonely}\"><img src=\"{$shared}\">");
        $this->createPage('Обучение', "<p>Схема</p><img src=\"{$shared}\">");

        $this->deleteJson(route('admin.api.pages.destroy', $page))->assertNoContent();

        Storage::assertMissing($this->pathOf($lonely));
        Storage::assertExists($this->pathOf($shared));
    }

    public function test_a_picture_dropped_from_a_page_is_deleted_when_the_page_is_saved(): void
    {
        $this->signInAdmin();

        $removed = $this->uploadPicture('removed.png');
        $kept = $this->uploadPicture('kept.png');
        $page = $this->createPage('Обучение', "<img src=\"{$removed}\"><img src=\"{$kept}\">");

        $this->patchJson(route('admin.api.pages.update', $page), [
            'title' => 'Обучение',
            'slug' => 'obuchenie',
            'body' => "<img src=\"{$kept}\">",
            'position' => 1,
            'visibility' => 'published',
        ])->assertOk();

        Storage::assertMissing($this->pathOf($removed));
        Storage::assertExists($this->pathOf($kept));
    }

    public function test_a_picture_still_shown_in_the_site_settings_outlives_the_page(): void
    {
        $this->signInAdmin();

        $url = $this->uploadPicture('promo.png');

        $this->putJson(route('admin.api.site.update'), ['promo_body' => "<img src=\"{$url}\">"])->assertOk();
        $page = $this->createPage('Контакты', "<img src=\"{$url}\">");

        $this->deleteJson(route('admin.api.pages.destroy', $page))->assertNoContent();

        Storage::assertExists($this->pathOf($url));
        $this->getJson(route('api.site'))->assertOk()->assertJsonPath(
            'data.promo.body_html',
            fn (string $html): bool => str_contains($html, $url),
        );
    }

    public function test_a_picture_dropped_from_the_site_settings_is_deleted(): void
    {
        $this->signInAdmin();

        $url = $this->uploadPicture('banner-note.png');

        $this->putJson(route('admin.api.site.update'), ['promo_body' => "<img src=\"{$url}\">"])->assertOk();
        $this->putJson(route('admin.api.site.update'), ['promo_body' => '<p>Без картинки</p>'])->assertOk();

        Storage::assertMissing($this->pathOf($url));
    }

    private function uploadPicture(string $name): string
    {
        return $this->postJson(route('admin.api.content.images.store'), [
            'image' => UploadedFile::fake()->image($name),
        ])->assertCreated()->json('data.url');
    }

    private function createPage(string $title, string $body): Page
    {
        $id = $this->postJson(route('admin.api.pages.store'), [
            'title' => $title,
            'body' => $body,
            'position' => 1,
            'visibility' => 'published',
        ])->assertCreated()->json('data.id');

        return Page::query()->findOrFail($id);
    }

    private function pathOf(string $url): string
    {
        return str_replace('/storage/', '', $url);
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
