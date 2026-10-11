/**
 * Voice mode, a live conversation like ChatGPT's: listen → (a spoken
 * "yes"/"undo" handled here, or) ask the AI Assistant → open what the reply
 * put on screen → speak the reply → listen again, until the user ends it.
 * Also the message box's dictation mic, which only turns speech into text.
 *
 * It uses the assistant's normal routes, so every rule stays on the server:
 * permissions, confirm cards, undo, rate limits and the AI session. The only
 * extras sent are the page on screen and "voice: true" (short spoken replies).
 */
import axios, { isAxiosError } from 'axios';
import i18next from 'i18next';
import { router } from '@inertiajs/react';
import { errorMessage, isSundalPath, type Message, type ToolCard } from '@/components/ai-confirm-card';
import { post as postToTabs } from '@/lib/ai-mode';
import { addMessages, getVoice, setVoice, updateCard } from './store';
import { MicBlockedError, browserSpeechSupported, listenWithBrowser, listenWithServer, recordingSupported, type ListenHandle } from './recognizer';
import { speak, speechQueue, stopSpeaking } from './speaker';
import { matchCommand, voiceConfirmable, type LocalCommand } from './commands';

declare const route: any;

/** How the workspace turns speech into text (auth.aiVoice). */
export type VoiceMode = 'server' | 'browser';

const MAX_RECORDING_SECONDS = 60;
/** Voice mode pauses itself after this long with nothing said, so a forgotten session does not keep the mic open. */
const IDLE_PAUSE_MS = 2 * 60 * 1000;
/** A listening turn that ends this fast with nothing heard (a recognizer error) waits before the next. */
const RETRY_DELAY_MS = 1000;
/**
 * Say "One moment." only when the reply is unusually slow to start. Free models
 * often take 3–6 s even for "hi", so a shorter wait made it play on almost every turn.
 */
const FILLER_AFTER_MS = 8000;
/** Sentences of a reply that are spoken; the full answer stays on screen. */
const SPOKEN_SENTENCES = 2;

/** What ai-assistant.send answers (also on 502, with the saved error message). */
interface SendResponse {
    conversation?: { id: number };
    messages?: Message[];
    pending?: boolean;
    error?: string;
}

/** An error answer to the streamed send, carrying what the server said (like an axios error's response). */
class StreamError extends Error {
    constructor(public status: number, public data: SendResponse & { message?: string }) {
        super(data?.error || data?.message || `HTTP ${status}`);
    }
}

/** The streamed send could not be made at all (the request never got an answer). */
class StreamUnavailable extends Error {}

const statusOf = (error: unknown): number | undefined =>
    error instanceof StreamError ? error.status : isAxiosError(error) ? error.response?.status : undefined;

/** The server's answer on an error response, or just its message. */
const failedResponse = (error: unknown): SendResponse => {
    if (error instanceof StreamError) return error.data?.conversation ? error.data : { error: error.message };
    return isAxiosError(error) && error.response?.data?.conversation ? error.response.data : { error: errorMessage(error, '') };
};

const t = (key: string, options?: Record<string, unknown>) => i18next.t(key, options) as string;

/** The AI mode tab: its own lock screen handles the AI session, and pages open in the Sundal tab. */
const inAiModeTab = () => axios.defaults.headers.common['X-AI-Mode'] === '1';

let mode: VoiceMode = 'browser';
let sessionReady = false;
let current: ListenHandle | null = null;
let lastSpeechAt = 0;

export function setVoiceMode(next: VoiceMode): void {
    mode = next;
}

/** The engine this browser can use, or null when it cannot listen at all. */
export function voiceEngine(forMode: VoiceMode | null = mode): VoiceMode | null {
    if (!forMode) return null;
    if (forMode === 'server' && recordingSupported()) return 'server';
    return browserSpeechSupported() ? 'browser' : null;
}

