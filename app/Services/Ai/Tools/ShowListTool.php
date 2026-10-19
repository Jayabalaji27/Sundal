<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

/**
 * Opens the task, bug or project list with filters set, using the same
 * query parameters as the filters on those pages. Names become ids here,
 * through the same lookups as the read tools.
 */
class ShowListTool extends UiTool
{
    /** List => [route name, permission, label]. */
    private const LISTS = [
        'tasks' => ['tasks.index', 'task_view_any', 'Tasks'],
        'bugs' => ['tasks.index', 'bug_view_any', 'Bugs'], // the Tasks list, type=bug
        'projects' => ['projects.index', 'project_view_any', 'Projects'],
    ];

    private const PRIORITIES = [
        'tasks' => ['low', 'medium', 'high', 'critical'],
        'bugs' => ['low', 'medium', 'high', 'critical'],
        'projects' => ['low', 'medium', 'high', 'urgent'],
    ];

    private const SEVERITIES = ['minor', 'major', 'critical', 'blocker'];

    private const PROJECT_STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'show_list';
    }

    public function description(): string
    {
        return 'Show the task, bug or project list on the user\'s screen with filters set, e.g. "show high priority bugs in Mobile App", "my tasks in progress". Pass only the filters the user said.';
    }

    public function permissions(): array
    {
        return ['task_view_any', 'bug_view_any', 'project_view_any'];
    }

    public function parameters(): array
    {
        return [
            'list' => ['type' => 'enum', 'options' => array_keys(self::LISTS), 'required' => true, 'description' => 'Which list to show.'],
            'project' => ['type' => 'string', 'description' => 'Tasks and bugs: project title or id.'],
            'assignee' => ['type' => 'string', 'description' => 'Tasks and bugs: "me" or a person\'s name or email.'],
            'stage' => ['type' => 'string', 'description' => 'Tasks and bugs: stage name (e.g. In Progress). Projects: planning, active, on_hold, completed or cancelled.'],
            'priority' => ['type' => 'string', 'description' => 'low, medium, high, critical (projects: urgent instead of critical).'],
            'severity' => ['type' => 'enum', 'options' => self::SEVERITIES, 'description' => 'Bugs only.'],
            'search' => ['type' => 'string', 'description' => 'Part of the title.'],
            'view' => ['type' => 'enum', 'options' => ['kanban', 'list'], 'description' => 'Tasks and bugs: board or table. Leave out unless the user asked.'],
        ];
    }

    public function action(array $args, User $user): array
    {
        $list = $args['list'] ?? '';
        [$route, $permission, $label] = self::LISTS[$list] ?? throw new ToolInputException(__('Choose tasks, bugs or projects.'));

        if (!$user->hasWorkspacePermission($permission)) {
            throw new ToolInputException(__('The user is not allowed to open :page.', ['page' => $label]));
        }

        $query = $list === 'bugs' ? ['type' => 'bug'] : [];
        $shown = [];
        $value = fn (string $key) => trim((string) ($args[$key] ?? ''));

        if ($list !== 'projects') {
            if ($value('project') !== '') {
                $project = $this->resolver->project($user, $value('project'));
                $query['project_id'] = $project->id;
                $shown[] = $project->title;
            }
            if ($value('assignee') !== '') {
                $person = $this->resolver->member($user, $value('assignee'));
                $query['assigned_to'] = $person->id;
                $shown[] = $person->name;
            }
        }

        if ($value('stage') !== '' && $list !== 'projects') {
            $stage = $list === 'bugs' ? $this->resolver->bugStage($user, $value('stage')) : $this->resolver->taskStage($user, $value('stage'));
            $query['stage_id'] = $stage->id;
            $shown[] = $stage->name;
        } elseif ($value('stage') !== '') {
            $query['status'] = $this->option($value('stage'), self::PROJECT_STATUSES, __('status'));
            $shown[] = $query['status'];
        }

        if ($value('priority') !== '') {
            $query['priority'] = $this->option($value('priority'), self::PRIORITIES[$list], __('priority'));
            $shown[] = $query['priority'];
        }

        if ($list === 'bugs' && $value('severity') !== '') {
            $query['severity'] = $this->option($value('severity'), self::SEVERITIES, __('severity'));
            $shown[] = $query['severity'];
        }

        if ($value('search') !== '') {
            $query['search'] = mb_substr($value('search'), 0, 100);
            $shown[] = '"' . $query['search'] . '"';
        }

        if ($list !== 'projects' && in_array($args['view'] ?? null, ['kanban', 'list'], true)) {
            $query['view'] = $args['view'];
        }

        return [
            'url' => route($route, $query, false),
            'label' => $shown ? __(':list: :filters', ['list' => __($label), 'filters' => implode(', ', $shown)]) : __($label),
        ];
    }

    /** @param  string[]  $options */
    private function option(string $value, array $options, string $label): string
    {
        $key = str_replace(' ', '_', mb_strtolower($value));

        return in_array($key, $options, true)
            ? $key
            : throw new ToolInputException(__('Unknown :label ":value". Use one of: :options.', ['label' => $label, 'value' => $value, 'options' => implode(', ', $options)]));
    }
}
