<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiAccess;
use LogicException;

/**
 * One thing the assistant can do. Read tools return data straight away.
 * Write tools never change data when the model calls them: prepare()
 * validates and resolves names into a confirm card, and execute() runs
 * only after the user presses Confirm.
 */
abstract class AiTool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /** Any one of these workspace permissions allows the tool. */
    abstract public function permissions(): array;

    /** @return array<string, array<string, mixed>> see ToolSpec */
    public function parameters(): array
    {
        return [];
    }

    public function isWrite(): bool
    {
        return false;
    }

    public function allowedFor(User $user): bool
    {
        if (!AiAccess::canUse($user)) {
            return false;
        }

        foreach ($this->permissions() as $permission) {
            if ($user->hasWorkspacePermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** Read tools: the data handed to the model. @throws ToolInputException */
    public function run(array $args, User $user): array
    {
        throw new LogicException(static::class . ' is not a read tool.');
    }

    /** Write tools: build the confirm card. @throws ToolInputException */
    public function prepare(array $args, User $user): PreparedAction
    {
        throw new LogicException(static::class . ' is not a write tool.');
    }

    /**
     * Write tools: run the confirmed action with the stored payload.
     * Re-resolves every record, so anything deleted or moved since the
     * card was shown fails safely. @throws ToolInputException
     */
    public function execute(array $payload, User $user): ToolOutcome
    {
        throw new LogicException(static::class . ' is not a write tool.');
    }
}
