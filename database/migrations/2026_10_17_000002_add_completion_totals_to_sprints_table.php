<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a sprint committed to and finished, frozen when it is completed.
 * Completing a sprint moves its unfinished tasks on, so without these totals
 * its report and the project's velocity would lose what was planned.
 * Nullable: sprints completed before this have no snapshot.
 */
return new class extends Migration
{
    private const COLUMNS = ['committed_tasks', 'committed_points', 'completed_tasks', 'completed_points'];

    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (!Schema::hasColumn('sprints', $column)) {
                    $table->unsignedInteger($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(self::COLUMNS, fn ($column) => Schema::hasColumn('sprints', $column))));
        });
    }
};
