<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI mode locks after this many minutes without activity; the company
 * owner picks it in the AI settings (config ai_assistant.mode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_provider_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('idle_timeout_minutes')->default(30)->after('retention_days');
        });
    }

    public function down(): void
    {
        Schema::table('ai_provider_settings', function (Blueprint $table) {
            $table->dropColumn('idle_timeout_minutes');
        });
    }
};
