<?php

use App\Enums\PageVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The footer column followed the published pages. It becomes a written block,
 * seeded here from those pages so the footer looks the same after the upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->text('info_body')->nullable()->after('contacts_body');
        });

        $links = DB::table('pages')
            ->where('visibility', PageVisibility::Published->value)
            ->orderBy('position')
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->map(fn (object $page): string => '<li><a href="/pages/'.e($page->slug).'">'.e($page->title).'</a></li>')
            ->implode("\n");

        if ($links !== '') {
            DB::table('site_settings')->update(['info_body' => "<ul>\n{$links}\n</ul>"]);
        }
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('info_body');
        });
    }
};
