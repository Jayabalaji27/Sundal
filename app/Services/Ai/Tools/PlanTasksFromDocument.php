<?php

namespace App\Services\Ai\Tools;

use App\Actions\ActionException;
use App\Actions\Projects\CreateMilestone;
use App\Actions\Projects\CreateProject;
use App\Actions\Tasks\CreateTask;
use App\Models\AiAttachment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Services\Ai\Attachments\DocumentAnalyzer;
use App\Services\Ai\Attachments\ScannedPdfReader;

/**
 * A BRD, spec or user-story document → milestones (epics) and tasks
 * (stories with acceptance criteria), in an existing or a new project.
 * The document is read once by DocumentAnalyzer; the card is an editable
 * list (project, include, title, priority). Nothing is created until
 * Confirm; more than 10 tasks need typed confirmation.
 */
class PlanTasksFromDocument extends AiTool implements EditableCard
{
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly DocumentAnalyzer $analyzer,
        private readonly ScannedPdfReader $scans,
        private readonly CreateProject $createProject,
        private readonly CreateMilestone $createMilestone,
        private readonly CreateTask $createTask,
    ) {}

    public function name(): string
    {
        return 'plan_tasks_from_document';
    }

    public function description(): string
    {
        return 'Turn an attached requirements document (BRD, spec, user stories; PDF, Word or text) into milestones and tasks with acceptance criteria. '
            . 'Sundal reads the whole document; the user checks and edits the plan on the card and confirms. Use this instead of creating tasks one by one.';
    }

    public function permissions(): array
    {
        return ['task_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'attachment' => ['type' => 'string', 'description' => 'The document: its id (e.g. #12) or name. Defaults to the latest attached file.'],
            'project' => ['type' => 'string', 'description' => 'Existing project title or id. Leave out if the user did not say.'],
            'new_project' => ['type' => 'string', 'description' => 'Only if the user asked for a new project: its name.'],
            'focus' => ['type' => 'string', 'description' => 'Only if the user asked to plan part of the document, e.g. "the reporting module".'],
        ];
    }

    public function edit(array $args, array $changes, User $user): array
    {
        if (array_key_exists('project', $changes)) {
            $args['project'] = $changes['project'] ? '#' . (int) $changes['project'] : '';
            $args['new_project'] = '';
        }
        if (array_key_exists('new_project', $changes)) {
            $args['new_project'] = trim((string) $changes['new_project']);
            if ($args['new_project'] !== '') {
                $args['project'] = '';
            }
        }
        if (array_key_exists('all', $changes)) {
            $args['row_edits'] = collect($args['row_edits'] ?? [])->map(fn ($e) => array_diff_key($e, ['include' => 1]))->all();
            $args['include_all'] = (bool) $changes['all'];
        }
        foreach ($changes['rows'] ?? [] as $row) {
            $n = (int) $row['n'];
            $args['row_edits'][$n] = [...($args['row_edits'][$n] ?? []), ...array_intersect_key($row, array_flip(['include', 'title', 'priority']))];
        }

        return $args;
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $file = $this->resolver->attachment($user, (string) ($args['attachment'] ?? ''));
        if ($file->kind === AiAttachment::SPREADSHEET) {
            throw new ToolInputException(__('":file" is a spreadsheet. Use import_tasks_from_sheet or import_bugs_from_sheet.', ['file' => $file->original_name]));
        }
        if ($file->status === AiAttachment::NEEDS_OCR) {
            $file = $this->scans->read($file, $user);
        }

        $plan = $this->analyzer->plan($file, $user, trim((string) ($args['focus'] ?? '')));

        $newProject = mb_substr(trim((string) ($args['new_project'] ?? '')), 0, 120);
        // A new project needs the permission and room in the plan; else pick an existing one.
        $projectBlocked = null;
        try {
            $this->createProject->ensureAllowed($user);
        } catch (ActionException $e) {
            $projectBlocked = $e->getMessage();
        }
        $canCreateProject = $user->hasWorkspacePermission('project_create') && $projectBlocked === null;
        $refused = $newProject !== '' && !$canCreateProject
            ? ($projectBlocked ?? __('You may not create projects.')) . ' ' . __('Choose an existing project instead.')
            : null;
        if (!$canCreateProject) {
            $newProject = '';
        }
        $project = $newProject === '' ? $this->project($user, (string) ($args['project'] ?? '')) : null;
        $useMilestones = $user->hasWorkspacePermission('project_manage_milestones');
        $milestoneTitles = collect($plan['milestones'])->pluck('title', 'key');

        $rows = [];
        foreach ($plan['tasks'] as $i => $task) {
            $n = $i + 1;
            $edit = $args['row_edits'][$n] ?? [];
            $title = mb_substr(trim((string) ($edit['title'] ?? $task['title'])), 0, 255);
            $priority = in_array($edit['priority'] ?? null, self::PRIORITIES, true) ? $edit['priority'] : $task['priority'];
            $include = $title !== '' && (bool) ($edit['include'] ?? ($args['include_all'] ?? true));

            $rows[] = [
                'n' => $n,
                'include' => $include,
                'title' => $title,
                'priority' => $priority,
                'milestone' => $task['milestone'] ? $milestoneTitles[$task['milestone']] ?? null : null,
                'milestone_key' => $task['milestone'],
                'description' => $task['description'],
                'source' => $task['source'],
            ];
        }

        $included = array_values(array_filter($rows, fn ($r) => $r['include']));
        $usedMilestones = $useMilestones
            ? collect($plan['milestones'])->whereIn('key', array_unique(array_filter(array_column($included, 'milestone_key'))))->values()->all()
            : [];
        $count = count($included);
        $where = $newProject !== '' ? __('new project ":name"', ['name' => $newProject]) : ($project?->title ?? null);

        $problem = match (true) {
            $refused !== null && $project === null => $refused,
            $project === null && $newProject === '' => $canCreateProject ? __('Choose a project, or name a new one.') : __('Choose the project.'),
            $count === 0 => __('No tasks are ticked.'),
            default => null,
        };

        $items = trans_choice(':count task|:count tasks', $count);
        $groups = $usedMilestones ? ' ' . trans_choice('in :count milestone|in :count milestones', count($usedMilestones)) : '';

        return new PreparedAction(
            summary: $where
                ? __('Create :items:groups in :where from :file', ['items' => $items, 'groups' => $groups, 'where' => $where, 'file' => $file->original_name])
                : __('Plan :items:groups from :file', ['items' => $items, 'groups' => $groups, 'file' => $file->original_name]),
            details: array_filter([
                __('File') => $file->original_name,
                __('Read') => __(':read of :total sections, about :tokens tokens', [
                    'read' => $plan['sections_read'], 'total' => $plan['sections_total'], 'tokens' => number_format($plan['tokens']),
                ]),
                __('Project') => $where,
                __('Tasks') => __(':included of :total ticked', ['included' => $count, 'total' => count($rows)]),
                __('Milestones') => $usedMilestones ? implode(', ', array_column($usedMilestones, 'title')) : null,
            ]),
            payload: [
                'file' => $file->original_name,
                'project_id' => $project?->id,
                'new_project' => $newProject !== '' ? $newProject : null,
                'milestones' => $usedMilestones,
                'tasks' => $problem ? [] : array_map(fn ($r) => [
                    'title' => $r['title'],
                    'description' => trim($r['description'] . "\n\n" . __('From :file, :source.', ['file' => $file->original_name, 'source' => $r['source']])),
                    'priority' => $r['priority'],
                    'milestone' => $useMilestones ? $r['milestone_key'] : null,
                ], $included),
            ],
            confirmPhrase: $count > PreparedAction::BULK_TYPED_CONFIRM_OVER ? 'CREATE ' . $count : null,
            view: [
                'type' => 'plan',
                'file' => $file->original_name,
                'project' => $project ? ['value' => (string) $project->id, 'label' => $project->title] : null,
                'projects' => $this->resolver->projects($user)->orderBy('title')->limit(200)->get(['id', 'title'])
                    ->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->title])->all(),
                'new_project' => $newProject !== '' ? $newProject : null,
                'can_create_project' => $canCreateProject,
                'uses_milestones' => $useMilestones,
                'priorities' => self::PRIORITIES,
                'rows' => array_map(fn ($r) => array_diff_key($r, ['milestone_key' => 1]), $rows),
                'included' => $count,
                'sections_read' => $plan['sections_read'],
                'sections_total' => $plan['sections_total'],
                'skipped' => $plan['skipped'],
                'tokens' => $plan['tokens'],
                'problem' => $problem,
            ],
        );
    }

    private function project(User $user, string $ref): ?Project
    {
        if (trim($ref) === '') {
            return null;
        }
        try {
            return $this->resolver->project($user, $ref);
        } catch (ToolInputException) {
            return null; // picked on the card
        }
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        if (empty($payload['tasks']) || (empty($payload['project_id']) && empty($payload['new_project']))) {
            throw new ToolInputException(__('Choose the project and at least one task on the card first.'));
        }

        $created = ['project' => null, 'milestones' => [], 'tasks' => []];
        if (!empty($payload['new_project'])) {
            $project = $this->createProject->handle($user, [
                'title' => $payload['new_project'],
                'description' => __('Planned from :file.', ['file' => $payload['file'] ?? '']),
                'status' => 'planning',
                'priority' => 'medium',
            ]);
            $created['project'] = $project->id;
        } else {
            $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        }

        $milestoneIds = [];
        foreach ($payload['milestones'] ?? [] as $milestone) {
            $milestoneIds[$milestone['key']] = $this->createMilestone->handle($user, $project, ['title' => $milestone['title']])->id;
        }
        $created['milestones'] = array_values($milestoneIds);

        foreach ($payload['tasks'] as $task) {
            $created['tasks'][] = $this->createTask->handle($user, [
                'project_id' => $project->id,
                'milestone_id' => $milestoneIds[$task['milestone'] ?? ''] ?? null,
                'title' => $task['title'],
                'description' => $task['description'],
                'priority' => $task['priority'],
            ])->id;
        }

        return new ToolOutcome(
            __('Created :tasks:milestones in :project from :file.', [
                'tasks' => trans_choice(':count task|:count tasks', count($created['tasks'])),
                'milestones' => $created['milestones'] ? ' ' . trans_choice('and :count milestone|and :count milestones', count($created['milestones'])) : '',
                'project' => $project->title,
                'file' => $payload['file'] ?? '',
            ]),
            $project,
            route('projects.show', $project->id, false),
            $created,
        );
    }

    /** Removes the tasks nobody changed since, then the milestones left empty. A new project is kept. */
    public function undo(array $undo, User $user): string
    {
        $removed = 0;
        foreach ($this->resolver->tasks($user)->whereKey($undo['tasks'] ?? [])->get() as $task) {
            if ($task->updated_at?->equalTo($task->created_at)) {
                $task->delete();
                $removed++;
            }
        }
        foreach (ProjectMilestone::whereKey($undo['milestones'] ?? [])->withCount('tasks')->get() as $milestone) {
            if ($milestone->tasks_count === 0) {
                $milestone->delete();
            }
        }
        $kept = count($undo['tasks'] ?? []) - $removed;

        return trim(implode(' ', array_filter([
            $kept > 0
                ? __('Removed :removed tasks; :kept were changed since, so they were kept.', ['removed' => $removed, 'kept' => $kept])
                : __('Removed all :removed tasks and their empty milestones.', ['removed' => $removed]),
            !empty($undo['project']) ? __('The new project was kept; delete it from Projects if you do not need it.') : null,
        ])));
    }
}
