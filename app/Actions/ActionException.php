<?php

namespace App\Actions;

use RuntimeException;

/**
 * An Action refused on a business rule (plan limit, role rule, wrong state).
 * The message is safe to show the user, from a screen or the AI Assistant.
 */
class ActionException extends RuntimeException
{
}
