<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\BugAttachment;
use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BugAttachmentController extends Controller
{
    use AuthorizesRequests;
    public function store(Request $request, Bug $bug)
    {
        // Handle media library selection
        if ($request->has('media_item_ids')) {
            $request->validate([
                'media_item_ids' => 'required|array',
                'media_item_ids.*' => 'exists:media_items,id'
            ]);

            foreach ($request->media_item_ids as $mediaItemId) {
                BugAttachment::create([
                    'bug_id' => $bug->id,
                    'media_item_id' => $mediaItemId,
                    'uploaded_by' => auth()->id()
                ]);
            }
        } else {
            // Handle file upload
            $request->validate([
                'files' => 'required|array',
                'files.*' => 'file|max:10240' // 10MB max per file
            ]);

            foreach ($request->file('files') as $file) {
                // Store file using MediaItem
                $mediaItem = MediaItem::create([
                    'name' => $file->getClientOriginalName(),
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'disk' => config('filesystems.default'),
                    'directory' => 'bug-attachments',
                    'created_by' => auth()->id()
                ]);

                // Store the actual file
                $path = $file->store('bug-attachments', config('filesystems.default'));
                $mediaItem->update(['path' => $path]);

                // Create bug attachment record
                BugAttachment::create([
                    'bug_id' => $bug->id,
                    'media_item_id' => $mediaItem->id,
                    'uploaded_by' => auth()->id()
                ]);
            }
        }

        return back();
    }

    public function destroy(BugAttachment $bugAttachment)
    {
        // Delete the file from storage
        if ($bugAttachment->mediaItem && $bugAttachment->mediaItem->path) {
            Storage::disk($bugAttachment->mediaItem->disk)->delete($bugAttachment->mediaItem->path);
        }

        // Delete media item record
        $bugAttachment->mediaItem?->delete();

        // Delete attachment record
        $bugAttachment->delete();

        return back();
    }

    public function download(BugAttachment $bugAttachment)
    {
        try {
            $this->authorize('view', $bugAttachment->bug);
        } catch (\Exception $e) {
            abort(403, 'Unauthorized');
        }
        
        $mediaItem = $bugAttachment->mediaItem;
        
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