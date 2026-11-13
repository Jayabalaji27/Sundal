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

/**
 * Looser answers to a card, said in a few words: "okay that's fine, create it",
 * "yes go ahead and save it". Every word must be one of these or a filler, and
 * the answer must lean one way only, so "yes but make it high priority" still
 * goes to the AI.
 */
const ANSWER_WORDS: Record<'confirm' | 'cancel', string[]> = {
    confirm: [
        'yes', 'yeah', 'yep', 'yup', 'ya', 'yah', 'sure', 'ok', 'okay', 'okey', 'alright', 'confirm', 'confirmed', 'approve', 'approved',
        'proceed', 'create', 'save', 'submit', 'add', 'fine', 'good', 'perfect', 'correct', 'right', 'great', 'absolutely', 'definitely',
    ],
    cancel: ['no', 'nope', 'nah', 'cancel', 'cancelled', 'dont', 'discard', 'skip', 'forget'],
};

const FILLER_WORDS = [
    'please', 'it', 'its', 'that', 'thats', 'this', 'is', 'the', 'now', 'just', 'you', 'can', 'and', 'all', 'set', 'looks', 'sounds',
    'go', 'ahead', 'do', 'everything', 'then', 'so', 'thanks', 'thank', 'about', 'with', 'one', 'fine', 'good', 'ok', 'okay',
];

export function matchCommand(text: string): LocalCommand | null {
    const said = text.toLowerCase().replace(/[^\p{L}\p{N}\s]/gu, '').replace(/\s+/g, ' ').trim();

    const exact = (Object.keys(PHRASES) as LocalCommand[]).find(command => PHRASES[command].includes(said));
    if (exact) return exact;

    const words = said.split(' ').filter(Boolean);
    if (words.length === 0 || words.length > 10) return null;
    const known = new Set([...ANSWER_WORDS.confirm, ...ANSWER_WORDS.cancel, ...FILLER_WORDS]);
    if (!words.every(word => known.has(word))) return null;

    const confirms = words.some(word => ANSWER_WORDS.confirm.includes(word)) || ` ${said} `.includes(' go ahead ') || ` ${said} `.includes(' do it ');
    const cancels = words.some(word => ANSWER_WORDS.cancel.includes(word));
    if (confirms === cancels) return null;

    return confirms ? 'confirm' : 'cancel';
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
