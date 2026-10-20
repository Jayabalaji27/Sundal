<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Media Library uploads were saved without workspace_id and user_id
 * (MediaItem's $fillable left them out), so the library - which lists a
 * workspace's own items - never showed them again. The uploader is still on
 * the Spatie media row. Restore the owner, and the workspace when that user
 * belongs to exactly one; anything else stays hidden rather than risk showing
 * a file in the wrong workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('media_items')
            ->join('media', function ($join) {
                $join->on('media.model_id', '=', 'media_items.id')
                    ->where('media.model_type', 'App\\Models\\MediaItem');
            })
            ->whereNull('media_items.user_id')
            ->whereNotNull('media.user_id')
            ->select('media_items.id', 'media.user_id')
            ->get();

        foreach ($orphans as $orphan) {
            $workspaces = DB::table('workspace_members')
                ->where('user_id', $orphan->user_id)
                ->where('status', 'active')
                ->distinct()
                ->pluck('workspace_id');

            DB::table('media_items')->where('id', $orphan->id)->whereNull('user_id')->update([
                'user_id' => $orphan->user_id,
                'workspace_id' => $workspaces->count() === 1 ? $workspaces->first() : null,
            ]);
        }
    }

    /** Nothing to undo: the restored values are the ones the uploads should have had. */
    public function down(): void
    {
    }
};
