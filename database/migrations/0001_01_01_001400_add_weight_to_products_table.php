<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A carrier prices a parcel by its weight, so every product needs one. Left
 * empty it falls back to shop.default_product_weight_grams rather than to
 * nothing, because a quote for a weightless parcel is not a quote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('weight_grams')->nullable()->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('weight_grams');
        });
    }
};
