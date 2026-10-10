<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Assistant sidebar: a chat can be starred (Favorites) or put away
 * (Archive). Archived chats leave the main list but are kept, and come back
 * when the user writes in them again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('topic');
            $table->timestamp('archived_at')->nullable()->after('is_favorite');
            $table->index(['workspace_id', 'user_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'user_id', 'archived_at']);
            $table->dropColumn(['is_favorite', 'archived_at']);
        });
    }
};
