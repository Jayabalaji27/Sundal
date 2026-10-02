<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_notification_templates')) {
            Schema::table('user_notification_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('user_notification_templates', 'workspace_id')) {
                    $table->unsignedBigInteger('workspace_id')->nullable()->after('user_id');
                    $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
                    $table->index('workspace_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_notification_templates') && Schema::hasColumn('user_notification_templates', 'workspace_id')) {
            Schema::table('user_notification_templates', function (Blueprint $table) {
                $table->dropForeign(['workspace_id']);
                $table->dropIndex(['workspace_id']);
                $table->dropColumn('workspace_id');
            });
        }
    }
};
