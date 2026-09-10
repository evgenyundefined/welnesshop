<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Content moves from markdown to HTML because the admin now edits it visually.
 * Everything written before is converted once, so no page has to be retyped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pages')->orderBy('id')->each(function (object $page): void {
            DB::table('pages')->where('id', $page->id)->update(['body' => $this->toHtml($page->body)]);
        });

        DB::table('site_settings')->orderBy('id')->each(function (object $settings): void {
            if ($settings->promo_body !== null) {
                DB::table('site_settings')
                    ->where('id', $settings->id)
                    ->update(['promo_body' => $this->toHtml($settings->promo_body)]);
            }
        });
    }

    public function down(): void
    {
        // Markdown cannot be recovered from the rendered HTML.
    }

    private function toHtml(string $markdown): string
    {
        return Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
};
