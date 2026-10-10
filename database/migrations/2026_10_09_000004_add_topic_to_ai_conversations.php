<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The topic button chosen in the AI Assistant's message box ("tasks",
 * "bugs"…). Kept per conversation so it stays selected until removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('topic', 20)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('topic');
        });
    }
};
