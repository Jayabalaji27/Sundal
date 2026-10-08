<?php

namespace App\Services\Ai;

/**
 * The one interface the assistant talks to. Each BYOA vendor is a driver
 * behind it, so switching vendor is a settings change, not a code change.
 *
 * A driver runs the model with the given tools until the model stops
 * calling tools or `maxSteps` is reached. Tool handlers decide what a call
 * does; write tools never change data here, they only queue a confirm card.
 */
interface AiProvider
{
    /** @throws AiProviderException */
    public function run(AiRequest $request): AiResult;
}
