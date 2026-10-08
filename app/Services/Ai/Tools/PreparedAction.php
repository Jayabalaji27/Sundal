<?php

namespace App\Services\Ai\Tools;

/**
 * What a write tool will do, shown on the confirm card before anything runs.
 *
 * @param  array<string, string>  $details  label => value rows on the card
 * @param  array<string, mixed>  $payload  ids and values execute() needs
 */
final class PreparedAction
{
    public function __construct(
        public readonly string $summary,
        public readonly array $details,
        public readonly array $payload,
    ) {}
}
