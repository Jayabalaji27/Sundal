<?php

namespace App\Services\Ai\Tools;

/**
 * What a write tool will do, shown on the confirm card before anything runs.
 *
 * @param  array<string, string>  $details  label => value rows on the card
 * @param  array<string, mixed>  $payload  ids and values execute() needs
 * @param  string[]  $items  every record a bulk action touches, listed in full
 * @param  string|null  $confirmPhrase  risky actions: the user must type this to confirm
 * @param  array<string, mixed>|null  $view  a richer card the page draws by its 'type'
 *                                           (e.g. 'import': an editable table of rows)
 */
final class PreparedAction
{
    /** Bulk actions above this many records need a typed confirmation. */
    public const BULK_TYPED_CONFIRM_OVER = 10;

    public function __construct(
        public readonly string $summary,
        public readonly array $details,
        public readonly array $payload,
        public readonly array $items = [],
        public readonly ?string $confirmPhrase = null,
        public readonly ?array $view = null,
    ) {}
}
