<?php

namespace App\Services\Ai\Forms;

use App\Models\BugStatus;
use App\Models\TaskStage;
use App\Models\User;
use App\Services\Ai\Tools\AiTool;
use App\Services\Ai\Tools\ListInvoices;
use App\Services\Ai\Tools\RecordResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Builds and checks form cards with fixed rules. No AI is involved here.
 *
 * build(): turns what the model extracted into field values, keeping a
 * value only when it resolves to exactly one record the user may see AND
 * the user actually said it (the "grounding" check). Anything else is left
 * for the user to pick from a list on the card.
 *
 * submit(): validates the user's picks against the same lists and returns
 * ordinary tool arguments for the tool's prepare().
 */
class FormBuilder
{
    private const MAX_OPTIONS = 200;

    /** Words too common to show that the user named a record. */
    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'into', 'new', 'add', 'create', 'make', 'task', 'tasks',
        'todo', 'bug', 'bugs', 'issue', 'project', 'projects', 'page', 'app', 'team', 'sprint', 'status', 'stage',
        'work', 'item', 'story', 'please', 'can', 'you', 'assign', 'move', 'set', 'change',
    ];

    private const ME = ['me', 'myself', 'i', 'mine', 'my'];

    public function __construct(private readonly RecordResolver $resolver) {}

    /**
     * The parameters handed to the model for a form tool: none of them is
     * required, so the model is never pushed to invent a project or person.
     */
    public function modelParameters(AiTool $tool): array
    {
        return collect($tool->parameters())->map(fn (array $p) => [...$p, 'required' => false])->all();
    }

    /**
     * @param  string  $said  the user's own words (recent messages), for grounding
     * @return array{fields: array<int, array<string, mixed>>, fixed: array<string, mixed>, error: ?string}
     */
    public function build(AiTool&HasForm $tool, array $args, User $user, string $said): array
    {
        $said = $this->normalize($said);
        $fields = $tool->formFields($user);

        $states = array_map(fn (FormField $field) => $this->initial($field, $args, $user, $said), $fields);
        $consumed = collect($fields)->flatMap(fn (FormField $f) => array_filter([$f->name, $f->narrowBy]))->all();

        return [
            'fields' => $states,
            // Arguments the form does not show (description, start date…) pass through as given.
            'fixed' => Arr::except($args, $consumed),
            'error' => null,
        ];
    }

    /**
     * Validate the card. Submitted values override the stored ones; fields
     * the user did not touch keep their value.
     *
     * @return array{ok: bool, form: array, args: array<string, mixed>}
     */
    public function submit(AiTool&HasForm $tool, array $form, array $submitted, User $user, bool $partial = false): array
    {
        $fields = collect($tool->formFields($user))->keyBy('name');
        $args = $form['fixed'] ?? [];
        $ok = true;
        $states = [];

        foreach ($form['fields'] ?? [] as $state) {
            $field = $fields->get($state['name']);
            if (!$field) {
                continue; // no longer offered to this user (permission changed)
            }

            $value = array_key_exists($field->name, $submitted) ? $submitted[$field->name] : ($state['value'] ?? null);
            $state['value'] = $field->inputType() === 'multi' ? array_values(array_map('strval', (array) ($value ?? []))) : $value;
            $state['note'] = null;
            if (array_key_exists($field->name, $submitted)) {
                $state['defaulted'] = false;
            }
            [$arg, $state['error']] = $this->toArgument($field, $value, $user);

            // Partial update (a clicked choice, a typed answer): a still-missing
            // value is not an error yet; it is asked next.
            if ($partial && $this->isEmptyValue($value)) {
                $state['error'] = null;
            }

            if ($state['error'] !== null) {
                $ok = false;
            } elseif ($arg !== null) {
                $args[$field->name] = $arg;
            }
            $states[] = $state;
        }

        return ['ok' => $ok, 'form' => [...$form, 'fields' => $states, 'error' => null], 'args' => $args];
    }

    /** True when every required field already has a value. */
    public function isComplete(array $form): bool
    {
        return $this->missing($form) === [];
    }

    /** @return string[] names of the required fields still without a value, in card order */
    public function missing(array $form): array
    {
        return collect($form['fields'])
            ->filter(fn (array $state) => $state['required'] && $this->isEmptyValue($state['value']))
            ->pluck('name')
            ->values()
            ->all();
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * For the model's tool result and the card: ["Title: Login page"],
     * ["Priority: Medium"] (defaults), ["Project"] (still missing).
     *
     * @return array{0: string[], 1: string[], 2: string[]}
     */
    public function describe(array $form): array
    {
        $known = $defaults = $missing = [];
        foreach ($form['fields'] as $state) {
            $label = $this->labelOf($state);
            if ($label === null) {
                if ($state['required']) {
                    $missing[] = $state['label'];
                }
            } elseif ($state['defaulted'] ?? false) {
                $defaults[] = "{$state['label']}: {$label}";
            } else {
                $known[] = "{$state['label']}: {$label}";
            }
        }

        return [$known, $defaults, $missing];
    }

    // ── Building ─────────────────────────────────────────────────────────────

    private function initial(FormField $field, array $args, User $user, string $said): array
    {
        $state = [
            'name' => $field->name,
            'label' => $field->label,
            'type' => $field->inputType(),
            'kind' => $field->kind,
            'required' => $field->required,
            'value' => $field->inputType() === 'multi' ? [] : null,
            'options' => [],
            'note' => null,
            'error' => null,
            // How the chat asks for it when it is missing.
            'question' => $field->questionText(),
            // True while the value is the field's default, not something the user said.
            'defaulted' => false,
        ];

        $raw = $args[$field->name] ?? null;
        $raw = is_string($raw) || is_numeric($raw) ? trim((string) $raw) : null;
        $raw = $raw === '' ? null : $raw;

        return match ($field->kind) {
            'enum' => $this->initialEnum($field, $state, $raw, $said),
            'date' => $this->initialDate($state, $raw),
            'text' => [...$state, 'value' => $raw !== null ? mb_substr($raw, 0, $field->maxLength) : null],
            'members' => $this->initialMembers($field, $state, $raw, $user, $said),
            default => $this->initialRecord($field, $state, $raw, $args, $user, $said),
        };
    }

    private function initialEnum(FormField $field, array $state, ?string $raw, string $said): array
    {
        $state['options'] = collect($field->options)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()->all();

        // The model's value counts only when the user said it ("high", "on hold");
        // an invented one falls back to the default, like saying nothing.
        $saidIt = $raw !== null && array_key_exists($raw, $field->options)
            && ($this->saidPhrase($said, str_replace('_', ' ', $raw)) || $this->saidPhrase($said, $field->options[$raw]));

        if ($saidIt) {
            $state['value'] = $raw;
        } elseif ($field->default !== null && array_key_exists($field->default, $field->options)) {
            $state['value'] = $field->default;
            $state['defaulted'] = true;
        }

        return $state;
    }

    private function initialDate(array $state, ?string $raw): array
    {
        if ($raw !== null) {
            $this->validDate($raw) ? $state['value'] = $raw : $state['note'] = __('":value" is not a date; pick one.', ['value' => $raw]);
        }

        return $state;
    }

    private function initialMembers(FormField $field, array $state, ?string $raw, User $user, string $said): array
    {
        $state['options'] = $this->options('member', $this->query('member', $user));

        $notes = [];
        foreach (array_filter(array_map('trim', explode(';', (string) $raw))) as $ref) {
            [$match, $note] = $this->pick('member', $ref, $user, $said, $this->query('member', $user));
            $match ? $state['value'][] = (string) $match->getKey() : $notes[] = $note;
        }
        $state['value'] = array_values(array_unique($state['value']));
        $state['note'] = $notes ? implode(' ', $notes) : null;

        return $state;
    }

    private function initialRecord(FormField $field, array $state, ?string $raw, array $args, User $user, string $said): array
    {
        $query = $this->query($field->kind, $user, $this->narrowId($field, $args, $user));
        $options = $this->options($field->kind, $query);

        if ($raw !== null) {
            [$match, $note, $matches] = $this->pick($field->kind, $raw, $user, $said, $query);
            $match ? $state['value'] = (string) $match->getKey() : $state['note'] = $note;
            // Possible matches go to the top of the list (and are in it even past the first 200).
            $options = collect($this->options($field->kind, $matches))->concat($options)->unique('value')->values()->all();
        } elseif ($field->default !== null) {
            // Nothing said: use the default ("none" = unassigned).
            $state['value'] = $field->default;
            $state['defaulted'] = true;
        } elseif ($field->required && count($options) === 1 && $field->kind === 'project') {
            // Only one project to choose from: nothing to ask.
            $state['value'] = $options[0]['value'];
        }

        if ($field->allowNone) {
            array_unshift($options, ['value' => 'none', 'label' => __('Unassigned')]);
        }
        $state['options'] = $options;

        return $state;
    }

    /**
     * Resolve one reference. Returns [record, null, matches] only when it
     * matches exactly one record AND the user said it; otherwise
     * [null, note for the card, possible matches].
     */
    private function pick(string $kind, string $ref, User $user, string $said, Builder $query): array
    {
        $label = $this->kindLabel($kind);

        if ($kind === 'member' && in_array(mb_strtolower($ref), self::ME, true)) {
            return $this->hasAnyWord($said, self::ME)
                ? [$user, null, collect([$user])]
                : [null, __('Pick the :label.', ['label' => $label]), collect()];
        }
        if ($kind === 'member' && filter_var($ref, FILTER_VALIDATE_EMAIL)) {
            $byEmail = (clone $query)->where('email', $ref)->first();
            if ($byEmail && $this->saidPhrase($said, $ref)) {
                return [$byEmail, null, collect([$byEmail])];
            }
        }

        $matches = $this->resolver->candidates(clone $query, $ref, $this->column($kind));

        if ($matches->count() === 1 && $this->saidName($said, $ref, $this->nameOf($kind, $matches->first()))) {
            return [$matches->first(), null, $matches];
        }

        return [null, match (true) {
            $matches->isEmpty() => __('Nothing matches ":ref"; pick the :label.', ['ref' => $ref, 'label' => $label]),
            $matches->count() > 1 => __('Several match ":ref"; pick one.', ['ref' => $ref]),
            default => __('Pick the :label.', ['label' => $label]),
        }, $matches];
    }

    private function narrowId(FormField $field, array $args, User $user): ?int
    {
        $ref = $field->narrowBy ? trim((string) ($args[$field->narrowBy] ?? '')) : '';
        if ($ref === '') {
            return null;
        }
        $projects = $this->resolver->candidates($this->resolver->projects($user), $ref, 'title');

        return $projects->count() === 1 ? (int) $projects->first()->getKey() : null;
    }

    // ── Submitting ───────────────────────────────────────────────────────────

    /** @return array{0: mixed, 1: ?string} [tool argument or null, error or null] */
    private function toArgument(FormField $field, mixed $value, User $user): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [null, $field->required ? __('Choose the :label.', ['label' => mb_strtolower($field->label)]) : null];
        }

        // Only "several people" takes a list; everywhere else a list is not a valid pick.
        if ($field->inputType() !== 'multi' && !is_scalar($value)) {
            return [null, __('Choose a valid option.')];
        }

        if ($field->allowNone && $value === 'none') {
            return [null, null];
        }

        switch ($field->kind) {
            case 'enum':
                return array_key_exists((string) $value, $field->options) ? [(string) $value, null] : [null, __('Choose a valid option.')];
            case 'date':
                return $this->validDate((string) $value) ? [(string) $value, null] : [null, __('Use a valid date.')];
            case 'text':
                $text = trim((string) $value);
                if ($text === '') {
                    return [null, $field->required ? __('Enter the :label.', ['label' => mb_strtolower($field->label)]) : null];
                }

                return mb_strlen($text) > $field->maxLength
                    ? [null, __('At most :max characters.', ['max' => $field->maxLength])]
                    : [$text, null];
            case 'members':
                $ids = array_values(array_unique(array_map('intval', (array) $value)));
                $found = $this->query('member', $user)->whereKey($ids)->count();

                return $found === count($ids)
                    ? [implode(';', array_map(fn ($id) => "#{$id}", $ids)), null]
                    : [null, __('Someone you picked is no longer in this workspace.')];
            default:
                $id = (int) $value;

                return $id > 0 && $this->query($field->kind, $user)->whereKey($id)->exists()
                    ? ["#{$id}", null]
                    : [null, __('That :label is no longer available; choose again.', ['label' => mb_strtolower($field->label)])];
        }
    }

    // ── Lists, scoped like the screens ───────────────────────────────────────

    private function query(string $kind, User $user, ?int $projectId = null): Builder
    {
        $workspaceId = $user->current_workspace_id;

        return match ($kind) {
            'project' => $this->resolver->projects($user)->orderBy('title'),
            'member', 'members' => $this->resolver->members($user)->orderBy('name'),
            'task' => $this->resolver->tasks($user)->with('project:id,title')
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))->orderByDesc('id'),
            'bug' => $this->resolver->bugs($user)->with('project:id,title')
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))->orderByDesc('id'),
            'task_stage' => TaskStage::forWorkspace($workspaceId)->ordered(),
            'bug_status' => BugStatus::forWorkspace($workspaceId)->ordered(),
            'invoice' => ListInvoices::visible($user)->where('status', 'draft')->with('client:id,name')->orderByDesc('id'),
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    private function options(string $kind, Builder|iterable $records): array
    {
        $records = $records instanceof Builder ? $records->limit(self::MAX_OPTIONS)->get() : $records;

        return collect($records)->map(fn (Model $m) => ['value' => (string) $m->getKey(), 'label' => $this->optionLabel($kind, $m)])->values()->all();
    }

    private function optionLabel(string $kind, Model $m): string
    {
        return match ($kind) {
            'member', 'members' => "{$m->name} ({$m->email})",
            'task', 'bug' => "{$m->title} · {$m->project?->title} (#{$m->id})",
            'invoice' => trim("{$m->invoice_number} · " . ($m->client?->name ?? '') . ' · ' . number_format((float) $m->total_amount, 2), ' ·'),
            default => (string) $this->nameOf($kind, $m),
        };
    }

    private function nameOf(string $kind, Model $m): string
    {
        return (string) match ($kind) {
            'project', 'task', 'bug' => $m->title,
            'invoice' => $m->invoice_number,
            default => $m->name,
        };
    }

    private function column(string $kind): string
    {
        return match ($kind) {
            'project', 'task', 'bug' => 'title',
            'invoice' => 'invoice_number',
            default => 'name',
        };
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            'member', 'members' => __('person'),
            'task_stage' => __('stage'),
            'bug_status' => __('status'),
            default => __($kind),
        };
    }

    private function labelOf(array $state): ?string
    {
        $values = (array) ($state['value'] ?? []);
        if (!$values || $values === ['']) {
            return null;
        }
        if (!$state['options']) {
            return implode(', ', $values);
        }

        $labels = collect($state['options'])->whereIn('value', array_map('strval', $values))->pluck('label');

        return $labels->isEmpty() ? implode(', ', $values) : $labels->implode(', ');
    }

    // ── Grounding: did the user say it? ──────────────────────────────────────

    private function normalize(string $text): string
    {
        return ' ' . trim(preg_replace('/[^\p{L}\p{N}@.]+/u', ' ', mb_strtolower($text))) . ' ';
    }

    private function saidPhrase(string $said, string $phrase): bool
    {
        $phrase = trim($this->normalize($phrase));

        return $phrase !== '' && !in_array($phrase, self::STOPWORDS, true) && str_contains($said, " {$phrase} ");
    }

    private function hasAnyWord(string $said, array $words): bool
    {
        foreach ($words as $word) {
            if (str_contains($said, " {$word} ")) {
                return true;
            }
        }

        return false;
    }

    /** The user named this record: by its id, the model's words, its full name or a distinctive word of it. */
    private function saidName(string $said, string $ref, string $name): bool
    {
        if (preg_match('/^#?(\d+)$/', trim($ref), $m)) {
            return str_contains($said, " {$m[1]} ");
        }
        if ($this->saidPhrase($said, $ref) || $this->saidPhrase($said, $name)) {
            return true;
        }

        foreach (explode(' ', trim($this->normalize($name))) as $word) {
            if (mb_strlen($word) >= 3 && !in_array($word, self::STOPWORDS, true) && str_contains($said, " {$word} ")) {
                return true;
            }
        }

        return false;
    }

    private function validDate(string $value): bool
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d') === $value;
        } catch (\Throwable) {
            return false;
        }
    }
}
