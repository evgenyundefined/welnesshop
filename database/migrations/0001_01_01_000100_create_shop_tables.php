<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0)->index();
            $this->timestamps($table);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 190)->unique();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->text('maturity')->nullable();
            $table->string('supplier')->nullable();
            $table->text('source_url')->nullable();
            $table->string('status', 32)->default(ProductStatus::Published->value);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('stock')->default(0);
            $this->timestamps($table);

            $table->index(['category_id', 'status']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->uuid('token')->nullable()->unique();
            $this->timestamps($table);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $this->timestamps($table);

            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default(OrderStatus::AwaitingPayment->value);
            $table->string('payment_status', 32)->default(PaymentStatus::Pending->value);
            $table->string('payment_method', 32);
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_minor');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 32);
            $table->text('shipping_address');
            $table->text('comment')->nullable();
            $table->timestamp('paid_at')->nullable();
            $this->timestamps($table);

            $table->index(['customer_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_slug', 190);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('total_minor');
            $this->timestamps($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }

    private function timestamps(Blueprint $table): void
    {
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
    }
};
