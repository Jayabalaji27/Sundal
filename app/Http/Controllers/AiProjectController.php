<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskStage;
use App\Services\PlanLimitService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenAI;

class AiProjectController extends Controller
{
    use HasPermissionChecks;

    /**
     * Parse a free-text requirement into a structured project plan via AI.
     */
    public function parse(Request $request): JsonResponse
    {
        $this->authorizePermission('project_create');

        $request->validate([
            'requirements' => 'required|string|min:20|max:3000',
        ]);

        $prompt = <<<PROMPT
You are a senior project manager. A user has described a software project in plain text.
Convert it into a structured project plan.

Return ONLY valid JSON in this exact format — no explanation, no markdown:
{
  "title": "Short project title",
  "description": "2-3 sentence project description",
  "priority": "medium",
  "estimated_hours": 120,
  "tasks": [
    { "title": "Task title", "description": "What needs to be done", "priority": "medium", "estimated_hours": 8 }
  ]
}

Rules:
- priority must be: low, medium, high, or urgent
- estimated_hours must be integers
- Generate 5-12 tasks that cover the full project scope
- Tasks should be actionable and specific

User's requirements:
{$request->requirements}
PROMPT;

        $ai = app(\App\Services\Ai\WorkspaceAi::class);
        try {
            // The workspace's AI (Settings → AI Assistant, e.g. a free OpenRouter model);
            // an OpenAI key under Chat GPT Settings is only the fallback.
            if ($ai->available($request->user())) {
                $content = $ai->complete($request->user(), 'You are a senior project manager. You reply with JSON only.', $prompt, 4096);
            } else {
                $apiKey = getSetting('chatgptKey');
                if (!$apiKey || !str_starts_with(trim($apiKey), 'sk-')) {
                    return response()->json([
                        'success' => false,
                        'message' => __('AI is not set up for this workspace yet. The workspace owner can connect it under Settings → AI Assistant.'),
                    ], 422);
                }
                $response = OpenAI::client(trim($apiKey))->chat()->create([
                    'model'    => getSetting('chatgptModel') ?: 'gpt-3.5-turbo',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.4,
                ]);
                $content = trim($response->choices[0]->message->content);
            }

            $plan = $this->decodePlan($content);
            if (!$plan) {
                throw new \Exception(__('The AI did not return a usable plan. Please try again or add more detail.'));
            }

            return response()->json(['success' => true, 'plan' => $plan]);
        } catch (\App\Services\Ai\WorkspaceAiUnavailable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('AI parsing failed: :msg', ['msg' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * The plan JSON from the model's reply. Free models often wrap it in a code
     * fence or a sentence, so this takes the outermost {...} block, then keeps
     * only tasks with a title and maps priorities to the allowed values.
     */
    private function decodePlan(string $content): ?array
    {
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $plan = json_decode(substr($content, $start, $end - $start + 1), true);
        if (!is_array($plan) || empty($plan['title']) || !is_array($plan['tasks'] ?? null)) {
            return null;
        }

        $priority = fn ($p, array $allowed) => in_array(strtolower((string) $p), $allowed, true) ? strtolower((string) $p) : 'medium';
        $plan['priority'] = $priority($plan['priority'] ?? null, ['low', 'medium', 'high', 'urgent']);
        $plan['estimated_hours'] = isset($plan['estimated_hours']) ? (int) $plan['estimated_hours'] : null;
        $plan['tasks'] = collect($plan['tasks'])
            ->filter(fn ($t) => is_array($t) && filled($t['title'] ?? null))
            ->map(fn ($t) => [
                'title' => mb_substr((string) $t['title'], 0, 255),
                'description' => (string) ($t['description'] ?? ''),
                // Tasks allow critical, not urgent.
                'priority' => str_replace('urgent', 'critical', $priority($t['priority'] ?? null, ['low', 'medium', 'high', 'urgent', 'critical'])),
                'estimated_hours' => isset($t['estimated_hours']) ? (int) $t['estimated_hours'] : null,
            ])
            ->values()->all();

        return $plan['tasks'] ? $plan : null;
    }

    /**
     * Create the project + tasks from the parsed plan.
     */
    public function create(Request $request): JsonResponse
    {
        $this->authorizePermission('project_create');

        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'priority'        => 'required|in:low,medium,high,urgent',
            'estimated_hours' => 'nullable|integer',
            'tasks'           => 'array',
            'tasks.*.title'   => 'required|string|max:255',
            'tasks.*.description'     => 'nullable|string',
            'tasks.*.priority'        => 'required|in:low,medium,high,critical',
            'tasks.*.estimated_hours' => 'nullable|integer',
        ]);

        $user      = auth()->user();
        $workspace = $user->currentWorkspace;
        if (!$workspace) abort(404);

        $project = DB::transaction(function () use ($validated, $user, $workspace) {
            $project = Project::create([
                'workspace_id'    => $workspace->id,
                'title'           => $validated['title'],
                'description'     => $validated['description'] ?? null,
                'priority'        => $validated['priority'],
                'estimated_hours' => $validated['estimated_hours'] ?? null,
                'status'          => 'planning',
                'created_by'      => $user->id,
            ]);

            // Get or create a default "To Do" stage
            $stage = TaskStage::where('workspace_id', $workspace->id)
                ->where('is_default', true)
                ->first()
                ?? TaskStage::where('workspace_id', $workspace->id)->first()
                ?? TaskStage::create([
                    'workspace_id' => $workspace->id,
                    'name'         => 'To Do',
                    'color'        => '#6366f1',
                    'order'        => 0,
                    'is_default'   => true,
                ]);

            foreach ($validated['tasks'] ?? [] as $i => $taskData) {
                Task::create([
                    'project_id'    => $project->id,
                    'task_stage_id' => $stage->id,
                    'title'         => $taskData['title'],
                    'description'   => $taskData['description'] ?? null,
                    'priority'      => $taskData['priority'],
                    'created_by'    => $user->id,
                    'progress'      => 0,
                ]);
            }

            return $project;
        });

        return response()->json([
            'success'  => true,
            'project'  => ['id' => $project->id, 'title' => $project->title],
            'redirect' => route('projects.show', $project->id),
        ]);
    }
}
