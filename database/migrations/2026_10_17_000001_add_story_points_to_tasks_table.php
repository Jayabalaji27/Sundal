<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story points: the size of a task, used for sprint capacity, burndown and
 * velocity on the project Backlog. Nullable: existing tasks have no estimate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tasks', 'story_points')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedSmallInteger('story_points')->nullable()->after('priority');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'story_points')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('story_points');
            });
        }
    }
};
