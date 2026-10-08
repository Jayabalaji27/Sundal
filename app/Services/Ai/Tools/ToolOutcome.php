<?php

namespace App\Services\Ai\Tools;

use Illuminate\Database\Eloquent\Model;

final class ToolOutcome
{
    public function __construct(
        public readonly string $message,
        public readonly ?Model $subject = null,
        public readonly ?string $link = null,
    ) {}
}
