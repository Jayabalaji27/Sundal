<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiAccess;

class OpenPageTool extends UiTool
{
    /**
     * Page key => [route name, permission (any of a list), label, config/features.php
     * switch the menu item needs]. Team takes the workspace in its URL.
     */
    private const PAGES = [
        'dashboard' => ['dashboard', null, 'Dashboard'],
        'projects' => ['projects.index', 'project_view_any', 'Projects'],
        'tasks' => ['tasks.index', 'task_view_any', 'Tasks'],
        'bugs' => ['bugs.index', 'bug_view_any', 'Bugs'],
        'timesheets' => ['timesheets.index', 'timesheet_view_any', 'Timesheets'],
        'meetings' => ['meetings.index', ['zoom_meeting_view_any', 'google_meeting_view_any'], 'Meetings', 'meetings'],
        'approvals' => ['approvals.index', ['timesheet_approve', 'expense_approval_view_any'], 'Approvals'],
        'timesheet_approvals' => ['timesheet-approvals.index', 'timesheet_approve', 'Timesheet approvals'],
        'expenses' => ['expenses.index', 'expense_view_any', 'Expenses'],
        'expense_approvals' => ['expense-approvals.index', 'expense_approval_view_any', 'Expense approvals'],
        'budgets' => ['budgets.index', 'budget_view_any', 'Budgets'],
        'invoices' => ['invoices.index', 'invoice_view_any', 'Invoices'],
        'contracts' => ['contracts.index', 'contract_view_any', 'Contracts'],
        'reports' => ['timesheet-reports.index', 'report_timesheet', 'Reports'],
        'project_reports' => ['project-reports.index', 'project_report_view_any', 'Project reports'],
        'team' => ['team.index', 'team_view', 'Team'],
        'calendar' => ['task-calendar.index', 'task_calendar_view', 'Calendar', 'calendar'],
        'knowledge_base' => ['kb.index', 'kb_view_any', 'Knowledge Base', 'knowledge_base'],
        'ai_tools' => ['ai.index', 'agent_advanced_insights', 'AI tools', 'ai'],
        'risk_radar' => ['risk-radar.index', 'agent_advanced_insights', 'Risk Radar', 'ai'],
        'resource_conflicts' => ['resource-conflicts.index', 'agent_advanced_insights', 'Resource Conflicts', 'ai'],
        'standup' => ['standup.index', 'agent_advanced_insights', 'Standup Bot', 'ai'],
        'settings' => ['settings', 'settings_view', 'Settings'],
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
        $page = self::PAGES[$args['page'] ?? ''] ?? throw new ToolInputException(__('There is no such page. Ask the user which page they mean.'));
        [$route, $permission, $label] = $page;

        if ($permission && !collect((array) $permission)->contains(fn ($p) => $user->hasWorkspacePermission($p))) {
            throw new ToolInputException(__('The user is not allowed to open :page.', ['page' => $label]));
        }
        if (isset($page[3]) && !config("features.{$page[3]}", true)) {
            throw new ToolInputException(__(':page is switched off in this Sundal.', ['page' => $label]));
        }

        $params = $route === 'team.index' ? ['workspace' => $user->current_workspace_id] : [];

        return ['url' => route($route, $params, false), 'label' => __($label)];
    }
}
