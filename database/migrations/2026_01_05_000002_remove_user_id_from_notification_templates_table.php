<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_templates')) {
            Schema::table('notification_templates', function (Blueprint $table) {
                if (Schema::hasColumn('notification_templates', 'user_id')) {
                    $table->dropColumn('user_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_templates') && !Schema::hasColumn('notification_templates', 'user_id')) {
            Schema::table('notification_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->default(1)->after('type');
            });
        }
    }
};