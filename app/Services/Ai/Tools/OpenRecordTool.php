<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

class OpenRecordTool extends UiTool
{
    /** Type => [route name, permission]. */
    private const TYPES = [
        'project' => ['projects.show', 'project_view'],
        'task' => ['tasks.show', 'task_view'],
        'bug' => ['bugs.show', 'bug_view'],
    ];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'open_record';
    }

    public function description(): string
    {
        return 'Open one project, task or bug on the user\'s screen, e.g. "open the login bug", "take me to Website Redesign".';
    }

    public function permissions(): array
    {
        return ['project_view', 'task_view', 'bug_view'];
    }

    public function parameters(): array
    {
        return [
            'type' => ['type' => 'enum', 'options' => array_keys(self::TYPES), 'required' => true, 'description' => 'What to open.'],
            'ref' => ['type' => 'string', 'required' => true, 'description' => 'Its title, part of the title, or id.'],
            'project' => ['type' => 'string', 'description' => 'Tasks and bugs: the project, when the user named one.'],
        ];
    }

    public function action(array $args, User $user): array
    {
        $type = $args['type'] ?? '';
        [$route, $permission] = self::TYPES[$type] ?? throw new ToolInputException(__('Choose a project, task or bug.'));

        if (!$user->hasWorkspacePermission($permission)) {
            throw new ToolInputException(__('The user is not allowed to open this :type.', ['type' => __($type)]));
        }

        $ref = trim((string) ($args['ref'] ?? ''));
        if ($ref === '') {
            throw new ToolInputException(__('Ask the user which :type to open.', ['type' => __($type)]));
        }
        $project = trim((string) ($args['project'] ?? '')) ?: null;

        $record = match ($type) {
            'project' => $this->resolver->project($user, $ref),
            'task' => $this->resolver->task($user, $ref, $project),
            'bug' => $this->resolver->bug($user, $ref, $project),
        };

        return ['url' => route($route, $record->id, false), 'label' => $record->title];
    }
}
