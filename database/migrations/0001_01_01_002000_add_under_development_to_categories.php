<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Раздел, который ещё наполняют. В каталоге вместо товаров показывается
 * сообщение, а сами товары никуда не выставляются: витрина не должна
 * предлагать купить то, о чём сама пишет «в разработке».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('under_development')->default(false)->after('min_order_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('under_development');
        });
    }
};