/** The user's language for speech, e.g. "en-US": the browser's, when it matches Sundal's. */
export function speechLang(): string {
    const app = (i18next.language || 'en').toLowerCase();
    const browser = typeof navigator !== 'undefined' ? navigator.language || '' : '';

    return browser.toLowerCase().startsWith(app.split('-')[0]) ? browser : app;
}

// ── Server session ───────────────────────────────────────────────────────────

async function ensureSession(force = false): Promise<void> {
    if (inAiModeTab() || (sessionReady && !force)) return;
    await axios.post(route('ai-assistant.voice.session'));
    sessionReady = true;
}

/** Run an assistant request; when the AI session ended or locked (423/409), start it again once. */
async function withSession<T>(request: () => Promise<T>): Promise<T> {
    await ensureSession();
    try {
        return await request();
    } catch (error) {
        const status = statusOf(error);
        if (inAiModeTab() || (status !== 423 && status !== 409)) throw error;
        await ensureSession(true);
        return request();
    }
}

async function transcribe(clip: Blob): Promise<string> {
    const extension = clip.type.includes('mp4') ? 'mp4' : clip.type.includes('ogg') ? 'ogg' : 'webm';
    const { data } = await withSession(() => {
        const form = new FormData();
        form.append('audio', clip, `voice.${extension}`);
        form.append('lang', speechLang());
        return axios.post(route('ai-assistant.voice.transcribe'), form);
    });

    return String(data.text ?? '').trim();
}

function startEngine(engine: VoiceMode, onInterim: (text: string) => void, onLevel: (level: number) => void, onRecorded: () => void): ListenHandle {
    return engine === 'server'
        ? listenWithServer({ maxSeconds: MAX_RECORDING_SECONDS, onLevel, onRecorded, transcribe })
        : listenWithBrowser({ lang: speechLang(), onInterim });
}

// ── Dictation (the message box's mic) ────────────────────────────────────────

/**
 * Turn one utterance into text for the message box; nothing is sent.
 * Null when this browser cannot listen.
 */
export function dictate(options: { onInterim: (text: string) => void; onLevel?: (level: number) => void; onTranscribing?: () => void }): ListenHandle | null {
    const engine = voiceEngine();
    if (!engine) return null;

    return startEngine(engine, options.onInterim, options.onLevel ?? (() => undefined), options.onTranscribing ?? (() => undefined));
}

// ── Voice mode ───────────────────────────────────────────────────────────────

/**
 * Start voice mode. From the chat it continues that chat (its messages,
 * cards included, so a spoken "yes" can answer them); from the launcher it
 * starts a new one.
 */
export function startLive(options: { floating: boolean; conversationId?: number | null; messages?: Message[] }): void {
    if (getVoice().live) {
        setVoice({ floating: options.floating });
        return;
    }

    setVoice({
        live: true,
        floating: options.floating,
        micMuted: false,
        idlePaused: false,
        notice: null,
        conversationId: options.conversationId ?? null,
        messages: (options.messages ?? []).slice(-30),
    });
    lastSpeechAt = Date.now();
    void listen();
}

export function endLive(): void {
    quiet();
    setVoice({ live: false, micMuted: false, idlePaused: false, notice: null });
}

export function setFloating(floating: boolean): void {
    setVoice({ floating });
}

/** Pause or resume the mic; voice mode stays on. */
export function setMicMuted(muted: boolean): void {
    if (muted) {
        current?.cancel();
        current = null;
        setVoice({ micMuted: true, level: 0, interim: '', status: getVoice().status === 'listening' ? 'idle' : getVoice().status });
        return;
    }
    setVoice({ micMuted: false, idlePaused: false });
    lastSpeechAt = Date.now();
    void listen();
}

/** The orb: interrupt the reply, finish listening now, or continue after a pause. */
export function tapOrb(): void {
    const { status, idlePaused, micMuted } = getVoice();
    if (status === 'speaking') {
        stopSpeaking();
        setVoice({ status: 'idle' });
        lastSpeechAt = Date.now();
        void listen();
    } else if (status === 'listening') {
        current?.stop();
    } else if (idlePaused || micMuted) {
        setMicMuted(false);
    }
}

