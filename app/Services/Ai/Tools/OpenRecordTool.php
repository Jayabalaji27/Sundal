<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

class OpenRecordTool extends UiTool
{
    /** Type => [route name, permission]. */
    private const TYPES = [
        'project' => ['projects.show', 'project_view'],
        'task' => ['tasks.show', 'task_view'],
        'bug' => ['tasks.show', 'bug_view'], // bugs are tasks of type bug
    ];

    /** Project tab => [route name, permission(s), passes the tab as ?tab=]. */
    private const PROJECT_TABS = [
        'backlog' => ['projects.backlog', ['sprint_view_any'], false],
        'sprints' => ['sprints.index', ['sprint_view_any'], false],
        'epics' => ['epics.index', ['sprint_view_any'], false],
        'milestones' => ['projects.show', ['project_view'], true],
        'board' => ['projects.board', ['task_view_any'], false],
        'timesheet' => ['projects.show', ['timesheet_view_any'], true],
        'meetings' => ['projects.meetings', ['zoom_meeting_view_any', 'google_meeting_view_any'], false],
        'expenses' => ['projects.show', ['expense_view_any'], true],
        'timeline' => ['projects.show', ['task_view_any'], true],
        'budget' => ['projects.show', ['project_manage_budget'], true],
        'team' => ['projects.show', ['project_assign_members'], true],
        'files' => ['projects.show', ['project_manage_attachments'], true],
        'activity' => ['projects.show', ['task_view_any'], true],
    ];

    /** The project page's own name for a tab (?tab=). */
    private const TAB_KEYS = ['timesheet' => 'timesheet', 'expenses' => 'expense', 'timeline' => 'gantt', 'files' => 'attachments'];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'open_record';
    }

    public function description(): string
    {
        return 'Open one project, task or bug on the user\'s screen, e.g. "open the login bug", "take me to Website Redesign". For a project, tab opens one of its pages, e.g. "show the BrightCart board" or "open the epics of Mobile App".';
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
            'tab' => ['type' => 'enum', 'options' => array_keys(self::PROJECT_TABS), 'description' => 'Projects only: which page of the project. Leave out for its summary.'],
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

        $tab = $type === 'project' ? ($args['tab'] ?? null) : null;
        if ($tab) {
            [$tabRoute, $tabPermissions, $asQuery] = self::PROJECT_TABS[$tab] ?? throw new ToolInputException(__('Unknown project tab ":tab".', ['tab' => $tab]));
            if (!collect($tabPermissions)->contains(fn ($p) => $user->hasWorkspacePermission($p))) {
                throw new ToolInputException(__('The user is not allowed to open the :tab of a project.', ['tab' => $tab]));
            }
            $url = $asQuery
                ? route($tabRoute, ['project' => $record->id, 'tab' => self::TAB_KEYS[$tab] ?? $tab], false)
                : route($tabRoute, $record->id, false);

            return ['url' => $url, 'label' => $record->title . ' · ' . __(ucfirst($tab))];
        }

        return ['url' => route($route, $record->id, false), 'label' => $record->title];
    }
}
