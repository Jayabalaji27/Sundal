<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiToolCall;
use App\Models\AiUsage;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiProviderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The AI Assistant page for company owners and managers. Routes sit behind
 * `ai.assistant` (role) and `module.access` (AI add-on); this controller
 * adds the manager switch and per-user ownership of conversations.
 */
class AiAssistantController extends Controller
{
    // AiAssistant is method-injected so each request gets a fresh one (the
    // route caches the controller instance between calls in tests).

    public function index(Request $request): Response
    {
        $user = $request->user();
        $settings = AiAccess::settings($user);
        $isOwner = AiAccess::canManageSettings($user);

        return Inertia::render('ai-assistant/index', [
            'access' => AiAccess::status($user),
            'isOwner' => $isOwner,
            'configured' => (bool) $settings,
            'conversations' => AiConversation::ownedBy($user)
                ->orderByDesc('last_message_at')->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'title', 'last_message_at']),
            'settings' => $isOwner && $settings ? [
                'provider' => $settings->provider,
                'model' => $settings->model,
                'masked_key' => $settings->maskedKey(),
                'azure_endpoint' => $settings->azure_endpoint,
                'azure_deployment' => $settings->azure_deployment,
                'organization' => $settings->organization,
                'monthly_token_cap' => $settings->monthly_token_cap,
                'managers_enabled' => $settings->managers_enabled,
                'retention_days' => $settings->retention_days,
                'last_tested_at' => $settings->last_tested_at?->toIso8601String(),
                'last_test_passed' => $settings->last_test_passed,
            ] : null,
            'usage' => $isOwner && $settings ? [
                'tokens_this_month' => AiUsage::tokensThisMonth($settings->workspace_id),
            ] : null,
            'providers' => $isOwner ? collect(config('ai_assistant.providers'))->map(fn ($p) => [
                'label' => $p['label'],
                'default_model' => $p['default_model'],
                'models' => collect($p['models'])->map(fn ($m, $id) => ['id' => $id, 'label' => $m['label']])->values(),
            ]) : null,
            'retentionOptions' => config('ai_assistant.retention_options'),
        ]);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json([
            'conversation' => $conversation->only(['id', 'title']),
            'messages' => $this->messagesWithCards($conversation),
        ]);
    }

    public function update(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $validated = $request->validate(['title' => 'required|string|max:120']);
        $conversation->update($validated);

        return response()->json(['conversation' => $conversation->only(['id', 'title'])]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    public function send(Request $request, AiAssistant $assistant): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanUse($request);

        $validated = $request->validate([
            'conversation_id' => 'nullable|integer',
            'content' => 'required|string|max:4000',
        ]);

        $limiterKey = 'ai-assistant:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, config('ai_assistant.messages_per_minute', 20))) {
            return response()->json(['error' => __('Too many messages. Please wait a minute.')], 429);
        }
        RateLimiter::hit($limiterKey, 60);

        $conversation = isset($validated['conversation_id'])
            ? AiConversation::ownedBy($user)->findOrFail($validated['conversation_id'])
            : AiConversation::create(['user_id' => $user->id, 'workspace_id' => $user->current_workspace_id]);

        $firstNewId = (int) $conversation->messages()->max('id');

        try {
            $assistant->reply($conversation, $user, trim($validated['content']));
        } catch (AiProviderException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'conversation' => $conversation->fresh()->only(['id', 'title']),
                'messages' => $this->messagesWithCards($conversation, $firstNewId),
            ], 502);
        }

        return response()->json([
            'conversation' => $conversation->fresh()->only(['id', 'title']),
            'messages' => $this->messagesWithCards($conversation, $firstNewId),
        ]);
    }

    public function confirm(Request $request, AiToolCall $toolCall, AiAssistant $assistant): JsonResponse
    {
        $this->ensureCanUse($request);
        $validated = $request->validate(['phrase' => 'nullable|string|max:100']);
        $call = $assistant->confirm($toolCall, $request->user(), $validated['phrase'] ?? null);

        return $this->cardResponse($call);
    }

    public function undo(Request $request, AiToolCall $toolCall, AiAssistant $assistant): JsonResponse
    {
        $this->ensureCanUse($request);
        $call = $assistant->undo($toolCall, $request->user());

        return $this->cardResponse($call);
    }

    public function cancel(Request $request, AiToolCall $toolCall, AiAssistant $assistant): JsonResponse
    {
        $this->ensureCanUse($request);
        $call = $assistant->cancel($toolCall, $request->user());

        return $this->cardResponse($call);
    }

    private function cardResponse(AiToolCall $call): JsonResponse
    {
        $note = AiMessage::where('ai_conversation_id', $call->ai_conversation_id)->latest('id')->first();

        return response()->json([
            'card' => $call->toCard(),
            'message' => $note ? $this->messageJson($note, []) : null,
        ]);
    }

    private function messagesWithCards(AiConversation $conversation, int $afterId = 0): array
    {
        $messages = $conversation->messages()->where('id', '>', $afterId)->get();
        $cards = AiToolCall::where('ai_conversation_id', $conversation->id)
            ->whereIn('ai_message_id', $messages->pluck('id'))
            ->whereNotNull('summary')
            ->orderBy('id')
            ->get()
            ->groupBy('ai_message_id');

        return $messages->map(fn (AiMessage $m) => $this->messageJson(
            $m,
            ($cards[$m->id] ?? collect())->map(fn (AiToolCall $c) => $c->toCard())->values()->all(),
        ))->all();
    }

    private function messageJson(AiMessage $message, array $cards): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'created_at' => $message->created_at?->toIso8601String(),
            'cards' => $cards,
        ];
    }

    private function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        // Workspace is already enforced by the BelongsToWorkspace route binding.
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
    }

    private function ensureCanUse(Request $request): void
    {
        $status = AiAccess::status($request->user());
        abort_if($status === AiAccess::MANAGERS_OFF, 403, __('Your company owner has turned the AI Assistant off for managers.'));
        abort_unless($status === AiAccess::ALLOWED, 403);
    }
}
