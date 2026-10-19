<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bugs become tasks of type "bug", so one Board, Backlog and Sprint holds all the
 * work. The bug fields move onto tasks, and existing bugs are copied over with
 * their comments and attachments. The old bug tables are left untouched (each
 * copy remembers its legacy_bug_id), so rolling back simply removes the copies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('type', 20)->default('task')->after('project_id')->index();
            $table->string('severity', 20)->nullable()->after('priority');
            $table->text('steps_to_reproduce')->nullable()->after('description');
            $table->text('expected_behavior')->nullable()->after('steps_to_reproduce');
            $table->text('actual_behavior')->nullable()->after('expected_behavior');
            $table->string('environment')->nullable()->after('actual_behavior');
            $table->text('resolution_notes')->nullable()->after('environment');
            $table->unsignedBigInteger('legacy_bug_id')->nullable()->unique()->after('id');
        });

        if (!Schema::hasTable('bugs')) {
            return;
        }

        $stagesByWorkspace = [];
        $stageFor = function (int $workspaceId, ?string $statusName) use (&$stagesByWorkspace) {
            $stages = $stagesByWorkspace[$workspaceId] ??= DB::table('task_stages')
                ->where('workspace_id', $workspaceId)->orderBy('order')->get(['id', 'name', 'is_completed', 'is_default']);
            if ($stages->isEmpty()) {
                return null;
            }
            $name = strtolower((string) $statusName);
            $named = fn (array $words) => $stages->first(fn ($s) => collect($words)->contains(fn ($w) => str_contains(strtolower($s->name), $w)));
            $first = $stages->firstWhere('is_default', 1) ?? $stages->first();

            return match (true) {
                str_contains($name, 'resolv') || str_contains($name, 'clos') || str_contains($name, 'done') || str_contains($name, 'fixed')
                    => ($stages->firstWhere('is_completed', 1) ?? $stages->last())->id,
                str_contains($name, 'test') || str_contains($name, 'review') || str_contains($name, 'qa') || str_contains($name, 'verif')
                    => ($named(['review', 'test', 'qa']) ?? $named(['progress']) ?? $first)->id,
                str_contains($name, 'progress') || str_contains($name, 'doing') || str_contains($name, 'open')
                    => ($named(['progress']) ?? $first)->id,
                default => $first->id,
            };
        };

        DB::table('bugs')
            ->leftJoin('bug_statuses', 'bug_statuses.id', '=', 'bugs.bug_status_id')
            ->join('projects', 'projects.id', '=', 'bugs.project_id')
            ->leftJoin('workspaces', 'workspaces.id', '=', 'projects.workspace_id')
            ->orderBy('bugs.id')
            ->select('bugs.*', 'bug_statuses.name as status_name', 'projects.workspace_id', 'projects.created_by as project_creator', 'workspaces.owner_id')
            ->chunk(200, function ($bugs) use ($stageFor) {
                foreach ($bugs as $bug) {
                    if (DB::table('tasks')->where('legacy_bug_id', $bug->id)->exists()) {
                        continue;
                    }
                    $stageId = $stageFor((int) $bug->workspace_id, $bug->status_name);
                    if (!$stageId) {
                        continue;
                    }
                    $done = (bool) DB::table('task_stages')->where('id', $stageId)->value('is_completed');
                    $taskId = DB::table('tasks')->insertGetId([
                        'legacy_bug_id' => $bug->id,
                        'project_id' => $bug->project_id,
                        'type' => 'bug',
                        'task_stage_id' => $stageId,
                        'milestone_id' => $bug->milestone_id,
                        'title' => $bug->title,
                        'description' => $bug->description,
                        'steps_to_reproduce' => $bug->steps_to_reproduce,
                        'expected_behavior' => $bug->expected_behavior,
                        'actual_behavior' => $bug->actual_behavior,
                        'environment' => $bug->environment,
                        'resolution_notes' => $bug->resolution_notes,
                        'priority' => $bug->priority,
                        'severity' => $bug->severity,
                        'start_date' => $bug->start_date,
                        'end_date' => $bug->end_date,
                        'assigned_to' => $bug->assigned_to,
                        // The reporter, or the project's creator for bugs reported through the client portal.
                        'created_by' => $bug->reported_by ?? $bug->project_creator ?? $bug->owner_id,
                        'progress' => $done ? 100 : 0,
                        'created_at' => $bug->created_at,
                        'updated_at' => $bug->updated_at,
                    ]);

                    foreach (DB::table('bug_comments')->where('bug_id', $bug->id)->orderBy('id')->get() as $comment) {
                        DB::table('task_comments')->insert([
                            'task_id' => $taskId, 'user_id' => $comment->user_id, 'comment' => $comment->comment,
                            'mentions' => $comment->mentions, 'created_at' => $comment->created_at, 'updated_at' => $comment->updated_at,
                        ]);
                    }
                    if (Schema::hasTable('bug_attachments')) {
                        foreach (DB::table('bug_attachments')->where('bug_id', $bug->id)->orderBy('id')->get() as $file) {
                            DB::table('task_attachments')->insert([
                                'task_id' => $taskId, 'media_item_id' => $file->media_item_id, 'uploaded_by' => $file->uploaded_by,
                                'created_at' => $file->created_at, 'updated_at' => $file->updated_at,
                            ]);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        $copies = DB::table('tasks')->whereNotNull('legacy_bug_id')->pluck('id');
        DB::table('task_comments')->whereIn('task_id', $copies)->delete();
        DB::table('task_attachments')->whereIn('task_id', $copies)->delete();
        DB::table('tasks')->whereIn('id', $copies)->delete();

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['legacy_bug_id']);
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'severity', 'steps_to_reproduce', 'expected_behavior', 'actual_behavior', 'environment', 'resolution_notes', 'legacy_bug_id']);
        });
    }
};
