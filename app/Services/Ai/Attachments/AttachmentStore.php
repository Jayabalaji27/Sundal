<?php

namespace App\Services\Ai\Attachments;

use App\Models\AiAttachment;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Takes a file for the AI Assistant (uploaded, copied from Sundal or from
 * Google Drive), checks it, reads it and keeps it on the private disk.
 * The file counts toward the company plan's storage.
 */
class AttachmentStore
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PlanLimitService $limits,
    ) {}

    public function fromUpload(UploadedFile $file, User $user): AiAttachment
    {
        return $this->store($file->getRealPath(), $file->getClientOriginalName(), $file->getSize(), $user, 'upload');
    }

    /** A file whose bytes Sundal fetched itself (a Sundal file, a Google Drive file). */
    public function fromContents(string $name, string $contents, User $user, string $source): AiAttachment
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ai-att');
        try {
            file_put_contents($tmp, $contents);

            return $this->store($tmp, $name, strlen($contents), $user, $source);
        } finally {
            @unlink($tmp);
        }
    }

    public static function extensions(): array
    {
        return config('ai_assistant.attachments.extensions', []);
    }

    public static function maxBytes(): int
    {
        return (int) config('ai_assistant.attachments.max_size_mb', 10) * 1024 * 1024;
    }

    private function store(string $path, string $name, int $size, User $user, string $source): AiAttachment
    {
        $name = $this->cleanName($name);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($extension, self::extensions(), true)) {
            $this->refuse(__('Only PDF, Excel (.xlsx, .xls, .csv), Word (.docx) and text (.txt, .md) files can be attached.'));
        }
        if ($size > self::maxBytes()) {
            $this->refuse(__('Files can be at most :mb MB.', ['mb' => config('ai_assistant.attachments.max_size_mb', 10)]));
        }
        $room = $this->limits->canUploadFile($user, $size);
        if (!($room['allowed'] ?? false)) {
            $this->refuse($room['message'] ?? __('Storage limit exceeded'));
        }

        @set_time_limit(120);
        $read = $this->reader->read($path, $extension);
        if ($read['status'] === AiAttachment::FAILED) {
            $this->refuse($read['error']);
        }

        $disk = config('ai_assistant.attachments.disk', 'local');
        $stored = "ai-attachments/{$user->current_workspace_id}/" . Str::uuid() . ".{$extension}";
        $handle = fopen($path, 'rb');
        try {
            Storage::disk($disk)->put($stored, $handle);
        } finally {
            is_resource($handle) && fclose($handle);
        }

        return AiAttachment::create([
            'workspace_id' => $user->current_workspace_id,
            'user_id' => $user->id,
            'source' => $source,
            'original_name' => $name,
            'extension' => $extension,
            'mime' => mime_content_type($path) ?: null,
            'size' => $size,
            'disk' => $disk,
            'path' => $stored,
            'kind' => $read['kind'],
            'status' => $read['status'],
            'error' => $read['error'],
            'extracted_text' => $read['text'],
            'structure' => $read['structure'],
        ]);
    }

    /** A plain file name: no folders, no control characters, at most 200 characters. */
    private function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));

        if (mb_strlen($name) > 200) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 190) . '.' . $extension;
        }

        return $name !== '' ? $name : 'file';
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
