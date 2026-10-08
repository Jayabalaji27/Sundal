<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Tools\AiTool;
use App\Services\Ai\Tools\AssignBugTool;
use App\Services\Ai\Tools\AssignTaskTool;
use App\Services\Ai\Tools\ChangeTaskStageTool;
use App\Services\Ai\Tools\CreateTaskTool;
use App\Services\Ai\Tools\ListBugs;
use App\Services\Ai\Tools\ListProjects;
use App\Services\Ai\Tools\ListTasks;
use App\Services\Ai\Tools\ListTeamMembers;

/**
 * Every tool the assistant knows. The model only ever sees the tools the
 * signed-in user's workspace permissions allow, so changing a role in
 * RoleSeeder changes what its assistant can do.
 */
class ToolRegistry
{
    /** @var class-string<AiTool>[] */
    private const TOOLS = [
        ListProjects::class,
        ListTasks::class,
        ListBugs::class,
        ListTeamMembers::class,
        CreateTaskTool::class,
        AssignTaskTool::class,
        ChangeTaskStageTool::class,
        AssignBugTool::class,
    ];

    /** @return AiTool[] keyed by name */
    public function all(): array
    {
        $tools = [];
        foreach (self::TOOLS as $class) {
            $tool = app($class);
            $tools[$tool->name()] = $tool;
        }

        return $tools;
    }

    public function find(string $name): ?AiTool
    {
        return $this->all()[$name] ?? null;
    }

    /** @return AiTool[] keyed by name */
    public function forUser(User $user, bool $readOnly = false): array
    {
        return array_filter(
            $this->all(),
            fn (AiTool $tool) => $tool->allowedFor($user) && !($readOnly && $tool->isWrite())
        );
    }
}
