<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Заказ хранит сумму в валюте расчётов, но позиция помнит, в чём была
 * назначена цена и по какому курсу её перевели. Без этого сумму заказа потом
 * не объяснить ни покупателю, ни бухгалтеру: курс к завтрашнему дню другой.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // Значения по умолчанию нужны самой миграции: без них SQLite не
            // добавит NOT NULL-колонку к непустой таблице.
            $table->char('original_currency', 3)->default(config('shop.currency'))->after('product_slug');
            $table->unsignedBigInteger('original_unit_price_minor')->default(0)->after('original_currency');
            $table->decimal('exchange_rate', 16, 6)->default(1)->after('original_unit_price_minor');
        });

        // У всего, что заказано до появления валют, цена и была в валюте
        // расчётов — курс единица, исходная цена равна списанной.
        DB::table('order_items')->update(['original_unit_price_minor' => DB::raw('unit_price_minor')]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['original_currency', 'original_unit_price_minor', 'exchange_rate']);
        });
    }
};
