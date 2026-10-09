<?php

namespace App\Http\Controllers;

use App\Models\AiProviderSetting;
use App\Services\Ai\AiAccess;
use App\Services\Ai\ConnectionTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * BYOA settings on the AI Assistant page. Company owner only. The API key
 * is write-only: it is encrypted at rest and only its last 4 characters
 * are ever shown again.
 */
class AiAssistantSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(AiAccess::canManageSettings($user), 403);

        $existing = AiAccess::settings($user);
        $validated = $this->validated($request, $existing);

        $settings = $existing ?? new AiProviderSetting(['workspace_id' => $user->current_workspace_id]);
        $connectionChanged = !$existing
            || $existing->provider !== $validated['provider']
            || $existing->model !== $validated['model']
            || !empty($validated['api_key'])
            || $existing->azure_endpoint !== ($validated['azure_endpoint'] ?? null)
            || $existing->azure_deployment !== ($validated['azure_deployment'] ?? null);

        $settings->fill([
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'azure_endpoint' => $validated['provider'] === 'azure_openai' ? $validated['azure_endpoint'] : null,
            'azure_deployment' => $validated['provider'] === 'azure_openai' ? $validated['azure_deployment'] : null,
            'organization' => $validated['provider'] === 'openai' ? ($validated['organization'] ?? null) : null,
            'monthly_token_cap' => $validated['monthly_token_cap'] ?? null,
            'managers_enabled' => $validated['managers_enabled'],
            'retention_days' => $validated['retention_days'],
            'idle_timeout_minutes' => $validated['idle_timeout_minutes'],
            'updated_by' => $user->id,
        ]);

        if (!empty($validated['api_key'])) {
            $settings->api_key = $validated['api_key'];
            $settings->api_key_last4 = mb_substr($validated['api_key'], -4);
        }

        if ($connectionChanged) {
            $settings->last_tested_at = null;
            $settings->last_test_passed = null;
        }

        $settings->save();

        return back()->with('success', __('AI settings saved.'));
    }

    /** Test the form's values before (or without) saving them. */
    public function test(Request $request, ConnectionTester $tester): JsonResponse
    {
        $user = $request->user();
        abort_unless(AiAccess::canManageSettings($user), 403);

        $existing = AiAccess::settings($user);
        $validated = $this->validated($request, $existing);

        $candidate = new AiProviderSetting([
            'workspace_id' => $user->current_workspace_id,
            'provider' => $validated['provider'],
            'model' => $validated['model'],
            'azure_endpoint' => $validated['azure_endpoint'] ?? null,
            'azure_deployment' => $validated['azure_deployment'] ?? null,
            'organization' => $validated['organization'] ?? null,
        ]);
        $candidate->api_key = $validated['api_key'] ?: $existing?->api_key;

        $result = $tester->test($candidate);

        // Remember the result when the tested values are the saved ones.
        if ($existing && empty($validated['api_key'])
            && $existing->provider === $candidate->provider
            && $existing->model === $candidate->model
            && $existing->azure_endpoint === $candidate->azure_endpoint
            && $existing->azure_deployment === $candidate->azure_deployment) {
            $existing->update(['last_tested_at' => now(), 'last_test_passed' => $result['passed']]);
        }

        return response()->json($result);
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless(AiAccess::canManageSettings($request->user()), 403);
        AiAccess::settings($request->user())?->delete();

        return back()->with('success', __('AI provider disconnected.'));
    }

    private function validated(Request $request, ?AiProviderSetting $existing): array
    {
        $providers = array_keys(config('ai_assistant.providers'));
        $provider = $request->input('provider');
        $models = array_keys(config("ai_assistant.providers.{$provider}.models", []));
        // A new key is needed when nothing is saved yet or the vendor changes.
        $needsKey = !$existing || $existing->provider !== $provider;

        $validated = $request->validate([
            'provider' => ['required', Rule::in($providers)],
            'model' => array_filter([
                'required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\/-]+$/',
                config('ai_assistant.allow_custom_model') || !$models ? null : Rule::in($models),
            ]),
            'api_key' => [$needsKey ? 'required' : 'nullable', 'string', 'min:8', 'max:500'],
            'azure_endpoint' => [
                'nullable', 'required_if:provider,azure_openai', 'string', 'max:255',
                'regex:' . config('ai_assistant.providers.azure_openai.endpoint_pattern'),
            ],
            'azure_deployment' => ['nullable', 'required_if:provider,azure_openai', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'organization' => ['nullable', 'string', 'max:100'],
            'monthly_token_cap' => ['nullable', 'integer', 'min:1000'],
            'managers_enabled' => ['boolean'],
            'retention_days' => ['nullable', Rule::in(config('ai_assistant.retention_options'))],
            'idle_timeout_minutes' => ['nullable', Rule::in(config('ai_assistant.mode.idle_timeout_options'))],
        ], [
            'azure_endpoint.regex' => __('Use your Azure OpenAI resource address, for example https://my-company.openai.azure.com'),
        ]);

        $validated['api_key'] = isset($validated['api_key']) ? trim($validated['api_key']) : null;
        $validated['managers_enabled'] = (bool) ($validated['managers_enabled'] ?? true);
        $validated['retention_days'] = (int) ($validated['retention_days'] ?? config('ai_assistant.retention_days'));
        $validated['idle_timeout_minutes'] = (int) ($validated['idle_timeout_minutes'] ?? ($existing?->idle_timeout_minutes ?: config('ai_assistant.mode.idle_timeout_minutes')));

        return $validated;
    }
}
