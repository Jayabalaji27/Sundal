<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contracts')) {

            Schema::table('tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('tasks', 'is_googlecalendar_sync')) {
                    $table->boolean('is_googlecalendar_sync')->default(false)->after('progress');
                }
                if (!Schema::hasColumn('tasks', 'google_calendar_event_id')) {
                    $table->string('google_calendar_event_id')->nullable()->after('is_googlecalendar_sync');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['is_googlecalendar_sync', 'google_calendar_event_id']);
        });
    }
};