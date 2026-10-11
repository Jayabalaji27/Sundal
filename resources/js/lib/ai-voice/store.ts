/**
 * Voice mode's state. It lives in this module, not in a component: Sundal's
 * layout remounts on every Inertia visit, and voice mode often opens another
 * page, so the conversation, the mic and the speech must outlive the
 * component. Nothing is kept across a full reload: voice mode is started
 * again with a click (the browser needs one before it may speak).
 */
import { useSyncExternalStore } from 'react';
import { type Message, type ToolCard } from '@/components/ai-confirm-card';

export type VoiceStatus = 'idle' | 'listening' | 'transcribing' | 'thinking' | 'speaking';

export interface VoiceState {
    /** Voice mode (live conversation) is on. */
    live: boolean;
    /** Floating over any Sundal page, or docked over the AI Assistant chat. */
    floating: boolean;
    /** The user paused the mic; voice mode stays on. */
    micMuted: boolean;
    /** Paused by itself after a long silence; tap the orb to continue. */
    idlePaused: boolean;
    conversationId: number | null;
    /** The latest messages of the voice conversation (newest last). */
    messages: Message[];
    status: VoiceStatus;
    /** What the browser heard so far (browser speech only). */
    interim: string;
    /** Mic loudness 0..1 while recording, for the orb. */
    level: number;
    /** A problem to show (mic blocked, provider error…). */
    notice: string | null;
}

const MAX_MESSAGES = 30;

let state: VoiceState = {
    live: false,
    floating: true,
    micMuted: false,
    idlePaused: false,
    conversationId: null,
    messages: [],
    status: 'idle',
    interim: '',
    level: 0,
    notice: null,
};

const listeners = new Set<() => void>();

export function getVoice(): VoiceState {
    return state;
}

export function setVoice(patch: Partial<VoiceState>): void {
    state = { ...state, ...patch };
    listeners.forEach(listener => listener());
}

/** Add new messages (or replace ones with the same id), keeping the newest. */
export function addMessages(fresh: Message[]): void {
    const ids = new Set(fresh.map(m => m.id));
    setVoice({ messages: [...state.messages.filter(m => !ids.has(m.id)), ...fresh].slice(-MAX_MESSAGES) });
}

/** A card changed (confirm, cancel, undo, edit): update it, and add the server's note. */
export function updateCard(card: ToolCard, note: Message | null): void {
    const messages = state.messages.map(m => ({ ...m, cards: m.cards.map(c => (c.id === card.id ? card : c)) }));
    setVoice({ messages });
    if (note && !messages.some(m => m.id === note.id)) addMessages([{ ...note, cards: note.cards ?? [] }]);
}

export function useVoice(): VoiceState {
    return useSyncExternalStore(
        listener => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        () => state,
        () => state,
    );
}
