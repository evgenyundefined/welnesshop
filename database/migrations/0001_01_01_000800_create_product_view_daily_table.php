<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products.views stays the lifetime counter shown in listings; this table adds
 * the history it cannot hold, one row per product per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_view_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('viewed_on');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['product_id', 'viewed_on']);
            $table->index('viewed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_view_daily');
    }
};
