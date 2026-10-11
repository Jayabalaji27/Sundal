<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiAccess;

class OpenPageTool extends UiTool
{
    /** Page key => [route name, permission, label]. */
    private const PAGES = [
        'dashboard' => ['dashboard', null, 'Dashboard'],
        'projects' => ['projects.index', 'project_view_any', 'Projects'],
        'tasks' => ['tasks.index', 'task_view_any', 'Tasks'],
        'bugs' => ['bugs.index', 'bug_view_any', 'Bugs'],
        'timesheets' => ['timesheets.index', 'timesheet_view_any', 'Timesheets'],
        'timesheet_approvals' => ['timesheet-approvals.index', 'timesheet_approve', 'Timesheet approvals'],
        'expenses' => ['expenses.index', 'expense_view_any', 'Expenses'],
        'expense_approvals' => ['expense-approvals.index', 'expense_approval_view_any', 'Expense approvals'],
        'budgets' => ['budgets.index', 'budget_view_any', 'Budgets'],
        'invoices' => ['invoices.index', 'invoice_view_any', 'Invoices'],
        'contracts' => ['contracts.index', 'contract_view_any', 'Contracts'],
        'project_reports' => ['project-reports.index', 'project_report_view_any', 'Project reports'],
        'notes' => ['notes.index', 'note_view_any', 'Notes'],
    ];

    public function name(): string
    {
        return 'open_page';
    }

    public function description(): string
    {
        return 'Open a Sundal page on the user\'s screen, e.g. "go to invoices", "show me the dashboard". For a filtered task, bug or project list use show_list; for one record use open_record.';
    }

    /** Each page's own permission is checked in action(). */
    public function permissions(): array
    {
        return [];
    }

    /** The dashboard is open to everyone who may use the assistant. */
    public function allowedFor(User $user): bool
    {
        return AiAccess::canUse($user);
    }

    public function parameters(): array
    {
        return [
            'page' => ['type' => 'enum', 'options' => array_keys(self::PAGES), 'required' => true, 'description' => 'The page to open.'],
        ];
    }

    public function action(array $args, User $user): array
    {
        [$route, $permission, $label] = self::PAGES[$args['page'] ?? ''] ?? throw new ToolInputException(__('There is no such page. Ask the user which page they mean.'));

        if ($permission && !$user->hasWorkspacePermission($permission)) {
            throw new ToolInputException(__('The user is not allowed to open :page.', ['page' => $label]));
        }

        return ['url' => route($route, [], false), 'label' => __($label)];
    }
}
