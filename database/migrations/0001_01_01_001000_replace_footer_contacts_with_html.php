<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The footer column was a phone and an address in two fixed fields. It becomes
 * one block written in the editor, so addresses, hours and messengers fit too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->text('contacts_body')->nullable()->after('disclaimer');
        });

        DB::table('site_settings')->orderBy('id')->each(function (object $settings): void {
            DB::table('site_settings')
                ->where('id', $settings->id)
                ->update(['contacts_body' => $this->toHtml($settings->contact_phone, $settings->contact_email)]);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['contact_email', 'contact_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('contact_email')->nullable()->after('disclaimer');
            $table->string('contact_phone', 32)->nullable()->after('disclaimer');
            $table->dropColumn('contacts_body');
        });
    }

    private function toHtml(?string $phone, ?string $email): ?string
    {
        $lines = array_filter([
            $phone === null ? null : '<p><strong>'.e($phone).'</strong></p>',
            $email === null ? null : '<p><a href="mailto:'.e($email).'">'.e($email).'</a></p>',
        ]);

        return $lines === [] ? null : implode("\n", $lines);
    }
};