/** Stop the mic and the voice (voice mode ending, or the page leaving). */
export function quiet(): void {
    current?.cancel();
    current = null;
    stopSpeaking();
    setVoice({ status: 'idle', level: 0, interim: '' });
}

/** One listening turn, and the next ones until voice mode ends or pauses. */
async function listen(): Promise<void> {
    const voice = getVoice();
    if (!voice.live || voice.micMuted || voice.idlePaused || current || voice.status === 'thinking' || voice.status === 'transcribing') return;

    if (Date.now() - lastSpeechAt > IDLE_PAUSE_MS) {
        setVoice({ idlePaused: true, status: 'idle' });
        return;
    }

    stopSpeaking();
    const engine = voiceEngine();
    if (!engine) {
        setVoice({ notice: t('Voice needs Chrome or Edge in this workspace.'), micMuted: true });
        return;
    }

    setVoice({ status: 'listening', notice: null, interim: '' });
    const started = Date.now();
    try {
        current = startEngine(engine, interim => setVoice({ interim }), level => setVoice({ level }), () => setVoice({ status: 'transcribing' }));
        const text = await current.result;
        current = null;
        setVoice({ level: 0, interim: '' });
        if (!getVoice().live) return;

        if (!text) {
            setVoice({ status: 'idle' });
            if (Date.now() - started < RETRY_DELAY_MS) await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS));
            void listen();
            return;
        }

        lastSpeechAt = Date.now();
        await handle(text);
    } catch (error) {
        current = null;
        const blocked = error instanceof MicBlockedError;
        setVoice({
            status: 'idle',
            level: 0,
            interim: '',
            micMuted: blocked || getVoice().micMuted,
            notice: blocked
                ? t('The microphone is blocked. Allow it for this site in the browser, then press the mic.')
                : errorMessage(error, t('Could not hear that. Please try again.')),
        });
        if (!blocked && getVoice().live) {
            await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS));
            void listen();
        }
    }
}

// ── One utterance ────────────────────────────────────────────────────────────

async function handle(text: string): Promise<void> {
    const command = matchCommand(text);
    if (command && (await runCommand(command, text))) return;
    await ask(text);
}

/** The pending cards of the latest reply that has cards. */
function waitingCards(): ToolCard[] {
    const withCards = [...getVoice().messages].reverse().find(m => m.role === 'assistant' && m.cards.length > 0);
    return withCards?.cards.filter(card => card.status === 'pending') ?? [];
}

/** A command handled without the AI. False when it does not apply (then the AI gets the words). */
async function runCommand(command: LocalCommand, text: string): Promise<boolean> {
    if (command === 'stop') {
        endLive();
        return true;
    }

    if (command === 'undo') {
        const done = [...getVoice().messages].reverse().flatMap(m => [...m.cards].reverse()).find(card => card.status === 'done' && card.can_undo);
        if (!done) return false;
        addMessages([localMessage('user', text)]);
        await cardAction(done, 'undo');
        return true;
    }

    const cards = waitingCards();
    // Nothing to answer, or a draft asking a question ("yes" may be its answer): the AI gets the words.
    if (cards.length === 0 || (cards.length === 1 && cards[0].stage === 'question')) return false;
    addMessages([localMessage('user', text)]);

    if (cards.length > 1) {
        await reply(t('There is more than one card waiting. Please use the buttons on the cards.'));
        return true;
    }
    if (command === 'confirm' && !voiceConfirmable(cards[0])) {
        await reply(cards[0].confirm_phrase
            ? t('This one needs the confirmation phrase typed on the card.')
            : t('Please check the card and press Confirm.'));
        return true;
    }

    await cardAction(cards[0], command === 'confirm' ? 'confirm' : 'cancel');
    return true;
}

