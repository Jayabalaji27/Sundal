<?php

namespace App\Http\Controllers;

use App\Models\AiAttachment;
use App\Services\Ai\AiAccess;
use App\Services\Ai\Attachments\AttachmentStore;
use App\Services\Ai\Attachments\SundalFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The + button in the AI Assistant's message box: upload a file, or pick
 * one already in Sundal. The file is read straight away and waits as a chip
 * until the message is sent with it.
 */
class AiAttachmentController extends Controller
{
    public function store(Request $request, AttachmentStore $store): JsonResponse
    {
        $this->ensureCanUse($request);
        if ($limited = $this->throttle($request)) {
            return $limited;
        }

        $request->validate([
            'file' => ['required', 'file', 'max:' . (int) (AttachmentStore::maxBytes() / 1024)],
        ], ['file.max' => __('Files can be at most :mb MB.', ['mb' => config('ai_assistant.attachments.max_size_mb', 10)])]);

        $attachment = $store->fromUpload($request->file('file'), $request->user());

        return response()->json(['attachment' => $attachment->toChip()], 201);
    }

    /** Documents already in Sundal that this user may attach. */
    public function sundalFiles(Request $request, SundalFiles $files): JsonResponse
    {
        $this->ensureCanUse($request);
        $validated = $request->validate(['search' => 'nullable|string|max:100']);

        return response()->json(['files' => $files->list($request->user(), trim($validated['search'] ?? ''))]);
    }

    public function fromSundal(Request $request, SundalFiles $files, AttachmentStore $store): JsonResponse
    {
        $this->ensureCanUse($request);
        if ($limited = $this->throttle($request)) {
            return $limited;
        }
        $validated = $request->validate(['media_id' => 'required|integer']);

        $media = $files->find($request->user(), (int) $validated['media_id']);
        abort_unless($media, 404);

        $attachment = $store->fromContents($media->file_name, $files->contents($media), $request->user(), 'sundal');

        return response()->json(['attachment' => $attachment->toChip()], 201);
    }

    /** Remove a chip before sending. Sent files go with their chat. */
    public function destroy(Request $request, AiAttachment $attachment): JsonResponse
    {
        abort_unless((int) $attachment->user_id === (int) $request->user()->id, 404);
        abort_if($attachment->ai_message_id !== null, 422, __('This file was already sent; it is removed with its chat.'));

        $attachment->delete();

        return response()->json(['deleted' => true]);
    }

    private function ensureCanUse(Request $request): void
    {
        $status = AiAccess::status($request->user());
        abort_if($status === AiAccess::MANAGERS_OFF, 403, __('Your company owner has turned the AI Assistant off for managers.'));
        abort_unless($status === AiAccess::ALLOWED, 403);
    }

    private function throttle(Request $request): ?JsonResponse
    {
        $key = 'ai-attachments:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            return response()->json(['error' => __('Too many files. Please wait a minute.')], 429);
        }
        RateLimiter::hit($key, 60);

        return null;
    }
}
