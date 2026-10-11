/**
 * Short spoken replies handled on the device, with no AI call: answering a
 * confirm card, undo, and ending hands-free mode. Anything else goes to the
 * assistant.
 */
import { type ToolCard } from '@/components/ai-confirm-card';

export type LocalCommand = 'confirm' | 'cancel' | 'undo' | 'stop';

const PHRASES: Record<LocalCommand, string[]> = {
    confirm: ['yes', 'yes please', 'yeah', 'yep', 'sure', 'confirm', 'confirm it', 'confirmed', 'do it', 'go ahead', 'yes do it', 'yes confirm'],
    cancel: ['no', 'nope', 'no thanks', 'cancel', 'cancel it', 'cancel that', 'never mind', 'nevermind', 'dont'],
    undo: ['undo', 'undo it', 'undo that', 'undo please'],
    stop: ['stop', 'stop listening', 'thats all', 'that is all', 'goodbye', 'bye', 'thank you thats all'],
};

export function matchCommand(text: string): LocalCommand | null {
    const said = text.toLowerCase().replace(/[^\p{L}\p{N}\s]/gu, '').replace(/\s+/g, ' ').trim();

    return (Object.keys(PHRASES) as LocalCommand[]).find(command => PHRASES[command].includes(said)) ?? null;
}

/** Most items a card may touch and still be confirmed by voice. */
const MAX_VOICE_ITEMS = 10;

/**
 * A spoken "yes" may confirm only an everyday card: fully filled in, no
 * phrase to type (risky actions), no table to review (imports, plans) and
 * no long list of records. Everything else needs a look and a click.
 */
export function voiceConfirmable(card: ToolCard): boolean {
    return card.status === 'pending'
        && card.stage !== 'question'
        && !card.confirm_phrase
        && !card.view
        && !card.form_error
        && card.items.length <= MAX_VOICE_ITEMS
        && !(card.fields ?? []).some(field => field.error);
}
