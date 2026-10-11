<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

/**
 * Changes what the user sees, never the data: the page opens a URL the
 * server built (a list, a filtered list, a record). Nothing is confirmed,
 * because nothing changes; the record lookups and permission checks are
 * the same as for the read tools.
 */
abstract class UiTool extends AiTool
{
    /**
     * @return array{url: string, label: string} a relative URL inside Sundal and what it shows
     *
     * @throws ToolInputException
     */
    abstract public function action(array $args, User $user): array;
}
