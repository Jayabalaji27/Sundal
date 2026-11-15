<?php

namespace App\Services\Ai;

use RuntimeException;

/** The workspace has no AI provider connected, or has used its monthly token cap. */
class WorkspaceAiUnavailable extends RuntimeException
{
}
