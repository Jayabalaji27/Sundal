<?php

namespace App\Imports;

use App\Actions\Tasks\CreateTask;
use App\Imports\Concerns\InterpretsSheetValues;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\TaskStage;
use App\Models\User;
use App\Services\Ai\Tools\RecordResolver;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Tasks from the rows of an Excel/CSV file (Tasks page > Import). Creates new
 * tasks only: a title already in the project, or repeated in the file, is
 * skipped. Nobody is notified, so a big import doesn't send an email per row.
 * One bad row never stops the others; each problem is reported by row.
 */
class TaskImport
{
    use InterpretsSheetValues;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CreateTask $createTask,
    ) {
    }

    /** @return array<string, array{label: string, synonyms: string[]}> the fields a column can fill */
    public function fields(): array
    {
        return [
            'project' => ['label' => __('Project'), 'synonyms' => ['project', 'project name', 'project title']],
            'title' => ['label' => __('Title'), 'synonyms' => ['title', 'task', 'task name', 'summary', 'user story', 'story', 'name', 'subject', 'item']],
            'description' => ['label' => __('Description'), 'synonyms' => ['description', 'details', 'acceptance criteria', 'notes', 'comments']],
            'type' => ['label' => __('Type'), 'synonyms' => ['type', 'work type', 'issue type', 'kind']],
            'priority' => ['label' => __('Priority'), 'synonyms' => ['priority', 'prio', 'pri']],
            'severity' => ['label' => __('Severity'), 'synonyms' => ['severity', 'sev']],
            'stage' => ['label' => __('Stage'), 'synonyms' => ['stage', 'status', 'state', 'column']],
            'assignee' => ['label' => __('Assignee'), 'synonyms' => ['assignee', 'assigned to', 'assignee email', 'assigned', 'owner', 'developer', 'responsible']],
            'start_date' => ['label' => __('Start date'), 'synonyms' => ['start date', 'start', 'begin']],
            'due_date' => ['label' => __('Due date'), 'synonyms' => ['due date', 'due', 'deadline', 'end date', 'target date']],
            'story_points' => ['label' => __('Story points'), 'synonyms' => ['story points', 'points', 'sp', 'estimate']],
            'milestone' => ['label' => __('Milestone'), 'synonyms' => ['milestone', 'phase']],
        ];
    }

    /**
     * Which column fills each field, from the column names: the field's own
     * name or a known synonym ("Due", "Deadline" → due date).
     *
     * @return array<string, int> field => column index
     */
    public function suggestMapping(array $headers): array
    {
        $byKey = [];
        foreach ($headers as $index => $header) {
            $byKey[$this->key((string) $header)] ??= $index;
        }

        $mapping = [];
        foreach ($this->fields() as $field => $spec) {
            foreach ([$field, ...$spec['synonyms']] as $name) {
                $index = $byKey[$this->key($name)] ?? null;
                if ($index !== null && !in_array($index, $mapping, true)) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  data rows (the header row removed)
     * @param  array<string, int>  $mapping  field => column index
     * @return array{imported: int, skipped: int, errors: string[], warnings: string[]}
     */
    public function import(User $user, array $rows, array $mapping, ?int $defaultProjectId = null): array
    {
        $canAssign = $user->hasWorkspacePermission('task_assign_users');
        $members = $canAssign ? $this->resolver->members($user)->get(['id', 'name', 'email']) : collect();
        $stages = TaskStage::forWorkspace($user->current_workspace_id)->ordered()->get(['id', 'name']);
        $defaultProject = $defaultProjectId ? $this->resolver->projects($user)->whereKey($defaultProjectId)->first() : null;

        $result = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];
        $projects = [];   // cell text => Project|string (the error)
        $titles = [];     // project id => [lowercase title => true]

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // row 1 is the header
            $cell = fn (string $field) => isset($mapping[$field]) ? trim((string) ($row[$mapping[$field]] ?? '')) : '';
            if (collect($row)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $problems = [];
            try {
                $title = mb_substr($cell('title'), 0, 255);
                if ($title === '') {
                    $result['errors'][] = $this->rowMessage($rowNumber, __('No title'));
                    continue;
                }

                $projectRef = $cell('project');
                $project = $projectRef !== ''
                    ? ($projects[$projectRef] ??= $this->project($user, $projectRef))
                    : ($defaultProject ?? __('No project: add a Project column, or filter the Tasks page by a project before importing'));
                if (!$project instanceof Project) {
                    $result['errors'][] = $this->rowMessage($rowNumber, $project);
                    continue;
                }

                $titles[$project->id] ??= Task::where('project_id', $project->id)->pluck('title')
                    ->map(fn ($t) => mb_strtolower(trim($t)))->flip()->all();
                $titleKey = mb_strtolower($title);
                if (isset($titles[$project->id][$titleKey])) {
                    $result['skipped']++;
                    continue;
                }

                $type = $this->type($cell('type'), $problems);
                $start = $this->sheetDate($cell('start_date'), $problems);
                $due = $this->sheetDate($cell('due_date'), $problems);
                if ($start && $due && $due < $start) {
                    $problems[] = __('The due date is before the start date; the start date is left empty');
                    $start = null;
                }

                $values = [
                    'project_id' => $project->id,
                    'title' => $title,
                    'description' => mb_substr($cell('description'), 0, 10000),
                    'type' => $type,
                    'priority' => $this->choice($cell('priority'), ['low', 'medium', 'high', 'critical'], self::PRIORITY_WORDS, 'medium', __('priority'), $problems),
                    'severity' => $type === 'bug' ? $this->choice($cell('severity'), Task::SEVERITIES, self::SEVERITY_WORDS, 'major', __('severity'), $problems) : null,
                    'task_stage_id' => $this->stage($cell('stage'), $stages, $problems),
                    'assigned_to' => $this->assignee($cell('assignee'), $canAssign, $members, $problems),
                    'start_date' => $start,
                    'end_date' => $due,
                    'story_points' => $this->storyPoints($cell('story_points'), $problems),
                    'milestone_id' => $this->milestone($cell('milestone'), $project, $problems),
                ];

                $this->createTask->handle($user, array_filter($values, fn ($v) => $v !== null && $v !== ''), notify: false);
                $titles[$project->id][$titleKey] = true;
                $result['imported']++;
            } catch (\Throwable $e) {
                report($e);
                $result['errors'][] = $this->rowMessage($rowNumber, __('could not be saved'));
                continue;
            }

            foreach ($problems as $problem) {
                $result['warnings'][] = $this->rowMessage($rowNumber, $problem);
            }
        }

        return $result;
    }

    private function rowMessage(int $row, string $message): string
    {
        return __('Row :row: :message', ['row' => $row, 'message' => $message]);
    }

    /** A project the user can see, by id or exact title; otherwise the reason it can't be used. */
    private function project(User $user, string $ref): Project|string
    {
        $visible = $this->resolver->projects($user);
        if (preg_match('/^#?(\d+)$/', $ref, $m) && ($byId = (clone $visible)->whereKey((int) $m[1])->first())) {
            return $byId;
        }

        $matches = (clone $visible)->whereRaw('LOWER(title) = ?', [mb_strtolower($ref)])->limit(2)->get();

        return match ($matches->count()) {
            1 => $matches->first(),
            0 => __('":project" is not a project you can see', ['project' => $ref]),
            default => __('More than one project is called ":project"; use its ID instead', ['project' => $ref]),
        };
    }

    private function type(string $value, array &$problems): string
    {
        $key = $this->key($value);
        if (in_array($key, ['bug', 'defect'], true)) {
            return 'bug';
        }
        if (!in_array($key, ['', 'task', 'story', 'userstory', 'feature', 'chore'], true)) {
            $problems[] = __('":value" is not a known type; Task is used', ['value' => $value]);
        }

        return 'task';
    }

    private function stage(string $value, $stages, array &$problems): ?int
    {
        if ($value === '') {
            return null; // the first stage
        }
        $stage = $stages->first(fn ($s) => $this->key($s->name) === $this->key($value));
        if (!$stage) {
            $problems[] = __('":value" is not a stage; the task starts in :stage', ['value' => $value, 'stage' => $stages->first()?->name ?? __('the first stage')]);
        }

        return $stage?->id;
    }

    private function assignee(string $value, bool $canAssign, $members, array &$problems): ?int
    {
        if ($value === '' || $this->key($value) === 'unassigned') {
            return null;
        }
        if (!$canAssign) {
            $problems[] = __('You may not assign people; left unassigned');

            return null;
        }

        return $this->matchMember($value, $members, $problems)?->id;
    }

    private function storyPoints(string $value, array &$problems): ?int
    {
        if ($value === '') {
            return null;
        }
        if (is_numeric($value) && (int) $value == $value && $value >= 0 && $value <= 100) {
            return (int) $value;
        }
        $problems[] = __('":value" is not a story point number (0-100); left empty', ['value' => $value]);

        return null;
    }

    private function milestone(string $value, Project $project, array &$problems): ?int
    {
        if ($value === '') {
            return null;
        }
        $id = ProjectMilestone::where('project_id', $project->id)->whereRaw('LOWER(title) = ?', [mb_strtolower($value)])->value('id');
        if (!$id) {
            $problems[] = __('":value" is not a milestone of :project; left empty', ['value' => $value, 'project' => $project->title]);
        }

        return $id;
    }

    /** Excel stores dates as day numbers (46310 = 2026-10-15); text dates go through date(). */
    private function sheetDate(string $value, array &$problems): ?string
    {
        if (is_numeric($value) && $value > 20000 && $value < 80000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return $this->date($value, $problems);
    }
}
