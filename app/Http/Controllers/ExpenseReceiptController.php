<?php

namespace App\Http\Controllers;

use App\Models\ProjectExpense;
use App\Models\ExpenseAttachment;
use App\Models\MediaItem;
use Illuminate\Http\Request;

class ExpenseReceiptController extends Controller
{
    public function upload(Request $request, ProjectExpense $expense)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
            'attachment_type' => 'required|in:receipt,invoice,document'
        ]);

        $attachments = [];

        foreach ($request->file('files') as $file) {
            // Store file using existing media system
            $mediaItem = MediaItem::create([
                'name' => $file->getClientOriginalName(),
                'file_name' => $file->store('expense-receipts', 'public'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id()
            ]);

            // Create expense attachment
            $attachment = ExpenseAttachment::create([
                'project_expense_id' => $expense->id,
                'media_item_id' => $mediaItem->id,
                'uploaded_by' => auth()->id(),
                'attachment_type' => $request->attachment_type
            ]);

            $attachments[] = $attachment->load('mediaItem');
        }

        return response()->json([
            'attachments' => $attachments,
            'message' => 'Files uploaded successfully'
        ]);
    }

    public function destroy(ExpenseAttachment $attachment)
    {
        // Check if user can delete this attachment
        if ($attachment->uploaded_by !== auth()->id() && !auth()->user()->can('edit-any-expenses')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted successfully']);
    }

    public function download(ExpenseAttachment $attachment)
    {
        $mediaItem = $attachment->mediaItem;
        $filePath = storage_path('app/public/' . $mediaItem->file_name);

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->download($filePath, $mediaItem->name);
    }
}