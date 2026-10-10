<?php

namespace App\Services\Ai\Tools;

use App\Models\AiAttachment;
use App\Models\Project;
use App\Models\User;
use App\Services\Ai\Attachments\AttachmentContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Bugs or tasks from the rows of an attached spreadsheet (a QA's bug list).
 *
 * Sundal reads every row itself; the model only says which column holds
 * what (and it may leave that out: columns are matched by their names).
 * The card is a table the user can change in place: the project, the
 * columns, and each row's include, assignee, severity and priority.
 * Nothing is created until Confirm; more than 10 rows need typed confirmation.
 */
abstract class ImportFromSheet extends AiTool implements EditableCard
{
    public function __construct(protected readonly RecordResolver $resolver) {}

    /** 'bugs' or 'tasks'. */
    abstract protected function kind(): string;

    /** @return array<string, array{label: string, synonyms: string[]}> fields a column can fill (title first) */
    abstract protected function fields(): array;

    abstract protected function canAssign(User $user): bool;

    /** Records of this kind in a project (for duplicates and undo). */
    abstract protected function records(User $user): Builder;

    /** Create one record from a prepared row. */
    abstract protected function create(User $user, Project $project, array $row): Model;

    abstract protected function link(Model $record): string;

    /** @return string[] valid priorities */
    protected function priorities(): array
    {
        return ['low', 'medium', 'high', 'critical'];
    }

    /** @return string[] valid severities (bugs only) */
    protected function severities(): array
    {
        return [];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        $fields = implode(', ', array_keys($this->fields()));

        return [
            'attachment' => ['type' => 'string', 'description' => 'The spreadsheet: its id (e.g. #12) or name. Defaults to the latest attached file.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id the rows go into. Leave out if the user did not say; they pick it on the card.'],
            'sheet' => ['type' => 'string', 'description' => 'Sheet name. Defaults to the first sheet.'],
            'mapping' => ['type' => 'string', 'description' => "Only if a column name does not make its meaning clear: field=Column pairs separated by \";\", e.g. \"title=Summary; assignee=Dev\". Fields: {$fields}."],
        ];
    }

    // ── Card edits ───────────────────────────────────────────────────────────

    public function edit(array $args, array $changes, User $user): array
    {
        if (array_key_exists('project', $changes)) {
            $args['project'] = $changes['project'] ? '#' . (int) $changes['project'] : '';
        }
        if (isset($changes['sheet'])) {
            $args['sheet'] = (string) $changes['sheet'];
            $args['mapping'] = [];
            $args['row_edits'] = [];
        }
        if (isset($changes['mapping'])) {
            $args['mapping'] = [...$this->mappingArg($args['mapping'] ?? []), ...array_intersect_key($changes['mapping'], $this->fields())];
        }
        if (array_key_exists('all', $changes)) {
            // Tick or untick every row (problem rows stay as they are).
            $args['row_edits'] = collect($args['row_edits'] ?? [])->map(fn ($e) => array_diff_key($e, ['include' => 1]))->all();
            $args['include_all'] = (bool) $changes['all'];
        }
        foreach ($changes['rows'] ?? [] as $row) {
            $n = (int) $row['n'];
            $args['row_edits'][$n] = [...($args['row_edits'][$n] ?? []), ...array_intersect_key($row, array_flip(['include', 'title', 'assignee', 'severity', 'priority']))];
        }

        return $args;
    }

    // ── Prepare ──────────────────────────────────────────────────────────────

    public function prepare(array $args, User $user): PreparedAction
    {
        $file = $this->resolver->attachment($user, (string) ($args['attachment'] ?? ''));
        if ($file->kind !== AiAttachment::SPREADSHEET) {
            throw new ToolInputException(__('":file" is not a spreadsheet. Use plan_tasks_from_document for documents.', ['file' => $file->original_name]));
        }
        $sheet = [...AttachmentContext::sheet($file, $args['sheet'] ?? null), 'file' => $file->original_name];
        $project = $this->project($user, (string) ($args['project'] ?? ''));
        $mapping = $this->mapping($sheet['headers'], $this->mappingArg($args['mapping'] ?? []));

        $maxRows = (int) config('ai_assistant.attachments.max_import_rows', 500);
        $sourceRows = array_slice($sheet['rows'], 0, $maxRows);
        $members = $this->canAssign($user) ? $this->resolver->members($user)->orderBy('name')->get(['id', 'name', 'email']) : new Collection();
        $existing = $project
            ? $this->records($user)->where('project_id', $project->id)->pluck('title')->map(fn ($t) => mb_strtolower(trim($t)))->flip()->all()
            : [];

        $rows = [];
        $seen = [];
        foreach ($sourceRows as $source) {
            $row = $this->row($source, $sheet, $mapping, $members, $user, $args['row_edits'][$source['n']] ?? []);
            $key = mb_strtolower($row['title']);
            if ($row['title'] !== '' && (isset($existing[$key]) || isset($seen[$key]))) {
                $row['duplicate'] = true;
                $row['problems'][] = isset($existing[$key]) ? __('Already in :project', ['project' => $project?->title]) : __('Same title as an earlier row');
            }
            $seen[$key] = true;

            // Included unless it has no title or is a duplicate; the user can tick it back.
            $default = $row['title'] !== '' && !$row['duplicate'];
            $override = $args['row_edits'][$source['n']]['include'] ?? ($args['include_all'] ?? null);
            $row['include'] = $row['title'] !== '' && ($override === null ? $default : (bool) $override);
            $rows[] = $row;
        }

        $included = array_values(array_filter($rows, fn ($r) => $r['include']));
        $problem = match (true) {
            $mapping['title'] === null => __('Choose the column with the titles.'),
            $project === null => __('Choose the project.'),
            $included === [] => __('No rows are ticked.'),
            default => null,
        };

        $count = count($included);
        $label = $this->kind() === 'bugs' ? trans_choice(':count bug|:count bugs', $count) : trans_choice(':count task|:count tasks', $count);

        return new PreparedAction(
            summary: $project
                ? __('Create :items in :project from :file', ['items' => $label, 'project' => $project->title, 'file' => $file->original_name])
                : __('Create :items from :file', ['items' => $label, 'file' => $file->original_name]),
            details: array_filter([
                __('File') => "{$file->original_name} · {$sheet['name']}",
                __('Project') => $project?->title,
                __('Rows') => __(':included of :total ticked', ['included' => $count, 'total' => count($rows)])
                    . ($sheet['total_rows'] > count($rows) ? ' · ' . __('only the first :max rows are imported', ['max' => count($rows)]) : ''),
            ]),
            payload: [
                'kind' => $this->kind(),
                'project_id' => $project?->id,
                'file' => $file->original_name,
                'rows' => $problem ? [] : array_map(fn ($r) => $r['values'], $included),
            ],
            confirmPhrase: $count > PreparedAction::BULK_TYPED_CONFIRM_OVER ? 'CREATE ' . $count : null,
            view: [
                'type' => 'import',
                'kind' => $this->kind(),
                'file' => $file->original_name,
                'sheet' => $sheet['name'],
                'sheets' => array_column($file->sheets(), 'name'),
                'project' => $project ? ['value' => (string) $project->id, 'label' => $project->title] : null,
                'projects' => $this->resolver->projects($user)->orderBy('title')->limit(200)->get(['id', 'title'])
                    ->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->title])->all(),
                'headers' => $sheet['headers'],
                'fields' => collect($this->fields())->map(fn ($f, $name) => ['name' => $name, 'label' => $f['label'], 'column' => $mapping[$name]])->values()->all(),
                'members' => $members->map(fn ($m) => ['value' => (string) $m->id, 'label' => $m->name])->all(),
                'can_assign' => $this->canAssign($user),
                'priorities' => $this->priorities(),
                'severities' => $this->severities(),
                'rows' => array_map(fn ($r) => array_diff_key($r, ['values' => 1]), $rows),
                'total_rows' => $sheet['total_rows'],
                'included' => $count,
                'problem' => $problem,
            ],
        );
    }

    /** One sheet row as the card shows it ('values': what will be created). */
    private function row(array $source, array $sheet, array $mapping, Collection $members, User $user, array $edits): array
    {
        $cells = array_combine($sheet['headers'], $source['c']);
        $cell = fn (string $field) => $mapping[$field] !== null ? trim((string) ($cells[$mapping[$field]] ?? '')) : '';
        $problems = [];

        $title = mb_substr(trim((string) ($edits['title'] ?? $cell('title'))), 0, 255);
        if ($title === '') {
            $problems[] = __('No title');
        }

        $priority = $this->choice($edits['priority'] ?? $cell('priority'), $this->priorities(), self::PRIORITY_WORDS, 'medium', __('priority'), $problems);
        $severity = $this->severities()
            ? $this->choice($edits['severity'] ?? $cell('severity'), $this->severities(), self::SEVERITY_WORDS, 'major', __('severity'), $problems)
            : null;

        [$assignee, $assigneeName] = $this->assignee($edits, $cell('assignee'), $members, $user, $problems);
        $due = $this->date($cell('due_date'), $problems);

        // Columns with no field keep their values, under the description.
        $used = array_filter($mapping);
        $extra = collect($cells)->except($used)->filter(fn ($v) => trim((string) $v) !== '')
            ->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
        $origin = __('From :file, sheet :sheet, row :row', ['file' => $sheet['file'] ?? '', 'sheet' => $sheet['name'], 'row' => $source['n']]);
        $description = trim(implode("\n\n", array_filter([$cell('description'), $extra !== '' ? "{$origin}\n{$extra}" : null])));

        $values = array_filter([
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'severity' => $severity,
            'assigned_to' => $assignee,
            'end_date' => $due,
            'start_date' => isset($mapping['start_date']) ? $this->date($cell('start_date'), $problems) : null,
            'steps_to_reproduce' => isset($mapping['steps_to_reproduce']) ? $cell('steps_to_reproduce') : null,
            'expected_behavior' => isset($mapping['expected_behavior']) ? $cell('expected_behavior') : null,
            'actual_behavior' => isset($mapping['actual_behavior']) ? $cell('actual_behavior') : null,
            'environment' => isset($mapping['environment']) ? $cell('environment') : null,
        ], fn ($v) => $v !== null && $v !== '');

        return [
            'n' => $source['n'],
            'title' => $title,
            'priority' => $priority,
            'severity' => $severity,
            'assignee' => $assignee ? (string) $assignee : null,
            'assignee_name' => $assigneeName,
            'due_date' => $due,
            'problems' => $problems,
            'duplicate' => false,
            'include' => true,
            'values' => $values,
        ];
    }

    // ── Columns ──────────────────────────────────────────────────────────────

    /** "title=Summary; assignee=Dev" or an array → [field => column name]. */
    private function mappingArg(string|array $mapping): array
    {
        if (is_array($mapping)) {
            return $mapping;
        }
        $pairs = [];
        foreach (preg_split('/[;\n]+/', $mapping) as $pair) {
            if (str_contains($pair, '=')) {
                [$field, $column] = array_map('trim', explode('=', $pair, 2));
                $pairs[strtolower($field)] = $column;
            }
        }

        return array_intersect_key($pairs, $this->fields());
    }

    /**
     * Which column fills each field: what the model or the user said (when
     * the column exists), else a column whose name is a known synonym.
     *
     * @return array<string, string|null>
     */
    private function mapping(array $headers, array $given): array
    {
        $byKey = [];
        foreach ($headers as $header) {
            $byKey[$this->key($header)] ??= $header;
        }

        $mapping = [];
        $taken = [];
        foreach ($this->fields() as $field => $spec) {
            $column = null;
            if (array_key_exists($field, $given)) {
                $column = $given[$field] === null || $given[$field] === '' ? null : ($byKey[$this->key((string) $given[$field])] ?? null);
            } else {
                foreach ($spec['synonyms'] as $synonym) {
                    $candidate = $byKey[$this->key($synonym)] ?? null;
                    if ($candidate !== null && !in_array($candidate, $taken, true)) {
                        $column = $candidate;
                        break;
                    }
                }
            }
            $mapping[$field] = $column;
            if ($column !== null) {
                $taken[] = $column;
            }
        }

        return $mapping;
    }

    private function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($name));
    }

    // ── Values ───────────────────────────────────────────────────────────────

    private const PRIORITY_WORDS = [
        'low' => ['low', 'lowest', 'minor', 'trivial', 'p4', 'p5', '4', '5'],
        'medium' => ['medium', 'normal', 'moderate', 'med', 'p3', '3'],
        'high' => ['high', 'major', 'important', 'p2', '2'],
        'critical' => ['critical', 'urgent', 'highest', 'blocker', 'immediate', 'p0', 'p1', '0', '1'],
    ];

    private const SEVERITY_WORDS = [
        'minor' => ['minor', 'low', 'trivial', 'cosmetic', 's4', 'sev4', '4'],
        'major' => ['major', 'medium', 'normal', 'moderate', 's3', 'sev3', '3'],
        'critical' => ['critical', 'high', 'severe', 's2', 'sev2', '2'],
        'blocker' => ['blocker', 'showstopper', 'urgent', 'highest', 'crash', 's1', 'sev1', '1'],
    ];

    private function choice(string $value, array $valid, array $words, string $default, string $label, array &$problems): string
    {
        $key = $this->key($value);
        if ($key === '') {
            return $default;
        }
        foreach ($words as $choice => $list) {
            if (in_array($choice, $valid, true) && in_array($key, $list, true)) {
                return $choice;
            }
        }
        $problems[] = __('":value" is not a known :label; :default is used', ['value' => $value, 'label' => $label, 'default' => ucfirst($default)]);

        return $default;
    }

    /** @return array{0: ?int, 1: ?string} [member id, name] */
    private function assignee(array $edits, string $cell, Collection $members, User $user, array &$problems): array
    {
        if (array_key_exists('assignee', $edits)) {
            $id = $edits['assignee'] && $edits['assignee'] !== 'none' ? (int) $edits['assignee'] : null;
            $member = $id ? $members->firstWhere('id', $id) : null;

            return [$member?->id, $member?->name];
        }
        if ($cell === '' || $this->key($cell) === 'unassigned') {
            return [null, null];
        }
        if (!$this->canAssign($user)) {
            $problems[] = __('You may not assign people; left unassigned');

            return [null, null];
        }

        // Only a sure match: the email, the full name, or a first name only one person has.
        $lower = mb_strtolower($cell);
        $match = $members->first(fn ($m) => mb_strtolower($m->email) === $lower || mb_strtolower($m->name) === $lower);
        if (!$match) {
            $byFirst = $members->filter(fn ($m) => mb_strtolower(strtok($m->name, ' ')) === $lower);
            $match = $byFirst->count() === 1 ? $byFirst->first() : null;
        }
        if (!$match) {
            $problems[] = __('":name" is not in this workspace; left unassigned', ['name' => $cell]);

            return [null, null];
        }

        return [$match->id, $match->name];
    }

    private function date(string $value, array &$problems): ?string
    {
        if ($value === '') {
            return null;
        }
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d.m.Y', '!Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format(ltrim($format, '!')) === $value) {
                return $date->toDateString();
            }
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            $problems[] = __('":value" is not a date; left empty', ['value' => $value]);

            return null;
        }
    }

    private function project(User $user, string $ref): ?Project
    {
        if (trim($ref) === '') {
            $only = $this->resolver->projects($user)->limit(2)->get();

            return $only->count() === 1 ? $only->first() : null;
        }

        try {
            return $this->resolver->project($user, $ref);
        } catch (ToolInputException) {
            return null; // the user picks it on the card
        }
    }

    // ── Execute and undo ─────────────────────────────────────────────────────

    public function execute(array $payload, User $user): ToolOutcome
    {
        if (empty($payload['project_id']) || empty($payload['rows'])) {
            throw new ToolInputException(__('Choose the project and at least one row on the card first.'));
        }
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));

        // People are checked again: someone may have left the workspace since.
        $memberIds = $this->canAssign($user)
            ? $this->resolver->members($user)->pluck('id')->map(fn ($id) => (int) $id)->flip()->all()
            : [];

        $created = [];
        foreach ($payload['rows'] as $row) {
            if (!empty($row['assigned_to']) && !isset($memberIds[(int) $row['assigned_to']])) {
                unset($row['assigned_to']);
            }
            $created[] = $this->create($user, $project, $row);
        }

        $count = count($created);
        $label = $this->kind() === 'bugs' ? trans_choice(':count bug|:count bugs', $count) : trans_choice(':count task|:count tasks', $count);

        return new ToolOutcome(
            __('Created :items in :project from :file.', ['items' => $label, 'project' => $project->title, 'file' => $payload['file'] ?? '']),
            $created[0] ?? null,
            $count === 1 ? $this->link($created[0]) : route('projects.show', $project->id, false),
            ['ids' => array_map(fn ($r) => $r->getKey(), $created)],
        );
    }

    /** Removes the created records nobody has changed since. */
    public function undo(array $undo, User $user): string
    {
        $records = $this->records($user)->whereKey($undo['ids'] ?? [])->get();
        $removed = 0;
        foreach ($records as $record) {
            if ($record->updated_at?->equalTo($record->created_at)) {
                $record->delete();
                $removed++;
            }
        }
        $kept = count($undo['ids'] ?? []) - $removed;

        return $kept > 0
            ? __('Removed :removed; :kept were changed since or are gone, so they were kept.', ['removed' => $removed, 'kept' => $kept])
            : __('Removed all :removed.', ['removed' => $removed]);
    }
}
