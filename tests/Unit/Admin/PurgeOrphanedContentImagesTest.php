<?php

namespace Tests\Unit\Admin;

use App\Actions\Admin\Content\PurgeOrphanedContentImages;
use App\Actions\Admin\Pages\DeletePage;
use App\Actions\Admin\Pages\SavePage;
use App\Actions\Admin\Site\SaveSiteSettings;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeOrphanedContentImagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    private function picture(string $name): string
    {
        Storage::put("content/{$name}", 'binary');

        return Storage::url("content/{$name}");
    }

    public function test_deleting_a_page_removes_the_pictures_only_it_used(): void
    {
        $lonely = $this->picture('lonely.jpg');
        $shared = $this->picture('shared.jpg');

        $page = Page::factory()->create(['body' => "<img src=\"{$lonely}\"><img src=\"{$shared}\">"]);
        Page::factory()->create(['body' => "<p>Тоже про это</p><img src=\"{$shared}\">"]);

        ($this->app->make(DeletePage::class))($page);

        Storage::assertMissing('content/lonely.jpg');
        Storage::assertExists('content/shared.jpg');
    }

    public function test_a_picture_kept_by_the_site_settings_survives_the_page_that_introduced_it(): void
    {
        $url = $this->picture('promo.jpg');

        SiteSetting::query()->sole()->forceFill(['promo_body' => "<img src=\"{$url}\">"])->save();
        $page = Page::factory()->create(['body' => "<img src=\"{$url}\">"]);

        ($this->app->make(DeletePage::class))($page);

        Storage::assertExists('content/promo.jpg');
    }

    public function test_editing_a_page_removes_the_pictures_dropped_from_its_text(): void
    {
        $removed = $this->picture('removed.jpg');
        $kept = $this->picture('kept.jpg');

        $page = Page::factory()->create(['body' => "<img src=\"{$removed}\"><img src=\"{$kept}\">"]);

        ($this->app->make(SavePage::class))(['body' => "<img src=\"{$kept}\">"], $page);

        Storage::assertMissing('content/removed.jpg');
        Storage::assertExists('content/kept.jpg');
    }

    public function test_a_picture_moved_between_pages_in_one_edit_survives(): void
    {
        $url = $this->picture('moved.jpg');

        $source = Page::factory()->create(['body' => "<img src=\"{$url}\">"]);
        Page::factory()->create(['body' => "<img src=\"{$url}\">"]);

        ($this->app->make(SavePage::class))(['body' => '<p>Без картинки</p>'], $source);

        Storage::assertExists('content/moved.jpg');
    }

    public function test_creating_a_page_keeps_the_pictures_uploaded_for_it(): void
    {
        $url = $this->picture('fresh.jpg');

        ($this->app->make(SavePage::class))([
            'slug' => 'novaya',
            'title' => 'Новая',
            'body' => "<img src=\"{$url}\">",
            'position' => 1,
            'is_published' => true,
        ]);

        Storage::assertExists('content/fresh.jpg');
    }

    public function test_an_upload_that_no_text_mentions_yet_is_left_alone(): void
    {
        $this->picture('waiting.jpg');

        ($this->app->make(DeletePage::class))(Page::factory()->create(['body' => '<p>Пусто</p>']));

        Storage::assertExists('content/waiting.jpg');
    }

    public function test_editing_the_site_settings_removes_the_pictures_dropped_from_them(): void
    {
        $removed = $this->picture('old-promo.jpg');
        $kept = $this->picture('page-picture.jpg');

        SiteSetting::query()->sole()->forceFill([
            'promo_body' => "<img src=\"{$removed}\">",
            'disclaimer' => "<img src=\"{$kept}\">",
        ])->save();

        ($this->app->make(SaveSiteSettings::class))([
            'promo_body' => '<p>Новый текст</p>',
            'disclaimer' => "<img src=\"{$kept}\">",
        ]);

        Storage::assertMissing('content/old-promo.jpg');
        Storage::assertExists('content/page-picture.jpg');
    }

    public function test_a_crafted_address_cannot_reach_outside_the_content_directory(): void
    {
        Storage::put('site/banner.jpg', 'binary');
        Storage::put('content/normal.jpg', 'binary');

        $page = Page::factory()->create([
            'body' => '<img src="'.Storage::url('content/../site/banner.jpg').'">'
                .'<img src="'.Storage::url('content/normal.jpg').'">',
        ]);

        ($this->app->make(DeletePage::class))($page);

        Storage::assertExists('site/banner.jpg');
        Storage::assertMissing('content/normal.jpg');
    }

    public function test_the_same_picture_listed_twice_is_deleted_once(): void
    {
        $url = $this->picture('twice.jpg');

        ($this->app->make(PurgeOrphanedContentImages::class))("<img src=\"{$url}\"><img src=\"{$url}\">");

        Storage::assertMissing('content/twice.jpg');
    }

    public function test_a_picture_from_another_directory_is_never_touched(): void
    {
        Storage::put('products/cover.jpg', 'binary');

        ($this->app->make(PurgeOrphanedContentImages::class))(
            '<img src="'.Storage::url('products/cover.jpg').'">',
        );

        Storage::assertExists('products/cover.jpg');
    }
}
