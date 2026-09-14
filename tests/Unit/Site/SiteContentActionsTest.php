<?php

namespace Tests\Unit\Site;

use App\Actions\Admin\Pages\DeletePage;
use App\Actions\Admin\Pages\SavePage;
use App\Actions\Admin\Site\RemoveBannerImage;
use App\Actions\Admin\Site\RemoveLogoImage;
use App\Actions\Admin\Site\SaveBannerImage;
use App\Actions\Admin\Site\SaveLogoImage;
use App\Actions\Admin\Site\SaveSiteSettings;
use App\Actions\Site\ListFooterProducts;
use App\Actions\Site\ListPages;
use App\Actions\Site\LoadSiteSettings;
use App\Enums\PageVisibility;
use App\Enums\ProductStatus;
use App\Models\Page;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentActionsTest extends TestCase
{
    public function test_the_settings_are_a_single_row_that_always_exists(): void
    {
        $settings = ($this->app->make(LoadSiteSettings::class))();

        $this->assertInstanceOf(SiteSetting::class, $settings);
        $this->assertSame(1, SiteSetting::query()->count());
    }

    public function test_saving_settings_updates_that_same_row(): void
    {
        $save = $this->app->make(SaveSiteSettings::class);

        $save(['contacts_body' => '<p>Телефон</p>', 'banner_enabled' => true]);
        $save(['contacts_body' => '<p><strong>+7 495 000 00 00</strong></p>']);

        $this->assertSame(1, SiteSetting::query()->count());
        $this->assertSame('<p><strong>+7 495 000 00 00</strong></p>', SiteSetting::query()->sole()->contacts_body);
    }

    public function test_saving_a_page_creates_then_updates_the_same_row(): void
    {
        $save = $this->app->make(SavePage::class);

        $created = $save(['slug' => 'dostavka', 'title' => 'Доставка', 'body' => 'Текст', 'position' => 1, 'visibility' => PageVisibility::Published]);
        $updated = $save(['slug' => 'dostavka', 'title' => 'Доставка и оплата', 'body' => 'Другой текст', 'position' => 2, 'visibility' => PageVisibility::Draft], $created);

        $this->assertSame($created->id, $updated->id);
        $this->assertSame(1, Page::query()->count());
        $this->assertDatabaseHas('pages', ['id' => $created->id, 'title' => 'Доставка и оплата', 'visibility' => 'draft']);
    }

    public function test_deleting_a_page_removes_it(): void
    {
        $page = Page::factory()->create();

        ($this->app->make(DeletePage::class))($page);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_the_public_page_list_skips_drafts_and_orders_by_position(): void
    {
        $second = Page::factory()->create(['title' => 'Вторая', 'position' => 2]);
        $first = Page::factory()->create(['title' => 'Первая', 'position' => 1]);
        Page::factory()->draft()->create(['title' => 'Черновик', 'position' => 0]);

        $listed = ($this->app->make(ListPages::class))();

        $this->assertSame([$first->id, $second->id], $listed->pluck('id')->all());
    }

    public function test_the_footer_shows_a_capped_number_of_published_products(): void
    {
        config(['shop.footer_products' => 3]);

        $published = collect(range(1, 4))->map(fn (): Product => $this->makeProduct())->pluck('id');
        $draft = $this->makeProduct(['status' => ProductStatus::Draft]);

        $products = ($this->app->make(ListFooterProducts::class))();

        $this->assertCount(3, $products);
        $this->assertEmpty($products->pluck('id')->diff($published), 'only published products belong in the footer');
        $this->assertNotContains($draft->id, $products->pluck('id')->all());
    }

    public function test_uploading_a_banner_replaces_the_previous_file(): void
    {
        Storage::fake();

        $save = $this->app->make(SaveBannerImage::class);

        $first = $save(UploadedFile::fake()->image('one.jpg'))->banner_image_path;
        Storage::assertExists($first);

        $second = $save(UploadedFile::fake()->image('two.jpg'))->banner_image_path;

        $this->assertNotSame($first, $second);
        Storage::assertExists($second);
        Storage::assertMissing($first);
    }

    public function test_uploading_a_logo_replaces_the_previous_file(): void
    {
        Storage::fake();

        $save = $this->app->make(SaveLogoImage::class);

        $first = $save(UploadedFile::fake()->image('one.png'))->logo_image_path;
        Storage::assertExists($first);

        $second = $save(UploadedFile::fake()->image('two.png'))->logo_image_path;

        $this->assertNotSame($first, $second);
        Storage::assertExists($second);
        Storage::assertMissing($first);
    }

    public function test_removing_the_logo_deletes_the_file(): void
    {
        Storage::fake();

        $path = ($this->app->make(SaveLogoImage::class))(UploadedFile::fake()->image('logo.png'))->logo_image_path;

        $this->assertNull(($this->app->make(RemoveLogoImage::class))()->logo_image_path);
        Storage::assertMissing($path);
    }

    public function test_the_logo_and_the_banner_do_not_overwrite_each_other(): void
    {
        Storage::fake();

        $logo = ($this->app->make(SaveLogoImage::class))(UploadedFile::fake()->image('logo.png'))->logo_image_path;
        $banner = ($this->app->make(SaveBannerImage::class))(UploadedFile::fake()->image('banner.jpg'))->banner_image_path;

        ($this->app->make(RemoveLogoImage::class))();

        $settings = SiteSetting::query()->sole();

        $this->assertNull($settings->logo_image_path);
        $this->assertSame($banner, $settings->banner_image_path);
        Storage::assertExists($banner);
        Storage::assertMissing($logo);
    }

    public function test_removing_the_banner_deletes_the_file_and_switches_it_off(): void
    {
        Storage::fake();

        $settings = ($this->app->make(SaveBannerImage::class))(UploadedFile::fake()->image('one.jpg'));
        ($this->app->make(SaveSiteSettings::class))(['banner_enabled' => true]);

        $path = $settings->banner_image_path;

        $cleared = ($this->app->make(RemoveBannerImage::class))();

        $this->assertNull($cleared->banner_image_path);
        $this->assertFalse($cleared->banner_enabled);
        Storage::assertMissing($path);
    }
}
