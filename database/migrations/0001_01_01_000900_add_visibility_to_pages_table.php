<?php

use App\Enums\PageVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A page used to be either in the menus or nowhere. The third state is a page
 * that answers on its own address but is never linked to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('visibility', 16)->default(PageVisibility::Published->value)->after('position');
        });

        DB::table('pages')
            ->where('is_published', false)
            ->update(['visibility' => PageVisibility::Draft->value]);

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->boolean('is_published')->default(true)->after('position');
        });

        DB::table('pages')
            ->where('visibility', '!=', PageVisibility::Published->value)
            ->update(['is_published' => false]);

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('visibility');
        });
    }
};
