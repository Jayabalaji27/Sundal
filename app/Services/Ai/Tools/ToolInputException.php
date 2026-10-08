<?php

namespace App\Services\Ai\Tools;

use RuntimeException;

/**
 * The tool cannot go ahead with these arguments: a name matched nothing or
 * several records, a date is invalid, and so on. The message goes back to
 * the model so it can ask the user, and is safe to show the user.
 */
class ToolInputException extends RuntimeException
{
}
