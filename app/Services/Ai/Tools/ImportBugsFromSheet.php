<?php

namespace App\Services\Ai\Tools;

use App\Actions\Bugs\CreateBug;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A QA's bug list (Excel or CSV) → one bug per row, through CreateBug. */
class ImportBugsFromSheet extends ImportFromSheet
{
    public function __construct(RecordResolver $resolver, private readonly CreateBug $createBug)
    {
        parent::__construct($resolver);
    }

    public function name(): string
    {
        return 'import_bugs_from_sheet';
    }

    public function description(): string
    {
        return 'Create bugs from the rows of an attached spreadsheet (e.g. a QA bug list), one bug per row. Sundal reads every row and matches the columns itself; '
            . 'the user checks a table of the rows on the card (project, columns, assignees) and confirms. Use this instead of creating bugs one by one.';
    }

    public function permissions(): array
    {
        return ['bug_create'];
    }

    protected function kind(): string
    {
        return 'bugs';
    }

    protected function fields(): array
    {
        return [
            'title' => ['label' => __('Title'), 'synonyms' => ['summary', 'title', 'bug', 'bug title', 'bug summary', 'issue', 'issue title', 'defect', 'defect summary', 'subject', 'name']],
            'description' => ['label' => __('Description'), 'synonyms' => ['description', 'details', 'detail', 'bug description', 'comments', 'notes']],
            'steps_to_reproduce' => ['label' => __('Steps to reproduce'), 'synonyms' => ['steps to reproduce', 'steps', 'repro steps', 'reproduction steps', 'test steps']],
            'expected_behavior' => ['label' => __('Expected'), 'synonyms' => ['expected result', 'expected', 'expected behavior', 'expected behaviour']],
            'actual_behavior' => ['label' => __('Actual'), 'synonyms' => ['actual result', 'actual', 'actual behavior', 'actual behaviour']],
            'environment' => ['label' => __('Environment'), 'synonyms' => ['environment', 'env', 'browser', 'device', 'platform', 'os']],
            'severity' => ['label' => __('Severity'), 'synonyms' => ['severity', 'sev']],
            'priority' => ['label' => __('Priority'), 'synonyms' => ['priority', 'prio', 'pri']],
            'assignee' => ['label' => __('Assignee'), 'synonyms' => ['assigned to', 'assignee', 'assigned', 'developer', 'dev', 'owner', 'responsible']],
            'due_date' => ['label' => __('Due date'), 'synonyms' => ['due date', 'due', 'deadline', 'target date', 'fix by']],
        ];
    }

    protected function severities(): array
    {
        return ['minor', 'major', 'critical', 'blocker'];
    }

    protected function canAssign(User $user): bool
    {
        return $user->hasWorkspacePermission('bug_assign');
    }

    protected function records(User $user): Builder
    {
        return $this->resolver->bugs($user);
    }

    protected function create(User $user, Project $project, array $row): Model
    {
        return $this->createBug->handle($user, [...$row, 'project_id' => $project->id]);
    }

    protected function link(Model $record): string
    {
        return route('bugs.show', $record->getKey(), false);
    }
}
