<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title');
            $table->text('body');
            $table->unsignedSmallInteger('position')->default(0)->index();
            $table->boolean('is_published')->default(true);
            $this->timestamps($table);
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('banner_enabled')->default(false);
            $table->string('banner_image_path')->nullable();
            $table->string('banner_title')->nullable();
            $table->string('banner_subtitle', 500)->nullable();
            $table->string('banner_button_label', 80)->nullable();
            $table->string('banner_button_url', 500)->nullable();
            $table->string('promo_heading')->nullable();
            $table->text('promo_body')->nullable();
            $table->text('disclaimer')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $this->timestamps($table);
        });

        // The settings are a single row by design, created here so every
        // environment has one without a seeder having to run first.
        DB::table('site_settings')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('pages');
    }

    private function timestamps(Blueprint $table): void
    {
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
    }
};
