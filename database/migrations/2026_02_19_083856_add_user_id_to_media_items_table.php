<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('media_items')) {
            Schema::table('media_items', function (Blueprint $table) {
                if (!Schema::hasColumn('media_items', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('workspace_id');
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                    $table->index('user_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('media_items') && Schema::hasColumn('media_items', 'user_id')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