/** Confirm, cancel or undo a card, as its buttons do, then say the outcome. */
async function cardAction(card: ToolCard, action: 'confirm' | 'cancel' | 'undo'): Promise<void> {
    setVoice({ status: 'thinking' });
    try {
        const { data } = await withSession(() => axios.post(route(`ai-assistant.tool-calls.${action}`, card.id)));
        updateCard(data.card, data.message);
        await reply(data.card.status === 'failed' ? data.card.error ?? t('The action failed.') : data.message?.content ?? t('Done.'));
    } catch (error) {
        setVoice({ status: 'idle' });
        await reply(errorMessage(error, t('Could not update this card.')));
    }
}

/**
 * Send the words to the AI Assistant, with the page on screen. The reply is
 * streamed: each sentence is spoken as soon as it arrives.
 */
async function ask(text: string): Promise<void> {
    const optimistic = localMessage('user', text);
    addMessages([optimistic]);
    setVoice({ status: 'thinking' });

    // A spoken reply stays short: two sentences; the full answer is on screen.
    const speech = speechQueue(speechLang(), { maxSentences: SPOKEN_SENTENCES, more: t('The rest is on screen.') });
    let streamed = false;
    const onDelta = (piece: string) => {
        if (!getVoice().live) return;
        streamed = true;
        speech.push(piece);
        if (getVoice().status === 'thinking') setVoice({ status: 'speaking' });
    };
    const filler = window.setTimeout(() => {
        if (!streamed && getVoice().live && getVoice().status === 'thinking') speech.aside(t('One moment.'));
    }, FILLER_AFTER_MS);

    const body = () => ({ conversation_id: getVoice().conversationId, content: text, voice: true, page: pageContext() });
    const send = async (): Promise<SendResponse> => {
        try {
            return await streamSend(body(), onDelta);
        } catch (error) {
            // Streaming is blocked on the way (an old proxy, an old browser): ask the normal way.
            // Only when the request never got an answer, so the message is never sent twice.
            if (!(error instanceof StreamUnavailable)) throw error;
            return (await axios.post<SendResponse>(route('ai-assistant.send'), body())).data;
        }
    };

    let data: SendResponse;
    try {
        data = await withSession(send);
    } catch (error) {
        if (statusOf(error) === 404 && getVoice().conversationId) {
            // The chat was deleted, or belongs to another workspace: start a new one.
            setVoice({ conversationId: null });
            try {
                data = await withSession(send);
            } catch (retryError) {
                data = failedResponse(retryError);
            }
        } else {
            data = failedResponse(error);
        }
    }
    window.clearTimeout(filler);
    if (!getVoice().live) return;

    if (!data?.conversation) {
        const reason = data?.error || t('The assistant could not answer. Please try again.');
        addMessages([{ ...localMessage('assistant', reason), error: true }]);
        await reply(reason);
        return;
    }

    setVoice({ conversationId: data.conversation.id, messages: getVoice().messages.filter(m => m.id !== optimistic.id) });
    let fresh: Message[] = data.messages ?? [];
    addMessages(fresh);

    if (data.pending) {
        try {
            fresh = await waitForReply(data.conversation.id, fresh.at(-1)?.id ?? 0);
            addMessages(fresh);
        } catch (error) {
            await reply(errorMessage(error, t('The assistant is taking too long. Please try again.')));
            return;
        }
    }

    const answers = fresh.filter(m => m.role === 'assistant');
    openOnScreen(answers.flatMap(m => m.actions ?? []).map(action => action.url));

    // The orb was tapped while the reply streamed in: that listening turn takes over.
    const status = getVoice().status;
    if (status !== 'thinking' && status !== 'speaking') return;

    // Nothing streamed (an answer without the AI, or a saved error): speak the reply now.
    if (!streamed) speech.push(answers.at(-1)?.content ?? '');
    setVoice({ status: 'speaking' });
    await speech.finish();
    if (getVoice().status !== 'speaking') return;

    setVoice({ status: 'idle' });
    void listen();
}

