<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_template_langs')) {
            Schema::table('notification_template_langs', function (Blueprint $table) {
                if (!Schema::hasColumn('notification_template_langs', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->default(1)->after('content');
                    $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_template_langs') && Schema::hasColumn('notification_template_langs', 'created_by')) {
            Schema::table('notification_template_langs', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            });
        }
    }
};