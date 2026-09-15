<?php

namespace Tests\Feature;

use App\Enums\PageVisibility;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\ProductImage;
use App\Models\SiteSetting;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_the_front_page_carries_the_words_the_shop_chose(): void
    {
        SiteSetting::query()->sole()->forceFill([
            'seo_title' => 'agelesscode — пептиды с доставкой по России',
            'seo_description' => 'Каталог пептидов и велнес-устройств.',
        ])->save();

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>agelesscode — пептиды с доставкой по России</title>', false)
            ->assertSee('<meta name="description" content="Каталог пептидов и велнес-устройств.">', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('"@type":"Store"', false)
            ->assertDontSee('noindex', false);
    }

    public function test_every_page_carries_the_defaults_the_browser_falls_back_to(): void
    {
        SiteSetting::query()->sole()->forceFill(['seo_title' => 'Пептиды и велнес — agelesscode'])->save();

        $product = $this->makeProduct([]);

        // The application replaces the title on its own after it mounts, so the
        // name the shop chose has to survive a navigation away and back.
        foreach (['/', "/products/{$product->slug}", '/cart'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<meta name="site-title" content="Пептиды и велнес — agelesscode">', false)
                ->assertSee('<meta name="site-brand" content="', false);
        }
    }

    public function test_the_front_page_falls_back_to_the_promo_heading(): void
    {
        SiteSetting::query()->sole()->forceFill([
            'seo_title' => null,
            'promo_heading' => 'Пептиды и велнес-технологии',
            'promo_body' => '<p>Мы собираем каталог пептидов.</p>',
        ])->save();

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Пептиды и велнес-технологии</title>', false)
            ->assertSee('Мы собираем каталог пептидов.', false);
    }

    public function test_a_product_page_is_described_and_marked_up_for_a_shop(): void
    {
        $product = $this->makeProduct([
            'name' => 'Эпиталон',
            'summary' => 'Пептид эпифиза, исследуется в контексте старения.',
            'price_minor' => 1_200_00,
            'stock' => 3,
        ]);
        $cover = ProductImage::factory()->for($product)->primary()->create(['position' => 1]);

        $response = $this->get("/products/{$product->slug}")->assertOk();

        $response->assertSee('<title>Эпиталон — agelesscode</title>', false);
        $response->assertSee('Пептид эпифиза, исследуется в контексте старения.', false);
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('"price":"1200.00"', false);
        $response->assertSee('"availability":"https://schema.org/InStock"', false);
        $response->assertSee('og:image', false);
        $response->assertSee($cover->url, false);
    }

    public function test_a_product_out_of_stock_says_so_in_the_markup(): void
    {
        $product = $this->makeProduct(['stock' => 0]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('"availability":"https://schema.org/OutOfStock"', false);
    }

    public function test_a_draft_product_is_not_offered_to_a_search_engine(): void
    {
        $product = $this->makeProduct(['status' => ProductStatus::Draft]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false)
            ->assertDontSee('"@type":"Product"', false);
    }

    public function test_a_listed_page_is_indexable_and_an_unlisted_one_is_not(): void
    {
        $listed = Page::factory()->create(['title' => 'Доставка и оплата', 'body' => '<p>Возим по России.</p>']);
        $unlisted = Page::factory()->unlisted()->create();

        $this->get("/pages/{$listed->slug}")
            ->assertOk()
            ->assertSee('<title>Доставка и оплата — agelesscode</title>', false)
            ->assertSee('Возим по России.', false)
            ->assertDontSee('noindex', false);

        // It opens by its address, which is the point, but it is not indexed.
        $this->get("/pages/{$unlisted->slug}")
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    #[DataProvider('privatePaths')]
    public function test_nothing_behind_a_login_is_indexed(string $path): void
    {
        $this->get($path)->assertOk()->assertSee('name="robots" content="noindex', false);
    }

    /** @return array<string, array{0: string}> */
    public static function privatePaths(): array
    {
        return [
            'cart' => ['/cart'],
            'checkout' => ['/checkout'],
            'orders' => ['/orders'],
            'login' => ['/login'],
            'register' => ['/register'],
        ];
    }

    public function test_the_admin_panel_is_closed_to_search_engines(): void
    {
        $this->get('/admin')->assertOk()->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_the_sitemap_lists_what_a_stranger_may_open(): void
    {
        $category = Category::factory()->create(['slug' => 'peptidy']);
        $published = $this->makeProduct(['slug' => 'epitalon']);
        $draft = $this->makeProduct(['slug' => 'chernovik', 'status' => ProductStatus::Draft]);
        $page = Page::factory()->create(['slug' => 'dostavka']);
        $unlisted = Page::factory()->unlisted()->create(['slug' => 'optovikam']);
        $pageDraft = Page::factory()->draft()->create(['slug' => 'ne-gotovo']);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $body = $response->getContent();

        $this->assertStringContainsString('<loc>'.url('/').'/</loc>', $body);
        $this->assertStringContainsString('?category='.$category->slug, $body);
        $this->assertStringContainsString("/products/{$published->slug}</loc>", $body);
        $this->assertStringContainsString("/pages/{$page->slug}</loc>", $body);

        $this->assertStringNotContainsString($draft->slug, $body);
        $this->assertStringNotContainsString($unlisted->slug, $body);
        $this->assertStringNotContainsString($pageDraft->slug, $body);
    }

    public function test_the_sitemap_is_valid_xml_with_a_url_for_every_entry(): void
    {
        $this->makeProduct();
        Page::factory()->create();

        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

        $this->assertNotFalse($xml);
        $this->assertSame('urlset', $xml->getName());
        $this->assertGreaterThanOrEqual(3, $xml->count());

        foreach ($xml->url as $url) {
            $this->assertNotEmpty((string) $url->loc);
            $this->assertStringStartsWith('http', (string) $url->loc);
        }
    }

    public function test_robots_points_at_the_sitemap_and_closes_the_private_paths(): void
    {
        $body = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);

        foreach (['/admin', '/cart', '/checkout', '/orders', '/api/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}", $body);
        }
    }

    public function test_the_description_is_plain_text_however_the_page_was_written(): void
    {
        $page = Page::factory()->create([
            'body' => "<h2>Заголовок</h2>\n<p>Текст  со   <strong>щедрыми</strong> пробелами.</p>",
        ]);

        $this->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertSee('content="Заголовок Текст со щедрыми пробелами."', false);
    }

    public function test_a_visibility_change_moves_a_page_in_and_out_of_the_sitemap(): void
    {
        $page = Page::factory()->create(['slug' => 'dostavka']);

        $this->assertStringContainsString('dostavka', $this->get('/sitemap.xml')->getContent());

        $page->forceFill(['visibility' => PageVisibility::Unlisted])->save();

        $this->assertStringNotContainsString('dostavka', $this->get('/sitemap.xml')->getContent());
    }
}
