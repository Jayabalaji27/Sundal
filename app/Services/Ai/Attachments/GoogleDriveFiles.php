<?php

namespace App\Services\Ai\Attachments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Fetches a file the user picked in the Google Drive picker, with the
 * short-lived access token their browser got from Google (drive.readonly,
 * never stored). Google Docs and Sheets are exported as .docx and .xlsx.
 */
class GoogleDriveFiles
{
    private const API = 'https://www.googleapis.com/drive/v3/files/';

    private const EXPORTS = [
        'application/vnd.google-apps.document' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
        'application/vnd.google-apps.spreadsheet' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
    ];

    public static function configured(): bool
    {
        return (bool) config('ai_assistant.attachments.google_drive.client_id') && (bool) config('ai_assistant.attachments.google_drive.api_key');
    }

    /** @return array{name: string, contents: string} */
    public function fetch(string $fileId, string $token): array
    {
        try {
            $meta = Http::withToken($token)->timeout(20)->get(self::API . rawurlencode($fileId), ['fields' => 'name,mimeType,size', 'supportsAllDrives' => 'true']);
            if (!$meta->ok()) {
                $this->refuse($meta->status() === 404 ? __('Google Drive could not find that file, or you have no access to it.') : __('Google Drive refused the request. Pick the file again.'));
            }

            $name = (string) $meta->json('name');
            $mime = (string) $meta->json('mimeType');
            if (isset(self::EXPORTS[$mime])) {
                [$exportMime, $extension] = self::EXPORTS[$mime];
                $name = pathinfo($name, PATHINFO_FILENAME) . ".{$extension}";
                $download = Http::withToken($token)->timeout(60)->get(self::API . rawurlencode($fileId) . '/export', ['mimeType' => $exportMime]);
            } else {
                if ((int) $meta->json('size') > AttachmentStore::maxBytes()) {
                    $this->refuse(__('Files can be at most :mb MB.', ['mb' => config('ai_assistant.attachments.max_size_mb', 10)]));
                }
                $download = Http::withToken($token)->timeout(60)->get(self::API . rawurlencode($fileId), ['alt' => 'media', 'supportsAllDrives' => 'true']);
            }
        } catch (ConnectionException) {
            $this->refuse(__('Google Drive could not be reached. Try again.'));
        }

        if (!$download->ok()) {
            $this->refuse(__('Google Drive could not send this file.'));
        }
        if (strlen($download->body()) > AttachmentStore::maxBytes()) {
            $this->refuse(__('Files can be at most :mb MB.', ['mb' => config('ai_assistant.attachments.max_size_mb', 10)]));
        }

        return ['name' => $name, 'contents' => $download->body()];
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
