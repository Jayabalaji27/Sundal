<?php

namespace App\Services\Ai\Tools;

use App\Actions\Projects\CreateMilestone;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Adds a milestone to a project, as the project's Milestones tab does. */
class CreateMilestoneTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CreateMilestone $createMilestone,
    ) {}

    public function name(): string
    {
        return 'create_milestone';
    }

    public function description(): string
    {
        return 'Add a milestone (a key date or deliverable, e.g. "Beta release") to a project. Shows the user a confirmation card; nothing is created until they confirm.';
    }

    public function permissions(): array
    {
        return ['project_manage_milestones'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'title' => ['type' => 'string', 'required' => true, 'description' => 'The milestone name only. At most 255 characters.'],
            'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
            'description' => ['type' => 'string', 'description' => 'What the milestone delivers.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Add a milestone');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('project', __('Project'), 'project', required: true),
            new FormField('title', __('Title'), 'text', required: true, question: __('What is the milestone called?')),
            new FormField('due_date', __('Due date'), 'date'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ToolInputException(__('The milestone needs a title of at most 255 characters.'));
        }
        if ($project->milestones()->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])->exists()) {
            throw new ToolInputException(__('Project :project already has a milestone called ":title". Ask the user whether they meant that one.', ['project' => $project->title, 'title' => $title]));
        }
        $due = $this->resolver->date($args['due_date'] ?? null, __('Due date'));
        $description = isset($args['description']) && trim((string) $args['description']) !== '' ? mb_substr(trim((string) $args['description']), 0, 5000) : null;

        return new PreparedAction(
            summary: __('Add milestone ":title" to :project', ['title' => $title, 'project' => $project->title]),
            details: array_filter([
                __('Project') => $project->title,
                __('Title') => $title,
                __('Due') => $due,
            ]),
            payload: ['project_id' => $project->id, 'title' => $title, 'due_date' => $due, 'description' => $description],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));

        $milestone = $this->createMilestone->handle($user, $project, [
            'title' => $payload['title'],
            'due_date' => $payload['due_date'],
            'description' => $payload['description'],
        ]);

        return new ToolOutcome(
            __('Added milestone ":title" to :project.', ['title' => $milestone->title, 'project' => $project->title]),
            $milestone,
            route('projects.show', ['project' => $project->id, 'tab' => 'milestones'], false),
            ['milestone_id' => $milestone->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var ProjectMilestone $milestone */
        $milestone = $this->resolver->byId($this->resolver->milestones($user), (int) $undo['milestone_id'], __('milestone'));
        $milestone->delete();
        $milestone->unsetRelation('project');
        $milestone->project->updateProgressFromMilestones();
        $milestone->project->logActivity('milestone_deleted', "Milestone '{$milestone->title}' was deleted");

        return __('Milestone ":title" removed.', ['title' => $milestone->title]);
    }
}
