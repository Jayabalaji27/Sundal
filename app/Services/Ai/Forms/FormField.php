<?php

namespace App\Services\Ai\Forms;

/**
 * One value a write tool needs. The server fills it from what the user said,
 * with fixed rules; nothing here calls the AI model.
 *
 * Three tiers decide whether the user is ever asked:
 *  - must know: required, no default (project when there are several, which
 *    task…). Asked once in the chat, with the choices as buttons.
 *  - default:   required with a default (priority → medium, assignee →
 *    unassigned). Never asked; shown on the confirm card, changeable.
 *  - optional:  not required (due date…). Never asked.
 *
 * Kinds: project, member, members (several people), task, bug, task_stage,
 * bug_status, invoice (a draft), unpaid_invoice, expense (not yet approved),
 * budget_category, time_entry and timesheet (the user's own, unlocked),
 * billable_tasks (several tasks not on a sent invoice): records, picked
 * from a list the user may see. Also enum (fixed options), date
 * (YYYY-MM-DD), number (more than 0, up to $max) and text.
 */
final class FormField
{
    public const RECORD_KINDS = [
        'project', 'member', 'members', 'task', 'bug', 'task_stage', 'bug_status', 'invoice', 'unpaid_invoice',
        'expense', 'budget_category', 'time_entry', 'timesheet', 'billable_tasks',
    ];

    /** Kinds that hold several records. */
    public const MULTI_KINDS = ['members', 'billable_tasks'];

    /**
     * @param  array<string, string>  $options  enum only: value => label
     * @param  bool  $allowNone  member only: offer "Unassigned" (value "none")
     * @param  string|null  $narrowBy  a tool argument or an earlier project field (e.g. "project") that narrows the list
     * @param  string|null  $default  used when the user said nothing ("medium", "none" for unassigned, a date)
     * @param  string|null  $question  how the chat asks for it, e.g. "Which project should it go in?"
     * @param  float|null  $max  number only: the largest value allowed
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $kind,
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly bool $allowNone = false,
        public readonly ?string $narrowBy = null,
        public readonly ?string $default = null,
        public readonly int $maxLength = 255,
        public readonly ?string $question = null,
        public readonly ?float $max = null,
    ) {}

    /** ['on_hold'] → ['on_hold' => 'On hold'] for enum options. */
    public static function labels(array $values): array
    {
        return collect($values)->mapWithKeys(fn ($v) => [$v => __(ucfirst(str_replace('_', ' ', $v)))])->all();
    }

    /** "Must know": the only tier the chat asks for. */
    public function mustAsk(): bool
    {
        return $this->required && $this->default === null;
    }

    public function isRecord(): bool
    {
        return in_array($this->kind, self::RECORD_KINDS, true);
    }

    /** What the page renders: select, multi, date, number or text. */
    public function inputType(): string
    {
        return match (true) {
            in_array($this->kind, self::MULTI_KINDS, true) => 'multi',
            in_array($this->kind, ['date', 'number', 'text'], true) => $this->kind,
            default => 'select',
        };
    }

    public function questionText(): string
    {
        return $this->question ?? match ($this->kind) {
            'project' => __('Which project should it go in?'),
            'member', 'members' => __('Who should it be?'),
            'task' => __('Which task?'),
            'bug' => __('Which bug?'),
            'task_stage' => __('Which stage?'),
            'bug_status' => __('Which status?'),
            'invoice', 'unpaid_invoice' => __('Which invoice?'),
            'expense' => __('Which expense?'),
            'budget_category' => __('Which budget category?'),
            'time_entry' => __('Which time entry?'),
            'timesheet' => __('Which timesheet?'),
            'billable_tasks' => __('Which tasks should it bill?'),
            'number' => __('How much is the :label?', ['label' => mb_strtolower($this->label)]),
            'text' => __('What should the :label be?', ['label' => mb_strtolower($this->label)]),
            default => __('Which :label?', ['label' => mb_strtolower($this->label)]),
        };
    }
}
