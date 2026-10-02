<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasPermissionChecks;

class TodoAttachmentController extends Controller
{
    use HasPermissionChecks;

    public function store(Request $request, Todo $todo)
    {
        $this->authorizePermission('todo_attachment_create');

        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:10240'
        ]);
        
        $files = $request->file('files');
        
        foreach ($files as $file) {
            $filenameWithExt = $file->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '_' . uniqid() . '.' . $extension;

            $singleFileRequest = new Request();
            $singleFileRequest->files->set('file', $file);
            $singleFileRequest->merge(['file' => $file]);
            
            $upload = upload_file($singleFileRequest, 'file', $fileNameToStore, 'todos/attachments');
            
            if ($upload['status'] == true) {
                TodoAttachment::create([
                    'todo_id' => $todo->id,
                    'file' => $upload['url'],
                    'uploaded_by' => auth()->id()
                ]);
            } else {
                return back()->withErrors(['files' => $upload['msg']]);
            }
        }

        if (!config('app.is_demo', true)) {
            event(new \App\Events\TodoCommentAdded($todo, 'attachment'));
        }

        return back()->with('success', __('Attachment uploaded successfully'));
    }

    public function destroy(TodoAttachment $todoAttachment)
    {
        $this->authorizePermission('todo_attachment_delete');

        delete_file($todoAttachment->file);
        $todoAttachment->delete();

        return back()->with('success', 'Attachment removed successfully.');
    }

    public function download(TodoAttachment $todoAttachment)
    {
        $this->authorizePermission('todo_attachment_download');
        return download_file($todoAttachment->file, basename($todoAttachment->file));
    }
}
