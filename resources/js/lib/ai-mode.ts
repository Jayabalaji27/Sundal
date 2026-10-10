/**
 * AI mode: the AI Assistant in its own browser tab, opened with the "AI mode"
 * switch in the Sundal header. The Sundal tabs and the AI mode tab talk over
 * a BroadcastChannel (same browser, same site only):
 *
 *  AI tab  → Sundal tabs: 'ai-alive' (every few seconds), 'ai-closed'
 *  Sundal  → AI tab:       'app-alive', 'app-closing', 'close', 'signed-out'
 *  either  → the others:   'ping' (who is there?)
 *
 * The server enforces the same rules on its own (see App\Services\Ai\AiMode);
 * the channel only makes the switch and the locks react instantly.
 */

declare const route: any;

export const AI_MODE_WINDOW = 'sundal-ai-mode';

export type AiModeMessage =
    | { type: 'ai-alive' }
    | { type: 'ai-closed' }
    | { type: 'app-alive' }
    | { type: 'app-closing' }
    | { type: 'close' }
    | { type: 'signed-out' }
    | { type: 'ping' };

const CHANNEL = 'sundal-ai-mode';

/** How long after the last 'ai-alive' the AI tab counts as closed. */
export const AI_ALIVE_TIMEOUT_MS = 8000;
export const AI_ALIVE_EVERY_MS = 3000;

/** Null in browsers without BroadcastChannel; everything still works, just less instantly. */
export function openChannel(): BroadcastChannel | null {
    return typeof window !== 'undefined' && 'BroadcastChannel' in window ? new BroadcastChannel(CHANNEL) : null;
}

export function post(message: AiModeMessage): void {
    const channel = openChannel();
    channel?.postMessage(message);
    channel?.close();
}

/** Tell an open AI mode tab that the user signed out of Sundal. */
export function notifySignedOut(): void {
    post({ type: 'signed-out' });
}

/**
 * Open the AI mode tab, or bring it to the front if it is already open
 * (a named window is reused). Must run inside the click, or the browser
 * blocks it. Returns false when it was blocked.
 */
export function openAiModeTab(): boolean {
    const tab = window.open(route('ai-mode'), AI_MODE_WINDOW);
    tab?.focus();

    return !!tab;
}
