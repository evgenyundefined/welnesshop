<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentAdminTest extends TestCase
{
    public function test_an_admin_creates_a_page_with_a_derived_slug(): void
    {
        $this->signInAdmin();

        $this->postJson(route('admin.api.pages.store'), [
            'title' => '  Доставка и оплата  ',
            'body' => '## Доставка',
            'position' => 2,
            'visibility' => 'published',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Доставка и оплата')
            ->assertJsonPath('data.slug', 'dostavka-i-oplata')
            ->assertJsonPath('data.position', 2)
            ->assertJsonPath('data.visibility', 'published');

        $this->getJson(route('api.site'))->assertOk()->assertJsonPath('data.pages.0.slug', 'dostavka-i-oplata');
    }

    public function test_a_colliding_slug_is_rejected_by_validation(): void
    {
        $this->signInAdmin();
        Page::factory()->create(['slug' => 'kontakty']);

        $this->postJson(route('admin.api.pages.store'), ['title' => 'Контакты', 'body' => 'Текст'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame(1, Page::query()->count());
    }

    public function test_an_admin_edits_and_unpublishes_a_page(): void
    {
        $this->signInAdmin();
        $page = Page::factory()->create(['slug' => 'obuchenie', 'title' => 'Обучение']);

        $this->putJson(route('admin.api.pages.update', $page), [
            'title' => 'Обучение и материалы',
            'slug' => 'obuchenie',
            'body' => 'Новый текст',
            'visibility' => 'draft',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Обучение и материалы')
            ->assertJsonPath('data.visibility', 'draft');

        $this->getJson(route('api.site'))->assertOk()->assertJsonCount(0, 'data.pages');
        $this->getJson(route('api.pages.show', ['page' => 'obuchenie']))->assertNotFound();
    }

    public function test_an_admin_creates_a_page_that_only_opens_by_its_address(): void
    {
        $this->signInAdmin();

        $this->postJson(route('admin.api.pages.store'), [
            'title' => 'Оптовым покупателям',
            'body' => '<p>Условия</p>',
            'visibility' => 'unlisted',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'optovym-pokupatelyam')
            ->assertJsonPath('data.visibility', 'unlisted');

        $this->getJson(route('api.site'))->assertOk()->assertJsonCount(0, 'data.pages');

        $this->getJson(route('api.pages.show', ['page' => 'optovym-pokupatelyam']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Оптовым покупателям');
    }

    public function test_a_hidden_page_stays_visible_to_the_admin_who_has_to_manage_it(): void
    {
        $this->signInAdmin();
        $unlisted = Page::factory()->unlisted()->create(['title' => 'Оптовым покупателям', 'position' => 1]);
        Page::factory()->draft()->create(['title' => 'Черновик', 'position' => 2]);

        $this->getJson(route('admin.api.pages.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.visibility', 'unlisted')
            ->assertJsonPath('data.1.visibility', 'draft');

        $this->getJson(route('admin.api.pages.show', $unlisted))
            ->assertOk()
            ->assertJsonPath('data.visibility', 'unlisted');
    }

    public function test_the_visibility_has_to_be_one_of_the_three_states(): void
    {
        $this->signInAdmin();

        foreach ([[], ['visibility' => ''], ['visibility' => 'hidden'], ['visibility' => true]] as $attempt) {
            $this->postJson(route('admin.api.pages.store'), [
                'title' => 'Страница',
                'body' => 'Текст',
                ...$attempt,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('visibility');
        }

        $this->assertSame(0, Page::query()->count());
    }

    public function test_an_admin_deletes_a_page(): void
    {
        $this->signInAdmin();
        $page = Page::factory()->create();

        $this->deleteJson(route('admin.api.pages.destroy', $page))->assertNoContent();

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_the_settings_are_read_and_written_through_the_endpoint(): void
    {
        $this->signInAdmin();

        $this->getJson(route('admin.api.site.show'))->assertOk()->assertJsonPath('data.banner_image_url', null);

        $this->putJson(route('admin.api.site.update'), [
            'promo_heading' => 'О компании',
            'promo_body' => '## Почему мы',
            'contact_phone' => 'ххххх',
            'contact_email' => '  SHOP@Example.RU ',
            'disclaimer' => 'Дисклеймер.',
        ])
            ->assertOk()
            ->assertJsonPath('data.promo_heading', 'О компании')
            ->assertJsonPath('data.contact_email', 'shop@example.ru')
            ->assertJsonPath('data.contact_phone', 'ххххх');

        $this->assertSame('Дисклеймер.', SiteSetting::query()->sole()->disclaimer);
    }

    public function test_a_malformed_email_is_refused(): void
    {
        $this->signInAdmin();

        $this->putJson(route('admin.api.site.update'), ['contact_email' => 'не-почта'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');
    }

    public function test_an_admin_uploads_and_then_removes_the_banner(): void
    {
        Storage::fake();
        $this->signInAdmin();

        $path = $this->postJson(route('admin.api.site.banner.store'), [
            'image' => UploadedFile::fake()->image('banner.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('data.banner_image_url', fn (string $url): bool => str_contains($url, '/storage/site/'))
            ->json('data.banner_image_url');

        $this->putJson(route('admin.api.site.update'), ['banner_enabled' => true])->assertOk();
        $this->getJson(route('api.site'))->assertOk()->assertJsonPath('data.banner.image_url', $path);

        $this->deleteJson(route('admin.api.site.banner.destroy'))
            ->assertOk()
            ->assertJsonPath('data.banner_image_url', null)
            ->assertJsonPath('data.banner_enabled', false);

        $this->getJson(route('api.site'))->assertOk()->assertJsonPath('data.banner', null);
    }

    public function test_the_banner_upload_is_validated(): void
    {
        Storage::fake();
        $this->signInAdmin();

        $this->postJson(route('admin.api.site.banner.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->postJson(route('admin.api.site.banner.store'), [
            'image' => UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertNull(SiteSetting::query()->sole()->banner_image_path);
    }

    public function test_the_content_endpoints_are_closed_to_everyone_but_an_admin(): void
    {
        $page = Page::factory()->create();

        foreach ([null, Customer::factory()->create()] as $identity) {
            $this->app['auth']->forgetGuards();
            $this->defaultCookies = [];

            if ($identity !== null) {
                $this->actingAs($identity, 'web');
            }

            $this->getJson(route('admin.api.pages.index'))->assertUnauthorized();
            $this->postJson(route('admin.api.pages.store'), ['title' => 'Взлом', 'body' => 'x'])->assertUnauthorized();
            $this->deleteJson(route('admin.api.pages.destroy', $page))->assertUnauthorized();
            $this->getJson(route('admin.api.site.show'))->assertUnauthorized();
            $this->putJson(route('admin.api.site.update'), ['promo_heading' => 'Взлом'])->assertUnauthorized();
            $this->postJson(route('admin.api.site.banner.store'), [
                'image' => UploadedFile::fake()->image('hack.jpg'),
            ])->assertUnauthorized();
            $this->deleteJson(route('admin.api.site.banner.destroy'))->assertUnauthorized();
        }

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('pages', ['title' => 'Взлом']);
        $this->assertNull(SiteSetting::query()->sole()->promo_heading);
    }
}
