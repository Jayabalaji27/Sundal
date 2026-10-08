<?php

namespace App\Services\Ai\Forms;

/**
 * One field on a form card. The server fills it from what the user said,
 * with fixed rules, and the user completes the rest on the card. Nothing
 * here calls the AI model.
 *
 * Kinds: project, member, members (several people), task, bug, task_stage,
 * bug_status, invoice (records, picked from a list the user may see),
 * enum (fixed options), date (YYYY-MM-DD), text.
 */
final class FormField
{
    public const RECORD_KINDS = ['project', 'member', 'members', 'task', 'bug', 'task_stage', 'bug_status', 'invoice'];

    /**
     * @param  array<string, string>  $options  enum only: value => label
     * @param  bool  $mustChoose  enum only: keep the model's value only when the user said it
     * @param  bool  $allowNone  member only: offer "Unassigned" (value "none")
     * @param  string|null  $narrowBy  task/bug only: a tool argument (e.g. "project") that narrows the list
     * @param  string|null  $default  enum only: used when the user said nothing and mustChoose is off
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $kind,
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly bool $mustChoose = false,
        public readonly bool $allowNone = false,
        public readonly ?string $narrowBy = null,
        public readonly ?string $default = null,
        public readonly int $maxLength = 255,
    ) {}

    /** ['on_hold'] → ['on_hold' => 'On hold'] for enum options. */
    public static function labels(array $values): array
    {
        return collect($values)->mapWithKeys(fn ($v) => [$v => __(ucfirst(str_replace('_', ' ', $v)))])->all();
    }

    public function isRecord(): bool
    {
        return in_array($this->kind, self::RECORD_KINDS, true);
    }

    /** What the page renders: select, multi, date or text. */
    public function inputType(): string
    {
        return match ($this->kind) {
            'members' => 'multi',
            'date' => 'date',
            'text' => 'text',
            default => 'select',
        };
    }
}
