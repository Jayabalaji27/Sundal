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
 * Deliberately absent (stay on the normal screens): roles and permissions,
 * plans and payments, BYOA/API keys, webhooks, removing users, deleting a
 * workspace, any hard delete, creating sprints (removed from the product)
 * and creating contracts (needs an uploaded file).
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

    /** @return AiTool[] keyed by name */
    public function forUser(User $user, bool $readOnly = false): array
    {
        if (!AiAccess::canUse($user)) {
            return [];
        }

        return array_filter(
            $this->all(),
            fn (AiTool $tool) => $tool->allowedFor($user) && !($readOnly && $tool->isWrite())
        );
    }
}
