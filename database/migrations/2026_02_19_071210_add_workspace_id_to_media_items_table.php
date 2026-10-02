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
                if (!Schema::hasColumn('media_items', 'workspace_id')) {
                    $table->unsignedBigInteger('workspace_id')->nullable()->after('description');
                    $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
                    $table->index('workspace_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('media_items') && Schema::hasColumn('media_items', 'workspace_id')) {
            Schema::table('media_items', function (Blueprint $table) {
                $table->dropForeign(['workspace_id']);
                $table->dropColumn('workspace_id');
            });
        }
    }
};
