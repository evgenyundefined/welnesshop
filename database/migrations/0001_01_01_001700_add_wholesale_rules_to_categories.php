<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wholesale terms belong to the category, not to the code: the shop renames
 * its categories and adds new ones, and a rule keyed on a Russian name would
 * quietly stop applying the first time one is edited.
 */
return new class extends Migration
{
    private const WHOLESALE = ['peptidy', 'vitaminy-i-dobavki', 'inektsionnye-preparaty', 'ingalyatory'];

    private const RETAIL = ['wellness'];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('wholesale_only')->default(false)->after('description');
            $table->unsignedInteger('min_order_quantity')->default(1)->after('wholesale_only');
        });

        $this->categories()->whereIn('slug', self::WHOLESALE)->update(['wholesale_only' => true]);

        // Everything but the gadgets is sold by the box; the shop can move a
        // category either way from the admin afterwards.
        $this->categories()->whereNotIn('slug', self::RETAIL)->update(['min_order_quantity' => 10]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['wholesale_only', 'min_order_quantity']);
        });
    }

    private function categories(): Builder
    {
        return DB::table('categories');
    }
};
