<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks which task stage means "done". Without it, tasks in the Done column were
 * still flagged overdue and kept 0% progress. Backfill: per workspace, stages named
 * Done/Completed/Complete are completed stages; a workspace with none of those gets
 * its last stage (highest order) marked instead. Tasks already sitting in a completed
 * stage are set to 100% progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('task_stages', 'is_completed')) {
            Schema::table('task_stages', function (Blueprint $table) {
                $table->boolean('is_completed')->default(false)->after('is_default');
            });
        }

        DB::table('task_stages')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), ['done', 'completed', 'complete'])
            ->update(['is_completed' => true]);

        $workspacesWithoutCompleted = DB::table('task_stages')
            ->select('workspace_id')
            ->groupBy('workspace_id')
            ->havingRaw('MAX(is_completed) = 0')
            ->pluck('workspace_id');

        foreach ($workspacesWithoutCompleted as $workspaceId) {
            $lastStageId = DB::table('task_stages')
                ->where('workspace_id', $workspaceId)
                ->orderByDesc('order')
                ->orderByDesc('id')
                ->value('id');

            if ($lastStageId) {
                DB::table('task_stages')->where('id', $lastStageId)->update(['is_completed' => true]);
            }
        }

        DB::table('tasks')
            ->whereIn('task_stage_id', DB::table('task_stages')->where('is_completed', true)->select('id'))
            ->where('progress', '<', 100)
            ->update(['progress' => 100]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('task_stages', 'is_completed')) {
            Schema::table('task_stages', function (Blueprint $table) {
                $table->dropColumn('is_completed');
            });
        }
    }
};
