<?php

namespace App\Services\Ai\Tools;

use App\Actions\Tasks\CreateTask;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A task list or user-story sheet (Excel or CSV) → one task per row, through CreateTask. */
class ImportTasksFromSheet extends ImportFromSheet
{
    public function __construct(RecordResolver $resolver, private readonly CreateTask $createTask)
    {
        parent::__construct($resolver);
    }

    public function name(): string
    {
        return 'import_tasks_from_sheet';
    }

    public function description(): string
    {
        return 'Create tasks from the rows of an attached spreadsheet (a task list or user stories), one task per row. Sundal reads every row and matches the columns itself; '
            . 'the user checks a table of the rows on the card (project, columns, assignees) and confirms. Use this instead of creating tasks one by one.';
    }

    public function permissions(): array
    {
        return ['task_create'];
    }

    protected function kind(): string
    {
        return 'tasks';
    }

    protected function fields(): array
    {
        return [
            'title' => ['label' => __('Title'), 'synonyms' => ['title', 'task', 'task name', 'summary', 'user story', 'story', 'name', 'subject', 'item']],
            'description' => ['label' => __('Description'), 'synonyms' => ['description', 'details', 'acceptance criteria', 'notes', 'comments']],
            'priority' => ['label' => __('Priority'), 'synonyms' => ['priority', 'prio', 'pri']],
            'assignee' => ['label' => __('Assignee'), 'synonyms' => ['assigned to', 'assignee', 'assigned', 'owner', 'developer', 'responsible']],
            'start_date' => ['label' => __('Start date'), 'synonyms' => ['start date', 'start', 'begin']],
            'due_date' => ['label' => __('Due date'), 'synonyms' => ['due date', 'due', 'deadline', 'end date', 'target date']],
        ];
    }

    protected function canAssign(User $user): bool
    {
        return $user->hasWorkspacePermission('task_assign_users');
    }

    protected function records(User $user): Builder
    {
        return $this->resolver->tasks($user);
    }

    protected function create(User $user, Project $project, array $row): Model
    {
        return $this->createTask->handle($user, [...$row, 'project_id' => $project->id]);
    }

    protected function link(Model $record): string
    {
        return route('tasks.show', $record->getKey(), false);
    }
}
