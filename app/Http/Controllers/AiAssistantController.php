<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiToolCall;
use App\Models\AiUsage;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\ToolRegistry;
use App\Services\Ai\Topics;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function index(Request $request, ToolRegistry $registry): Response|RedirectResponse
    {
        // The page's requests follow the same session rules as AI mode (except
        // "Sundal must be open": this page is Sundal).
        \App\Services\Ai\AiMode::start($request, $request->user());

        return $this->page($request, $registry);
    }

    /**
     * AI mode: the same page in its own browser tab, full screen, opened with
     * the "AI mode" switch in the Sundal header. The route also asks for the
     * password unless it was confirmed recently; opening it starts the AI mode
     * session (workspace lock, idle clock, Sundal-open check).
     */
    public function aiMode(Request $request, ToolRegistry $registry): Response|RedirectResponse
    {
        $user = $request->user();
        \App\Services\Ai\AiMode::start($request, $user);

        return $this->page($request, $registry, [
            'standalone' => true,
            'aiMode' => [...\App\Services\Ai\AiMode::clientConfig($user), 'workspaceName' => $user->currentWorkspace?->name],
        ]);
    }

    /** @param  array<string, mixed>  $extra  props for the AI mode tab */
    private function page(Request $request, ToolRegistry $registry, array $extra = []): Response|RedirectResponse
    {
        $user = $request->user();
        $isOwner = AiAccess::canManageSettings($user);

        // Without the AI add-on: owners (who can buy plans) see the upgrade page;
        // managers go back to the dashboard, as with the other add-on modules.
        if (AiAccess::status($user) === AiAccess::NEEDS_PLAN) {
            if (!$isOwner) {
                return redirect()->route('dashboard')->with('error', __('The AI Assistant requires the Pro Add-on plan. Please ask your workspace owner to upgrade.'));
            }

            return Inertia::render('ai-assistant/index', [
                'access' => AiAccess::NEEDS_PLAN,
                'isOwner' => true,
                'configured' => false,
                'conversations' => [],
                'settings' => null,
                'usage' => null,
                'providers' => null,
                'retentionOptions' => [],
                'topics' => [],
                ...$extra,
            ]);
        }

        $settings = AiAccess::settings($user);

        return Inertia::render('ai-assistant/index', [
            'access' => AiAccess::status($user),
            'isOwner' => $isOwner,
            'configured' => (bool) $settings,
            'conversations' => AiConversation::ownedBy($user)
                ->orderByDesc('last_message_at')->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'title', 'topic', 'last_message_at']),
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
                'idle_timeout_minutes' => $settings->idle_timeout_minutes,
                'last_tested_at' => $settings->last_tested_at?->toIso8601String(),
                'last_test_passed' => $settings->last_test_passed,
            ] : null,
            'usage' => $isOwner && $settings ? [
                'tokens_this_month' => AiUsage::tokensThisMonth($settings->workspace_id),
                'daily' => AiUsage::dailyTokens($settings->workspace_id, 30),
            ] : null,
            'providers' => $isOwner ? collect(config('ai_assistant.providers'))->map(fn ($p) => [
                'label' => $p['label'],
                'default_model' => $p['default_model'],
                'models' => collect($p['models'])->map(fn ($m, $id) => ['id' => $id, 'label' => $m['label']])->values(),
            ]) : null,
            'retentionOptions' => config('ai_assistant.retention_options'),
            // Topic buttons in the message box, only those with tools this user may use.
            'topics' => $settings && AiAccess::canUse($user) ? Topics::forUser($user, $registry) : [],
            'idleTimeoutOptions' => config('ai_assistant.mode.idle_timeout_options'),
            // The model pill in the chat header (names only, never the key).
            'model' => $settings && AiAccess::canUse($user) ? [
                'provider' => config("ai_assistant.providers.{$settings->provider}.label", $settings->provider),
                // Model ids contain dots and slashes ("openai/gpt-4.1"): no dot-notation lookup.
                'name' => (config("ai_assistant.providers.{$settings->provider}.models") ?? [])[$settings->model]['label'] ?? $settings->model,
            ] : null,
            ...$extra,
        ]);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json([
            'conversation' => $conversation->only(['id', 'title', 'topic']),
            'messages' => $this->messagesWithCards($conversation),
        ]);
    }

    public function update(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $validated = $request->validate(['title' => 'required|string|max:120']);
        $conversation->update($validated);

        return response()->json(['conversation' => $conversation->only(['id', 'title', 'topic'])]);
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
            // Topic button: a key sets it, '' or null clears it, absent leaves it.
            'topic' => ['nullable', 'string', Rule::in(['', ...Topics::keys()])],
        ]);

        $limiterKey = 'ai-assistant:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, config('ai_assistant.messages_per_minute', 20))) {
            return response()->json(['error' => __('Too many messages. Please wait a minute.')], 429);
        }
        RateLimiter::hit($limiterKey, 60);

        $conversation = isset($validated['conversation_id'])
            ? AiConversation::ownedBy($user)->findOrFail($validated['conversation_id'])
            : AiConversation::create(['user_id' => $user->id, 'workspace_id' => $user->current_workspace_id]);

        if ($request->has('topic')) {
            $conversation->update(['topic' => ($validated['topic'] ?? null) ?: null]);
        }

        $firstNewId = (int) $conversation->messages()->max('id');

        // Queue mode: answer in the background; the page polls the conversation.
        if (config('ai_assistant.queue')) {
            $message = $assistant->addUserMessage($conversation, trim($validated['content']));
            \App\Jobs\ProcessAiMessage::dispatch($user->id, $conversation->id, $message->id);

            return response()->json([
                'conversation' => $conversation->fresh()->only(['id', 'title', 'topic']),
                'messages' => $this->messagesWithCards($conversation, $firstNewId),
                'pending' => true,
            ], 202);
        }

        // A reply can take several AI round trips. Let the provider timeout end a slow
        // call (a normal, saved error with the form fallback) instead of PHP's 30 s
        // limit killing the request with nothing saved.
        @set_time_limit((int) config('ai_assistant.sync_time_limit', 300));

        try {
            $assistant->reply($conversation, $user, trim($validated['content']));
        } catch (AiProviderException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'conversation' => $conversation->fresh()->only(['id', 'title', 'topic']),
                'messages' => $this->messagesWithCards($conversation, $firstNewId),
            ], 502);
        }

        return response()->json([
            'conversation' => $conversation->fresh()->only(['id', 'title', 'topic']),
            'messages' => $this->messagesWithCards($conversation, $firstNewId),
        ]);
    }

    /**
     * A draft card's values changed: a clicked choice button, or the card's
     * Edit form. Moves it to the next question or to confirmation; nothing runs.
     */
    public function updateCard(Request $request, AiToolCall $toolCall, AiAssistant $assistant): JsonResponse
    {
        $this->ensureCanUse($request);
        if ($limited = $this->throttleCards($request)) {
            return $limited;
        }
        $validated = $request->validate($this->fieldRules(required: true));

        return $this->cardResponse($assistant->updateDraft($toolCall, $request->user(), $validated['fields']));
    }

    public function confirm(Request $request, AiToolCall $toolCall, AiAssistant $assistant): JsonResponse
    {
        $this->ensureCanUse($request);
        if ($limited = $this->throttleCards($request)) {
            return $limited;
        }
        $validated = $request->validate(['phrase' => 'nullable|string|max:100', ...$this->fieldRules(required: false)]);
        $call = $assistant->confirm($toolCall, $request->user(), $validated['phrase'] ?? null, $validated['fields'] ?? null);

        return $this->cardResponse($call);
    }

    public function undo(Request $request, AiToolCall $toolCall, AiAssistant $assistant): JsonResponse
    {
        $this->ensureCanUse($request);
        if ($limited = $this->throttleCards($request)) {
            return $limited;
        }
        $call = $assistant->undo($toolCall, $request->user());

        return $this->cardResponse($call);
    }

    public function cancel(Request $request, AiToolCall $toolCall, AiAssistant $assistant): JsonResponse
    {
        $this->ensureCanUse($request);
        if ($limited = $this->throttleCards($request)) {
            return $limited;
        }
        $call = $assistant->cancel($toolCall, $request->user());

        return $this->cardResponse($call);
    }

    /**
     * Card values: each is a single value (an id, an option, a date, a short
     * text) or, for "several people", a short list of ids. Anything else is
     * refused before it reaches the form checks.
     */
    private function fieldRules(bool $required): array
    {
        return [
            'fields' => [$required ? 'required' : 'nullable', 'array', 'max:30'],
            'fields.*' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                $single = fn ($v) => is_int($v) || (is_string($v) && mb_strlen($v) <= 4000);
                $ok = $single($value)
                    || (is_array($value) && array_is_list($value) && count($value) <= 200 && collect($value)->every($single));
                if (!$ok) {
                    $fail(__('This value is not valid.'));
                }
            }],
        ];
    }

    /** Card actions (confirm, cancel, undo, update): 60 a minute per user. */
    private function throttleCards(Request $request): ?JsonResponse
    {
        $key = 'ai-cards:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['error' => __('Too many actions. Please wait a minute.')], 429);
        }
        RateLimiter::hit($key, 60);

        return null;
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
            'error' => (bool) $message->is_error,
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
