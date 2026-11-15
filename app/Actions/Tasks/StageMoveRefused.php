<?php

namespace App\Actions\Tasks;

use RuntimeException;

/**
 * A stage move the process does not allow. $needsReason marks the one refusal
 * the user can fix on the spot: a backward move sent without a reason.
 */
class StageMoveRefused extends RuntimeException
{
    public function __construct(string $message, public readonly bool $needsReason = false)
    {
        parent::__construct($message);
    }
}
