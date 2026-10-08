<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reply that is a provider error ("no credits left") is kept in the chat so
 * the user sees it after a reload or a queued reply, but it is never sent
 * back to the model as conversation history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->boolean('is_error')->default(false)->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('is_error');
        });
    }
};
