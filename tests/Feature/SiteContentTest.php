<?php

namespace Tests\Feature;

use App\Actions\Admin\Site\SaveBannerImage;
use App\Actions\Admin\Site\SaveSiteSettings;
use App\Models\Category;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    public function test_the_site_payload_carries_the_menu_the_footer_needs(): void
    {
        $page = Page::factory()->create(['title' => 'Доставка и оплата', 'position' => 1]);
        Page::factory()->draft()->create(['title' => 'Черновик', 'position' => 0]);
        $category = Category::factory()->create(['name' => 'Пептиды']);
        $product = $this->makeProduct(['category_id' => $category->id]);

        ($this->app->make(SaveSiteSettings::class))([
            'contact_phone' => 'ххххх',
            'contact_email' => 'shop@example.ru',
            'disclaimer' => 'Текст дисклеймера.',
        ]);

        $this->getJson(route('api.site'))
            ->assertOk()
            ->assertJsonPath('data.banner', null)
            ->assertJsonPath('data.disclaimer', 'Текст дисклеймера.')
            ->assertJsonPath('data.contacts.phone', 'ххххх')
            ->assertJsonPath('data.contacts.email', 'shop@example.ru')
            ->assertJsonCount(1, 'data.pages')
            ->assertJsonPath('data.pages.0.title', 'Доставка и оплата')
            ->assertJsonPath('data.pages.0.slug', $page->slug)
            ->assertJsonPath('data.categories.0.name', 'Пептиды')
            ->assertJsonPath('data.products.0.slug', $product->slug);
    }

    public function test_the_banner_appears_only_once_it_has_a_picture_and_is_switched_on(): void
    {
        Storage::fake();

        ($this->app->make(SaveSiteSettings::class))(['banner_enabled' => true]);

        $this->getJson(route('api.site'))->assertOk()->assertJsonPath('data.banner', null);

        ($this->app->make(SaveBannerImage::class))(UploadedFile::fake()->image('banner.jpg'));
        ($this->app->make(SaveSiteSettings::class))([
            'banner_enabled' => true,
            'banner_title' => 'agelesscode',
            'banner_button_url' => '/',
        ]);

        $this->getJson(route('api.site'))
            ->assertOk()
            ->assertJsonPath('data.banner.title', 'agelesscode')
            ->assertJsonPath('data.banner.button_url', '/')
            ->assertJsonPath('data.banner.image_url', fn (string $url): bool => str_contains($url, '/storage/site/'));
    }

    public function test_a_banner_switched_off_is_not_reported_even_with_a_picture(): void
    {
        Storage::fake();

        ($this->app->make(SaveBannerImage::class))(UploadedFile::fake()->image('banner.jpg'));
        ($this->app->make(SaveSiteSettings::class))(['banner_enabled' => false]);

        $this->getJson(route('api.site'))->assertOk()->assertJsonPath('data.banner', null);
    }

    public function test_the_promo_block_arrives_as_the_html_the_editor_produced(): void
    {
        $html = '<h2>Почему мы</h2><ul><li><strong>Качество</strong></li></ul>';

        ($this->app->make(SaveSiteSettings::class))(['promo_heading' => 'О компании', 'promo_body' => $html]);

        $this->getJson(route('api.site'))
            ->assertOk()
            ->assertJsonPath('data.promo.heading', 'О компании')
            ->assertJsonPath('data.promo.body_html', $html);
    }

    public function test_a_page_is_served_as_the_html_the_editor_produced(): void
    {
        $html = '<h2>Доставка</h2><p>Курьером или транспортной компанией.</p>';

        $page = Page::factory()->create(['title' => 'Доставка и оплата', 'body' => $html]);

        $this->getJson(route('api.pages.show', $page))
            ->assertOk()
            ->assertJsonPath('data.title', 'Доставка и оплата')
            ->assertJsonPath('data.slug', $page->slug)
            ->assertJsonPath('data.body_html', $html);
    }

    /**
     * Page bodies are stored and served exactly as written, by decision: the
     * editor is a visual one and anyone with admin access is trusted with
     * arbitrary markup. Anyone reading this test should know that an
     * administrator can therefore put scripts and embeds on the storefront.
     */
    public function test_a_page_body_reaches_the_storefront_untouched(): void
    {
        $html = '<table><tr><td>Ячейка</td></tr></table><iframe src="https://example.ru"></iframe>';

        $page = Page::factory()->create(['body' => $html]);

        $this->getJson(route('api.pages.show', $page))->assertOk()->assertJsonPath('data.body_html', $html);
    }

    public function test_an_unpublished_or_unknown_page_is_a_not_found(): void
    {
        $draft = Page::factory()->draft()->create();

        $this->getJson(route('api.pages.show', $draft))->assertNotFound();
        $this->getJson(route('api.pages.show', ['page' => 'net-takoy']))->assertNotFound();
    }

    public function test_the_site_payload_is_open_to_guests(): void
    {
        $this->getJson(route('api.site'))->assertOk();
    }
}
