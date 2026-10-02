<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskAttachmentController extends Controller
{
    use AuthorizesRequests;
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'media_item_ids' => 'required|array',
            'media_item_ids.*' => 'exists:media_items,id'
        ]);

        foreach ($validated['media_item_ids'] as $mediaItemId) {
            TaskAttachment::firstOrCreate([
                'task_id' => $task->id,
                'media_item_id' => $mediaItemId
            ], [
                'uploaded_by' => auth()->id()
            ]);
        }

        return back();
    }

    public function destroy(TaskAttachment $taskAttachment)
    {
        // Check if user can access the workspace
        $workspace = $taskAttachment->task->project->workspace;
        if (!$workspace->members()->where('user_id', auth()->id())->exists()) {
            abort(403, 'Unauthorized');
        }

        $taskAttachment->delete();

        return back();
    }

    public function download(TaskAttachment $taskAttachment)
    {
        // Skip authorization for now - just check if user can access the workspace
        $workspace = $taskAttachment->task->project->workspace;
        if (!$workspace->members()->where('user_id', auth()->id())->exists()) {
            abort(403, 'Unauthorized');
        }
        
        $mediaItem = $taskAttachment->mediaItem;
        
        if (!$mediaItem) {
            abort(404, 'Media item not found');
        }
        
        // For media library items, download from URL
        if ($mediaItem->url) {
            $headers = [
                'Content-Type' => $mediaItem->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $mediaItem->name . '"',
            ];
            return response()->streamDownload(function() use ($mediaItem) {
                echo file_get_contents($mediaItem->url);
            }, $mediaItem->name, $headers);
        }
        
        // For uploaded files, check path and download
        if (!$mediaItem->path || !Storage::disk($mediaItem->disk ?? 'public')->exists($mediaItem->path)) {
            abort(404, 'File not found');
        }

        return Storage::disk($mediaItem->disk ?? 'public')->download(
            $mediaItem->path,
            $mediaItem->name
        );
    }
}