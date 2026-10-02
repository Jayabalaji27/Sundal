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

        $apiKey = Setting::where('key', 'chatgptKey')->value('value');
        $model  = Setting::where('key', 'chatgptModel')->value('value') ?? 'gpt-3.5-turbo';

        if (!$apiKey || !str_starts_with(trim($apiKey), 'sk-')) {
            return response()->json([
                'success' => false,
                'message' => __('OpenAI API key not configured. Go to Settings → Integrations → ChatGPT to add your key.'),
            ], 422);
        }

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

        try {
            $client   = OpenAI::client(trim($apiKey));
            $response = $client->chat()->create([
                'model'    => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
            ]);

            $content = trim($response->choices[0]->message->content);
            // Strip markdown code fences if present
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $plan = json_decode($content, true);
            if (!$plan || !isset($plan['title'], $plan['tasks'])) {
                throw new \Exception('Invalid AI response format');
            }

            return response()->json(['success' => true, 'plan' => $plan]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('AI parsing failed: :msg', ['msg' => $e->getMessage()]),
            ], 500);
        }
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
