<?php

namespace App\Services\Ai\Forms;

use App\Models\User;

/**
 * A write tool whose confirm card is a form. The model may leave any of
 * these fields out; the server fills what the user said and the user picks
 * the rest on the card, so a missing or ambiguous value never needs another
 * AI call. The same form opens from the quick-action buttons with no AI.
 *
 * Field values reach prepare() as ordinary tool arguments: records as
 * "#id" (several as "#1;#2"), enums and dates as their value.
 */
interface HasForm
{
    /** Short name for the card and the quick-action button, e.g. "New task". */
    public function formTitle(): string;

    /** @return FormField[] the fields for this user (options may depend on their role) */
    public function formFields(User $user): array;
}
