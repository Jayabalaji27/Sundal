<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectAttachmentController extends Controller
{
    public function store(Request $request, Project $project)
    {
        // Ensure project belongs to current workspace and is visible to this user
        if ($project->workspace_id !== auth()->user()->current_workspace_id) {
            abort(404);
        }
        abort_if(! Project::visibleTo(auth()->user())->whereKey($project->id)->exists(), 404);

        // Debug log
        \Log::info('Received media_ids:', $request->all());
        
        $request->validate([
            'media_ids' => 'required|array',
            'media_ids.*' => 'integer'
        ]);
        
        // Check if media items exist
        $mediaItems = MediaItem::whereIn('id', $request->media_ids)->get();
        if ($mediaItems->count() !== count($request->media_ids)) {
            return back()->withErrors(['media_ids' => 'Some selected media items do not exist.']);
        }

        $attachments = [];
        $fileNames = [];
        foreach ($mediaItems as $mediaItem) {
            $attachment = ProjectAttachment::create([
                'workspace_id' => $project->workspace_id,
                'project_id' => $project->id,
                'media_item_id' => $mediaItem->id,
                'uploaded_by' => auth()->id()
            ]);
            
            $attachment->load(['mediaItem', 'uploadedBy']);
            $attachments[] = $attachment;
            $fileNames[] = $mediaItem->name;
        }

        $fileList = count($fileNames) > 1 ? implode(', ', $fileNames) : $fileNames[0];
        $project->logActivity('attachment_added', "File(s) '{$fileList}' uploaded to project");
        return back()->with('success', __('Attachment(s) uploaded successfully'));
    }

    public function destroy(ProjectAttachment $projectAttachment)
    {
        // Ensure attachment belongs to current workspace and its project is visible to this user
        if ($projectAttachment->workspace_id !== auth()->user()->current_workspace_id) {
            abort(404);
        }
        abort_if(! Project::visibleTo(auth()->user())->whereKey($projectAttachment->project_id)->exists(), 404);

        $project = $projectAttachment->project;
        $fileName = $projectAttachment->mediaItem->name;
        $projectAttachment->delete();
        
        $project->logActivity('attachment_removed', "File '{$fileName}' removed from project");

        return back()->with('success', __('Attachment deleted successfully'));
    }

    public function download(ProjectAttachment $projectAttachment)
    {
        // Ensure attachment belongs to current workspace and its project is visible to this user
        if ($projectAttachment->workspace_id !== auth()->user()->current_workspace_id) {
            abort(404);
        }
        abort_if(! Project::visibleTo(auth()->user())->whereKey($projectAttachment->project_id)->exists(), 404);

        $mediaItem = $projectAttachment->mediaItem;
        $media = $mediaItem->getFirstMedia('images');
        
        if (!$media) {
            abort(404, __('File not found'));
        }

        $filePath = $media->getPath();
        
        // Check if file exists
        if (!file_exists($filePath)) {
            abort(404, __('File not found on disk'));
        }

        return response()->download($filePath, $mediaItem->name);
    }
}