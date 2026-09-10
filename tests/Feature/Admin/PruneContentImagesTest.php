<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\Content\FindUnreferencedContentImages;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneContentImagesTest extends TestCase
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

    public function test_the_sweep_reports_only_the_pictures_no_text_refers_to(): void
    {
        $used = $this->picture('used.jpg');
        $inSettings = $this->picture('in-settings.jpg');
        $this->picture('forgotten.jpg');
        Storage::put('site/banner.jpg', 'binary');

        Page::factory()->create(['body' => "<img src=\"{$used}\">"]);
        SiteSetting::query()->sole()->forceFill(['promo_body' => "<img src=\"{$inSettings}\">"])->save();

        $this->assertSame(
            ['content/forgotten.jpg'],
            ($this->app->make(FindUnreferencedContentImages::class))()->all(),
        );
    }

    public function test_the_command_lists_the_files_without_deleting_them(): void
    {
        $this->picture('forgotten.jpg');

        $this->artisan('content:prune')
            ->expectsOutputToContain('content/forgotten.jpg')
            ->expectsOutputToContain('Ничего не удалено')
            ->assertSuccessful();

        Storage::assertExists('content/forgotten.jpg');
    }

    public function test_the_command_deletes_the_files_when_forced(): void
    {
        $used = $this->picture('used.jpg');
        $this->picture('forgotten.jpg');
        Page::factory()->create(['body' => "<img src=\"{$used}\">"]);

        $this->artisan('content:prune', ['--force' => true])
            ->expectsOutputToContain('Удалено файлов: 1.')
            ->assertSuccessful();

        Storage::assertMissing('content/forgotten.jpg');
        Storage::assertExists('content/used.jpg');
    }

    public function test_the_command_says_so_when_there_is_nothing_to_delete(): void
    {
        $used = $this->picture('used.jpg');
        Page::factory()->create(['body' => "<img src=\"{$used}\">"]);

        $this->artisan('content:prune', ['--force' => true])
            ->expectsOutputToContain('Лишних картинок нет.')
            ->assertSuccessful();

        Storage::assertExists('content/used.jpg');
    }
}
