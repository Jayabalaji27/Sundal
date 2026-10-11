<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiMode;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\Transcriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The voice panel (floating mic on every Sundal page). It talks to the
 * normal assistant routes; this adds starting the AI session from any page
 * and speech to text with the company's own key.
 */
class AiVoiceController extends Controller
{
    /** Pressing the mic: start or resume the AI session, as opening the assistant page does. */
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanUse($user);

        AiMode::resume($request, $user);

        return response()->json([
            'voice' => AiAccess::voiceMode(AiAccess::settings($user)),
            'idleSeconds' => AiMode::idleSeconds($user),
        ]);
    }

    public function transcribe(Request $request, Transcriber $transcriber): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanUse($user);

        $validated = $request->validate([
            // Browsers record webm/ogg (Chrome, Firefox) or mp4 (Safari).
            'audio' => 'required|file|max:' . (int) config('ai_assistant.voice.max_audio_kb', 4096)
                . '|mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4,video/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/wave',
            'lang' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/'],
        ]);

        $settings = AiAccess::settings($user);
        if (AiAccess::voiceMode($settings) !== 'server') {
            return response()->json(['error' => __('This AI provider cannot transcribe speech. The browser\'s speech recognition is used instead.')], 422);
        }

        $key = 'ai-voice:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('ai_assistant.voice.transcriptions_per_minute', 30))) {
            return response()->json(['error' => __('Too many recordings. Please wait a minute.')], 429);
        }
        RateLimiter::hit($key, 60);

        $file = $validated['audio'];
        // As the vendors name it: a sound-only webm/mp4 is often detected as video/*.
        $mime = match ($detected = (string) $file->getMimeType()) {
            'video/webm' => 'audio/webm',
            'video/mp4' => 'audio/mp4',
            'audio/x-wav', 'audio/wave' => 'audio/wav',
            default => $detected,
        };

        try {
            $text = $transcriber->transcribe($settings, $user, (string) $file->get(), $mime, $validated['lang'] ?? null);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['text' => $text]);
    }

    private function ensureCanUse(User $user): void
    {
        $status = AiAccess::status($user);
        abort_if($status === AiAccess::MANAGERS_OFF, 403, __('Your company owner has turned the AI Assistant off for managers.'));
        abort_unless($status === AiAccess::ALLOWED, 403);
    }
}
