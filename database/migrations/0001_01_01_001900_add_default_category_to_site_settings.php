<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Какая категория выбрана на главной. Ссылкой на категорию, а не slug'ом в
 * коде: категории переименовывают, и правило, привязанное к названию, тихо
 * перестало бы работать.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->foreignId('default_category_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
        });

        $peptides = DB::table('categories')->where('slug', 'peptidy')->value('id');

        if ($peptides !== null) {
            DB::table('site_settings')->update(['default_category_id' => $peptides]);
        }
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_category_id');
        });
    }
};
