<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\Bugs\CreateBug;
use App\Models\User;

class CreateBugTool extends AiTool implements HasForm
{
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];
    private const SEVERITIES = ['minor', 'major', 'critical', 'blocker'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CreateBug $createBug,
    ) {}

    public function name(): string
    {
        return 'create_bug';
    }

    public function description(): string
    {
        return 'Report a bug in a project. Shows the user a confirmation card; nothing is created until they confirm.';
    }

    public function permissions(): array
    {
        return ['bug_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Short bug title, at most 255 characters.'],
            'description' => ['type' => 'string', 'description' => 'What is wrong.'],
            'severity' => ['type' => 'enum', 'options' => self::SEVERITIES, 'description' => 'Defaults to major.'],
            'priority' => ['type' => 'enum', 'options' => self::PRIORITIES, 'description' => 'Defaults to medium.'],
            'steps_to_reproduce' => ['type' => 'string', 'description' => 'Steps to reproduce.'],
            'expected_behavior' => ['type' => 'string', 'description' => 'What should happen.'],
            'actual_behavior' => ['type' => 'string', 'description' => 'What happens instead.'],
            'environment' => ['type' => 'string', 'description' => 'Browser, device or environment.'],
            'assignee' => ['type' => 'string', 'description' => '"me" or a person\'s name or email.'],
            'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Report a bug');
    }

    public function formFields(User $user): array
    {
        return array_values(array_filter([
            new FormField('title', __('Title'), 'text', required: true),
            new FormField('project', __('Project'), 'project', required: true),
            new FormField('severity', __('Severity'), 'enum', required: true, options: FormField::labels(self::SEVERITIES), mustChoose: true),
            new FormField('priority', __('Priority'), 'enum', required: true, options: FormField::labels(self::PRIORITIES), mustChoose: true),
            $user->hasWorkspacePermission('bug_assign')
                ? new FormField('assignee', __('Assignee'), 'member', required: true, allowNone: true)
                : null,
            new FormField('due_date', __('Due date'), 'date'),
        ]));
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ToolInputException(__('The bug needs a title of at most 255 characters.'));
        }

        $priority = $args['priority'] ?? 'medium';
        $severity = $args['severity'] ?? 'major';
        if (!in_array($priority, self::PRIORITIES, true) || !in_array($severity, self::SEVERITIES, true)) {
            throw new ToolInputException(__('Priority must be low, medium, high or critical; severity must be minor, major, critical or blocker.'));
        }

        $assignee = null;
        if (!empty($args['assignee'])) {
            if (!$user->hasWorkspacePermission('bug_assign')) {
                throw new ToolInputException(__('This user may report bugs but not assign them. Report it unassigned or ask the user.'));
            }
            $assignee = $this->resolver->member($user, (string) $args['assignee']);
        }

        $due = $this->resolver->date($args['due_date'] ?? null, __('Due date'));
        $text = fn (string $key) => isset($args[$key]) && trim((string) $args[$key]) !== '' ? trim((string) $args[$key]) : null;

        return new PreparedAction(
            summary: __('Report bug ":title" in :project', ['title' => $title, 'project' => $project->title]),
            details: array_filter([
                __('Project') => $project->title,
                __('Title') => $title,
                __('Severity') => ucfirst($severity),
                __('Priority') => ucfirst($priority),
                __('Assignee') => $assignee?->name ?? __('Unassigned'),
                __('Due') => $due,
            ]),
            payload: [
                'project_id' => $project->id,
                'title' => $title,
                'description' => $text('description'),
                'priority' => $priority,
                'severity' => $severity,
                'steps_to_reproduce' => $text('steps_to_reproduce'),
                'expected_behavior' => $text('expected_behavior'),
                'actual_behavior' => $text('actual_behavior'),
                'environment' => $text('environment'),
                'assigned_to' => $assignee?->id,
                'end_date' => $due,
            ],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        if (!empty($payload['assigned_to'])) {
            $this->resolver->byId($this->resolver->members($user), (int) $payload['assigned_to'], __('person'));
        }

        $bug = $this->createBug->handle($user, [...$payload, 'project_id' => $project->id]);

        return new ToolOutcome(
            __('Reported bug ":title" in :project.', ['title' => $bug->title, 'project' => $project->title]),
            $bug,
            route('bugs.show', $bug->id, false),
        );
    }
}
