<?php

namespace App\Services\Ai;

/**
 * A provider that can hand over the reply text as it is written, so the
 * voice panel starts speaking the first sentence before the rest arrives.
 */
interface StreamsReplies extends AiProvider
{
    /**
     * Run the request like run(), calling $onDelta with each piece of reply text.
     *
     * @param  callable(string): void  $onDelta
     *
     * @throws AiProviderException
     */
    public function stream(AiRequest $request, callable $onDelta): AiResult;
}
