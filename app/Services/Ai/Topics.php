<?php

namespace App\Services\Ai;

use App\Models\User;

/**
 * The topic buttons in the message box ("Tasks", "Bugs"…). A topic tells
 * the assistant what the next messages are about: the model gets only that
 * topic's tools (plus a few shared look-ups) and a line saying so. Fewer
 * tools mean fewer tokens and fewer wrong tool picks.
 *
 * A topic only narrows the user's own allowed tools, never adds to them.
 * A message that is clearly about another topic gets all tools (decided by
 * keywords before the AI call, so it costs no extra call).
 */
class Topics
{
    /** Look-ups every topic keeps: resolving names and "who changed this". */
    public const SHARED = ['list_projects', 'list_team_members', 'get_record_history', 'read_attachment', 'get_sheet_rows'];

    public const TOOLS = [
        'tasks' => ['list_tasks', 'create_task', 'assign_task', 'change_task_status', 'list_sprints', 'add_tasks_to_sprint', 'import_tasks_from_sheet', 'plan_tasks_from_document'],
        'bugs' => ['list_bugs', 'create_bug', 'assign_bug', 'change_bug_status', 'import_bugs_from_sheet'],
        'projects' => ['create_project', 'add_project_members', 'get_project_report', 'get_budget_status', 'list_tasks', 'list_bugs', 'plan_tasks_from_document'],
        'time' => ['list_my_time', 'log_time', 'update_time_entry', 'delete_time_entry', 'submit_timesheet', 'start_timer', 'stop_timer', 'list_tasks'],
        'approvals' => ['list_timesheet_approvals', 'decide_timesheets', 'list_expense_approvals', 'decide_expenses'],
        'finance' => [
            'list_invoices', 'create_invoice', 'update_invoice', 'delete_invoice', 'send_invoice', 'mark_invoice_paid', 'get_revenue_summary',
            'list_expenses', 'create_expense', 'update_expense', 'delete_expense', 'get_budget_status', 'create_budget', 'update_budget',
            'list_contracts', 'list_expense_approvals',
        ],
        'team' => ['invite_user'],
        'help' => ['search_knowledge_base'],
    ];

    /** Words that show a message is about a topic, for the out-of-topic check. */
    private const KEYWORDS = [
        'tasks' => ['task', 'tasks', 'todo', 'to-do', 'story', 'stories', 'sprint', 'sprints', 'stage', 'kanban'],
        'bugs' => ['bug', 'bugs', 'defect', 'defects', 'issue', 'issues', 'crash', 'crashes'],
        'projects' => ['project', 'projects', 'milestone', 'milestones', 'report', 'reports', 'progress'],
        'time' => ['hours', 'hour', 'hrs', 'timer', 'log time', 'logged', 'worked', 'timesheet', 'timesheets', 'time entry', 'time entries', 'my time', 'clock'],
        'approvals' => ['approve', 'approval', 'approvals', 'reject', 'timesheet', 'timesheets', 'expense', 'expenses', 'pending'],
        'finance' => [
            'invoice', 'invoices', 'revenue', 'bill', 'billed', 'billing', 'payment', 'payments', 'paid', 'contract', 'contracts',
            'budget', 'budgets', 'money', 'expense', 'expenses', 'spent', 'cost', 'costs',
        ],
        'team' => ['invite', 'invitation', 'team', 'member', 'members', 'people', 'colleague', 'colleagues'],
        'help' => ['how do i', 'how to', 'how can i', 'policy', 'guide', 'help', 'documentation', 'knowledge base'],
    ];

    public static function label(string $topic): string
    {
        return match ($topic) {
            'tasks' => __('Tasks'),
            'bugs' => __('Bugs'),
            'projects' => __('Projects'),
            'time' => __('Time'),
            'approvals' => __('Approvals'),
            'finance' => __('Finance'),
            'team' => __('Team'),
            'help' => __('Help'),
        };
    }

    public static function valid(?string $topic): bool
    {
        return $topic !== null && array_key_exists($topic, self::TOOLS);
    }

    /**
     * @param  string|string[]  $topics  one topic, or several (their tools together)
     * @return string[] tool names the topics may use (before permissions)
     */
    public static function toolNames(string|array $topics): array
    {
        $names = collect((array) $topics)->filter(fn ($t) => self::valid($t))->flatMap(fn ($t) => self::TOOLS[$t]);

        return $names->concat(self::SHARED)->unique()->values()->all();
    }

    /**
     * The topics worth showing this user: at least one of the topic's own
     * tools (not just the shared look-ups) is allowed for them.
     *
     * @param  array<string, mixed>  $allowed  tools the user may use, keyed by name
     * @return array<int, array{key: string, label: string}>
     */
    public static function available(array $allowed): array
    {
        return collect(self::TOOLS)
            ->filter(fn (array $tools) => array_intersect($tools, array_keys($allowed)))
            ->keys()
            ->map(fn (string $key) => ['key' => $key, 'label' => self::label($key)])
            ->values()
            ->all();
    }

    /**
     * Keep the chosen topic for this message unless the message is clearly
     * about other topics only. A message that names no topic keeps it, so
     * short replies like "login page for mobile, high" stay on topic.
     */
    public static function appliesTo(string $topic, string $text): bool
    {
        $mentioned = self::mentioned($text);

        return $mentioned === [] || in_array($topic, $mentioned, true);
    }

    /** @return string[] the topics a message mentions by keyword */
    public static function mentioned(string $text): array
    {
        $said = ' ' . trim(preg_replace('/[^\p{L}\p{N}\-]+/u', ' ', mb_strtolower($text))) . ' ';

        return collect(self::KEYWORDS)
            ->filter(fn (array $words) => collect($words)->contains(fn (string $w) => str_contains($said, " {$w} ")))
            ->keys()
            ->all();
    }

    /** The line added to the system prompt while a topic is on. */
    public static function prompt(string $topic): string
    {
        return __('The user picked the ":topic" topic: treat their messages as being about :lower unless they clearly ask about something else. A short message such as a name with a few details is a request to create or update an item of this kind.', [
            'topic' => self::label($topic),
            'lower' => mb_strtolower(self::label($topic)),
        ]);
    }

    /** Topic for an IntentMatcher fallback: the create form a bare message in this topic most likely means. */
    public static function defaultForm(string $topic): ?string
    {
        return ['tasks' => 'create_task', 'bugs' => 'create_bug', 'projects' => 'create_project', 'time' => 'log_time', 'team' => 'invite_user'][$topic] ?? null;
    }

    /** For tests and the page: the topic keys in display order. */
    public static function keys(): array
    {
        return array_keys(self::TOOLS);
    }

    /** Convenience for the controller: topics for this user. */
    public static function forUser(User $user, ToolRegistry $registry): array
    {
        return self::available($registry->forUser($user));
    }
}