/** The headers axios would send (CSRF, AI mode), for the streamed request made with fetch. */
function requestHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream, application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    for (const [name, value] of Object.entries(axios.defaults.headers.common)) {
        if (typeof value === 'string') headers[name] = value;
    }
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    const xsrf = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf[1]);

    return headers;
}

/**
 * ai-assistant.send-stream: "delta" events carry reply text as it is written;
 * the last event ("done", or "failed" with the saved error) carries what
 * ai-assistant.send returns.
 */
async function streamSend(body: object, onDelta: (text: string) => void): Promise<SendResponse> {
    let response: Response;
    try {
        response = await fetch(route('ai-assistant.send-stream'), { method: 'POST', headers: requestHeaders(), credentials: 'same-origin', body: JSON.stringify(body) });
    } catch {
        throw new StreamUnavailable();
    }
    if (!response.ok || !response.body || !(response.headers.get('content-type') ?? '').includes('text/event-stream')) {
        const data = await response.json().catch(() => ({}));
        throw new StreamError(response.ok ? 500 : response.status, data);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        let cut = buffer.indexOf('\n\n');
        while (cut !== -1) {
            const block = buffer.slice(0, cut);
            buffer = buffer.slice(cut + 2);
            cut = buffer.indexOf('\n\n');

            const event = block.match(/^event: (.*)$/m)?.[1];
            const raw = block.match(/^data: (.*)$/m)?.[1];
            if (!event || raw === undefined) continue;
            const data = JSON.parse(raw);
            if (event === 'delta') onDelta(String(data.text ?? ''));
            else if (event === 'done' || event === 'failed') return data as SendResponse;
        }
    }

    throw new Error(t('The connection to the assistant was lost. Please try again.'));
}

/** Queue mode: poll the conversation until the assistant's reply arrives. */
async function waitForReply(conversationId: number, afterId: number): Promise<Message[]> {
    for (let attempt = 0; attempt < 120; attempt++) {
        await new Promise(resolve => setTimeout(resolve, 1500));
        const { data } = await withSession(() => axios.get(route('ai-assistant.conversations.show', conversationId)));
        const fresh: Message[] = data.messages.filter((m: Message) => m.id > afterId);
        if (fresh.some(m => m.role === 'assistant')) return fresh;
    }
    throw new Error(t('The assistant is taking too long. Please try again.'));
}

/**
 * Open the last page the reply asked for (Sundal paths only, never another
 * site). Docked over the chat, voice mode floats so it follows the user; in
 * the AI mode tab the Sundal tab opens it.
 */
function openOnScreen(urls: string[]): void {
    const url = urls.filter(isSundalPath).at(-1);
    if (!url) return;

    if (inAiModeTab()) {
        postToTabs({ type: 'navigate', url });
        return;
    }
    if (url === window.location.pathname + window.location.search) return;

    if (!getVoice().floating) setVoice({ floating: true });
    router.visit(url);
}

/** The page on screen, so "this project" or "these bugs" make sense to the model. */
function pageContext(): { route: string; url: string } | undefined {
    if (inAiModeTab()) return undefined;
    try {
        const name = route().current();
        if (!name) return undefined;
        const full = window.location.pathname + window.location.search;
        return { route: name, url: full.length <= 500 ? full : window.location.pathname.slice(0, 500) };
    } catch {
        return undefined;
    }
}

/** Speak the reply, then listen again. */
async function reply(text: string): Promise<void> {
    if (!getVoice().live) return;

    if (text) {
        setVoice({ status: 'speaking' });
        await speak(text, speechLang());
        // The orb was tapped while it spoke: that listening turn takes over.
        if (getVoice().status !== 'speaking') return;
    }

    setVoice({ status: 'idle' });
    void listen();
}

function localMessage(role: 'user' | 'assistant', content: string): Message {
    return { id: -Date.now() - Math.floor(Math.random() * 1000), role, content, created_at: new Date().toISOString(), cards: [] };
}
