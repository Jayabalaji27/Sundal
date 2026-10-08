<?php

namespace App\Services\Ai\Tools;

use Illuminate\Database\Eloquent\Model;

/**
 * @param  array<string, mixed>|null  $undo  what undo() needs to put things back;
 *                                           null when the action cannot be undone
 */
final class ToolOutcome
{
    public function __construct(
        public readonly string $message,
        public readonly ?Model $subject = null,
        public readonly ?string $link = null,
        public readonly ?array $undo = null,
    ) {}
}
