<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

/**
 * A write tool whose card the user can change in place (the import table):
 * edit() folds the page's changes into the tool arguments, which are then
 * prepared again. Nothing runs until Confirm.
 */
interface EditableCard
{
    /**
     * @param  array<string, mixed>  $args  the card's current arguments
     * @param  array<string, mixed>  $changes  what the user changed on the card
     * @return array<string, mixed> the new arguments
     */
    public function edit(array $args, array $changes, User $user): array;
}
