<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order has to remember which payment at the acquirer belongs to it: the
 * webhook only names the payment, and the answer has to come back to one order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_provider', 32)->nullable()->after('payment_method');
            $table->string('payment_external_id', 64)->nullable()->unique()->after('payment_provider');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_provider', 'payment_external_id']);
        });
    }
};
