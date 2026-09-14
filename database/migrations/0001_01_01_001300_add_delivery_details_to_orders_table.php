<?php

use App\Enums\DeliveryMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery used to be a label on the order and nothing else. It now carries a
 * price of its own and, for CDEK, the city, tariff and pickup point the price
 * was quoted for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('delivery_cost_minor')->default(0)->after('delivery_method');
            $table->string('cdek_city_code', 16)->nullable()->after('delivery_cost_minor');
            $table->string('cdek_city_name')->nullable()->after('cdek_city_code');
            $table->unsignedInteger('cdek_tariff_code')->nullable()->after('cdek_city_name');
            $table->string('cdek_tariff_name')->nullable()->after('cdek_tariff_code');
            $table->string('cdek_point_code', 32)->nullable()->after('cdek_tariff_name');
            $table->string('cdek_point_address')->nullable()->after('cdek_point_code');
            $table->unsignedSmallInteger('delivery_days_min')->nullable()->after('cdek_point_address');
            $table->unsignedSmallInteger('delivery_days_max')->nullable()->after('delivery_days_min');
        });

        DB::table('orders')
            ->where('delivery_method', 'transport_company')
            ->update(['delivery_method' => DeliveryMethod::Cdek->value]);
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('delivery_method', DeliveryMethod::Cdek->value)
            ->update(['delivery_method' => 'transport_company']);

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_cost_minor',
                'cdek_city_code',
                'cdek_city_name',
                'cdek_tariff_code',
                'cdek_tariff_name',
                'cdek_point_code',
                'cdek_point_address',
                'delivery_days_min',
                'delivery_days_max',
            ]);
        });
    }
};
