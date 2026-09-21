<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Page;
use App\Models\SiteSetting;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Сидеры запускаются при каждом старте контейнера, то есть на каждом деплое.
 * Всё, что здесь проверяется, — что деплой не трогает уже сделанную работу.
 */
class DeployPreservesContentTest extends TestCase
{
    public function test_an_edited_page_survives_a_deploy(): void
    {
        $this->seed(SiteContentSeeder::class);

        Page::query()->where('slug', 'vopros-otvet')->update([
            'title' => 'Частые вопросы',
            'body' => '<p>Наш текст.</p>',
        ]);

        $this->seed(SiteContentSeeder::class);

        $this->assertDatabaseHas('pages', [
            'slug' => 'vopros-otvet',
            'title' => 'Частые вопросы',
            'body' => '<p>Наш текст.</p>',
        ]);
    }

    public function test_a_deleted_page_stays_deleted(): void
    {
        $this->seed(SiteContentSeeder::class);

        Page::query()->where('slug', 'vopros-otvet')->delete();
        $remaining = Page::query()->count();

        $this->seed(SiteContentSeeder::class);

        $this->assertSame($remaining, Page::query()->count());
        $this->assertDatabaseMissing('pages', ['slug' => 'vopros-otvet']);
    }

    public function test_a_new_setting_still_gets_its_default(): void
    {
        $this->seed(SiteContentSeeder::class);
        $settings = SiteSetting::query()->sole();

        $settings->forceFill(['disclaimer' => null, 'promo_heading' => 'Наш заголовок'])->save();

        // Пустое поле заполняется значением по умолчанию, заполненное — нет.
        $this->seed(SiteContentSeeder::class);

        $refreshed = $settings->fresh();
        $this->assertNotNull($refreshed->disclaimer);
        $this->assertSame('Наш заголовок', $refreshed->promo_heading);
    }

    public function test_a_changed_admin_password_is_not_rolled_back(): void
    {
        config()->set('shop.admin.email', 'admin@example.com');
        config()->set('shop.admin.password', 'iz-peremennoy');

        $this->seed(AdminSeeder::class);

        // Пароль сменили в панели — например, потому что старый утёк.
        Admin::query()->where('email', 'admin@example.com')
            ->first()
            ->forceFill(['password' => 'novyy-parol', 'name' => 'Евгений'])
            ->save();

        $this->seed(AdminSeeder::class);

        $admin = Admin::query()->where('email', 'admin@example.com')->sole();

        $this->assertTrue(Hash::check('novyy-parol', $admin->password));
        $this->assertFalse(Hash::check('iz-peremennoy', $admin->password));
        $this->assertSame('Евгений', $admin->name);
    }

    public function test_a_forgotten_password_can_still_be_reset(): void
    {
        config()->set('shop.admin.email', 'admin@example.com');
        $this->seed(AdminSeeder::class);

        $this->artisan('admin:password', ['email' => 'admin@example.com', 'password' => 'zabyl-i-sbrosil'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check(
            'zabyl-i-sbrosil',
            Admin::query()->where('email', 'admin@example.com')->sole()->password,
        ));
    }
}
