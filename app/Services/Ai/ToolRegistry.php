<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Tools;
use App\Services\Ai\Tools\AiTool;

/**
 * Every tool the assistant knows. The model only ever sees the tools the
 * signed-in user's workspace permissions allow, so changing a role in
 * RoleSeeder changes what its assistant can do.
 *
 * Every module in the owner's and manager's menus has its tools here
 * (2026-11-10). Deliberately absent (stay on the normal screens): roles and
 * permissions, plans and payments, settings and integrations, BYOA/API keys,
 * webhooks, changing a member's workspace role or removing users, deleting a
 * workspace or a project, and creating contracts (needs the contract file).
 * Hard deletes are limited to everyday records: draft invoices (with the
 * number typed), unapproved expenses, the user's own unsubmitted time, and
 * meetings the user scheduled (cancel_meeting).
 */
class ToolRegistry
{
    /** @var class-string<AiTool>[] */
    private const TOOLS = [
        // Phase 1: projects, tasks, bugs, people
        Tools\ListProjects::class,
        Tools\ListTasks::class,
        Tools\ListBugs::class,
        Tools\ListTeamMembers::class,
        Tools\CreateTaskTool::class,
        Tools\AssignTaskTool::class,
        Tools\ChangeTaskStageTool::class,
        Tools\AssignBugTool::class,
        // Phase 2: manager workflows
        Tools\CreateBugTool::class,
        Tools\ChangeBugStatusTool::class,
        Tools\ListSprints::class,
        Tools\AddTasksToSprintTool::class,
        Tools\RemoveTasksFromSprintTool::class,
        // Epics (big pieces of work grouping tasks and bugs) and meetings
        Tools\ListEpics::class,
        Tools\CreateEpicTool::class,
        Tools\SetEpicTool::class,
        Tools\ListMeetings::class,
        Tools\ScheduleMeetingTool::class,
        Tools\ListTimesheetApprovals::class,
        Tools\DecideTimesheetsTool::class,
        Tools\ListExpenseApprovals::class,
        Tools\DecideExpensesTool::class,
        Tools\GetBudgetStatus::class,
        Tools\GetProjectReport::class,
        // Phase 3: owner tools (and managers where their permissions allow)
        Tools\CreateProjectTool::class,
        Tools\AddProjectMembersTool::class,
        Tools\ListInvoices::class,
        Tools\SendInvoiceTool::class,
        Tools\ListContracts::class,
        Tools\InviteUserTool::class,
        Tools\GetRevenueSummary::class,
        Tools\SearchKnowledgeBase::class,
        // Who created or changed a record (history log)
        Tools\GetRecordHistory::class,
        // Module tools, phase 1: finance
        Tools\CreateInvoiceTool::class,
        Tools\UpdateInvoiceTool::class,
        Tools\DeleteInvoiceTool::class,
        Tools\MarkInvoicePaidTool::class,
        Tools\ListExpenses::class,
        Tools\CreateExpenseTool::class,
        Tools\UpdateExpenseTool::class,
        Tools\DeleteExpenseTool::class,
        Tools\CreateBudgetTool::class,
        Tools\UpdateBudgetTool::class,
        // Module tools, phase 1: time
        Tools\ListMyTime::class,
        Tools\LogTimeTool::class,
        Tools\UpdateTimeEntryTool::class,
        Tools\DeleteTimeEntryTool::class,
        Tools\SubmitTimesheetTool::class,
        Tools\StartTimerTool::class,
        Tools\StopTimerTool::class,
        // Attached files (the + button)
        Tools\ReadAttachment::class,
        Tools\GetSheetRows::class,
        Tools\ImportBugsFromSheet::class,
        Tools\ImportTasksFromSheet::class,
        Tools\PlanTasksFromDocument::class,
        // What the user sees: pages, filtered lists and records (voice and chat)
        Tools\OpenPageTool::class,
        Tools\ShowListTool::class,
        Tools\OpenRecordTool::class,
        // Full module coverage (2026-11-10): editing tasks, bugs, projects and epics
        Tools\UpdateTaskTool::class,
        Tools\UpdateBugTool::class,
        Tools\AddCommentTool::class,
        Tools\UpdateChecklistTool::class,
        Tools\UpdateProjectTool::class,
        Tools\RemoveProjectMembersTool::class,
        Tools\UpdateEpicTool::class,
        // Milestones
        Tools\ListMilestones::class,
        Tools\CreateMilestoneTool::class,
        Tools\UpdateMilestoneTool::class,
        // Sprints: plan, start, complete
        Tools\CreateSprintTool::class,
        Tools\StartSprintTool::class,
        Tools\CompleteSprintTool::class,
        // Meetings: move and cancel
        Tools\RescheduleMeetingTool::class,
        Tools\CancelMeetingTool::class,
        // Calendar, Reports and the AI tools page (Risk Radar, Resource Conflicts, Standup Bot)
        Tools\GetCalendar::class,
        Tools\GetTimesheetReport::class,
        Tools\GetRiskRadar::class,
        Tools\GetResourceConflicts::class,
        Tools\GetStandup::class,
        // Knowledge Base articles and contract status
        Tools\CreateKbArticleTool::class,
        Tools\UpdateKbArticleTool::class,
        Tools\ChangeContractStatusTool::class,
    ];

    /** @var AiTool[]|null */
    private ?array $tools = null;

    /** @return AiTool[] keyed by name */
    public function all(): array
    {
        if ($this->tools === null) {
            $this->tools = [];
            foreach (self::TOOLS as $class) {
                $tool = app($class);
                $this->tools[$tool->name()] = $tool;
            }
        }

        return $this->tools;
    }

    public function find(string $name): ?AiTool
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @param  string|string[]|null  $topics  Topics keys: only those topics' tools (still permission-filtered); none means all
     * @return AiTool[] keyed by name
     */
    public function forUser(User $user, bool $readOnly = false, string|array|null $topics = null): array
    {
        if (!AiAccess::canUse($user)) {
            return [];
        }

        $topics = array_values(array_filter((array) $topics, fn ($t) => Topics::valid($t)));
        $inTopic = $topics ? Topics::toolNames($topics) : null;

        return array_filter(
            $this->all(),
            fn (AiTool $tool) => $tool->allowedFor($user)
                && !($readOnly && $tool->isWrite())
                && ($inTopic === null || in_array($tool->name(), $inTopic, true))
        );
    }
}
