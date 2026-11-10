<?php

namespace App\Services\Ai\Tools;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Changes a bug's details: the task fields plus severity and the bug report fields. */
class UpdateBugTool extends UpdateTaskTool
{
    public function name(): string
    {
        return 'update_bug';
    }

    public function description(): string
    {
        return 'Change a bug\'s title, description, severity, priority, due date, steps to reproduce, expected or actual behaviour, environment or resolution notes. Only the values given change. For the assignee use assign_bug, for the status change_bug_status. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['bug_update'];
    }

    public function parameters(): array
    {
        $task = parent::parameters();
        unset($task['task'], $task['story_points']);

        return [
            'bug' => ['type' => 'string', 'required' => true, 'description' => 'Bug title or id.'],
            ...$task,
            'severity' => ['type' => 'enum', 'options' => Task::SEVERITIES, 'description' => 'New severity.'],
            'steps_to_reproduce' => ['type' => 'string', 'description' => 'New steps to reproduce.'],
            'expected_behavior' => ['type' => 'string', 'description' => 'What should happen.'],
            'actual_behavior' => ['type' => 'string', 'description' => 'What happens instead.'],
            'environment' => ['type' => 'string', 'description' => 'Browser, device, version…'],
            'resolution_notes' => ['type' => 'string', 'description' => 'How it was fixed.'],
        ];
    }

    protected function records(User $user): Builder
    {
        return $this->resolver->bugs($user);
    }

    protected function find(User $user, array $args): Task
    {
        return $this->resolver->bug($user, (string) ($args['bug'] ?? ''), $args['project'] ?? null);
    }

    protected function kind(): string
    {
        return __('Bug');
    }

    protected function extraChanges(array $args, Task $task): array
    {
        $text = fn (string $key, int $max) => isset($args[$key]) && trim((string) $args[$key]) !== '' ? mb_substr(trim((string) $args[$key]), 0, $max) : null;

        return [
            'severity' => in_array($args['severity'] ?? null, Task::SEVERITIES, true) ? $args['severity'] : null,
            'steps_to_reproduce' => $text('steps_to_reproduce', 10000),
            'expected_behavior' => $text('expected_behavior', 5000),
            'actual_behavior' => $text('actual_behavior', 5000),
            'environment' => $text('environment', 255),
            'resolution_notes' => $text('resolution_notes', 5000),
        ];
    }
}
