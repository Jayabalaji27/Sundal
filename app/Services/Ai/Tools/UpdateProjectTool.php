<?php

namespace App\Services\Ai\Tools;

use App\Models\Project;
use App\Models\User;

/** Changes a project's details, status or dates, as the project's Edit form does. */
class UpdateProjectTool extends AiTool
{
    use RevertsChanges;

    private const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'update_project';
    }

    public function description(): string
    {
        return 'Change a project: rename it, change its description, status (planning, active, on hold, completed, cancelled), priority, start date, deadline or estimated hours. Only the values given change. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['project_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'title' => ['type' => 'string', 'description' => 'New title.'],
            'description' => ['type' => 'string', 'description' => 'New description (replaces the old one).'],
            'status' => ['type' => 'enum', 'options' => self::STATUSES, 'description' => 'New status.'],
            'priority' => ['type' => 'enum', 'options' => self::PRIORITIES, 'description' => 'New priority.'],
            'start_date' => ['type' => 'string', 'description' => 'New start date, YYYY-MM-DD.'],
            'deadline' => ['type' => 'string', 'description' => 'New deadline, YYYY-MM-DD, after the start date.'],
            'estimated_hours' => ['type' => 'number', 'description' => 'New estimate in hours.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));

        $changes = [
            'title' => isset($args['title']) && trim((string) $args['title']) !== '' ? trim((string) $args['title']) : null,
            'description' => isset($args['description']) && trim((string) $args['description']) !== '' ? mb_substr(trim((string) $args['description']), 0, 10000) : null,
            'status' => in_array($args['status'] ?? null, self::STATUSES, true) ? $args['status'] : null,
            'priority' => in_array($args['priority'] ?? null, self::PRIORITIES, true) ? $args['priority'] : null,
            'start_date' => $this->resolver->date($args['start_date'] ?? null, __('Start date')),
            'deadline' => $this->resolver->date($args['deadline'] ?? null, __('Deadline')),
            'estimated_hours' => isset($args['estimated_hours']) && $args['estimated_hours'] !== '' ? (int) $this->resolver->number($args['estimated_hours'], __('Estimated hours'), 1_000_000) : null,
        ];
        if (isset($changes['title']) && mb_strlen($changes['title']) > 255) {
            throw new ToolInputException(__('The project needs a title of at most 255 characters.'));
        }

        $old = $this->snapshot($project, array_keys($changes));
        $changes = array_filter($changes, fn ($value, $field) => $value !== null && (string) $value !== (string) ($old[$field] ?? ''), ARRAY_FILTER_USE_BOTH);
        if ($changes === []) {
            throw new ToolInputException(__('What should change on project ":title"? Ask the user.', ['title' => $project->title]));
        }

        $current = $this->snapshot($project, ['start_date', 'deadline']);
        $start = $changes['start_date'] ?? $current['start_date'];
        $deadline = $changes['deadline'] ?? $current['deadline'];
        if ($start && $deadline && $deadline <= $start) {
            throw new ToolInputException(__('The deadline must be after the start date.'));
        }

        return new PreparedAction(
            summary: __('Change project ":title"', ['title' => $project->title]),
            details: [
                __('Project') => $project->title,
                ...collect($changes)->mapWithKeys(fn ($value, $field) => [
                    __(ucfirst(str_replace('_', ' ', $field))) => $field === 'description'
                        ? __('new text: :text', ['text' => mb_strimwidth((string) $value, 0, 80, '…')])
                        : str_replace('_', ' ', ($old[$field] ?? '—') . ' → ' . $value),
                ])->all(),
            ],
            payload: ['project_id' => $project->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var Project $project */
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        $before = $this->snapshot($project, array_keys($payload['changes']));

        $this->apply($user, $project, $payload['changes']);

        return new ToolOutcome(
            __('Updated project ":title".', ['title' => $project->title]),
            $project,
            route('projects.show', $project->id, false),
            $this->changeUndo($project, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var Project $project */
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $undo['id'], __('project'));
        $this->ensureUnchanged($project, $undo, __('Project ":title"', ['title' => $project->title]));

        $this->apply($user, $project, $undo['before']);

        return __('Project ":title" put back.', ['title' => $project->title]);
    }

    private function apply(User $user, Project $project, array $changes): void
    {
        $project->update([...$changes, 'updated_by' => $user->id]);
        $project->logActivity('updated', "Project '{$project->title}' was updated", [], $user->id);
    }
}
