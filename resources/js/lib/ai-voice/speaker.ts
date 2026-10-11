/**
 * Spoken replies with the browser's own voices (speechSynthesis): free and
 * instant. Long text is spoken a sentence at a time, because some browsers
 * cut off a single long utterance.
 */

export function speechSynthesisSupported(): boolean {
    return typeof window !== 'undefined' && 'speechSynthesis' in window;
}

/** Reply text as it should sound: links become their label; markdown, list markers, tables and addresses are dropped. */
export function plainSpeech(text: string): string {
    return text
        .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
        .replace(/https?:\/\/\S+/g, '')
        .replace(/(^|\s)\/[\w/?=&.#-]+/g, '$1')
        .replace(/^\s*\|.*\|\s*$/gm, '')
        .replace(/[*_`#>|]+/g, '')
        .replace(/^\s*(?:[-•]|\d{1,3}[.)])\s+/gm, '')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * How clear a voice sounds. Neural / "Natural" voices (Edge), Google's voices
 * (Chrome) and Apple's enhanced voices are far clearer than the old offline
 * system voices (Windows David, Zira), so they win even in a nearby accent.
 */
function clarity(voice: SpeechSynthesisVoice): number {
    const name = voice.name.toLowerCase();
    if (/natural|neural/.test(name)) return 60;
    if (/premium|enhanced|siri/.test(name)) return 50;
    if (name.includes('google')) return 45;
    if (name.includes('online')) return 30;
    return 0;
}

let cachedVoice: { lang: string; voice: SpeechSynthesisVoice | undefined } | null = null;
if (speechSynthesisSupported()) {
    // Voices load after the page; pick again once they are there.
    window.speechSynthesis.addEventListener?.('voiceschanged', () => (cachedVoice = null));
}

/** The clearest voice for the language: same language and region first, then the same language. */
function voiceFor(lang: string): SpeechSynthesisVoice | undefined {
    if (cachedVoice?.lang === lang && cachedVoice.voice) return cachedVoice.voice;

    const wanted = lang.toLowerCase();
    const primary = wanted.split('-')[0];
    const score = (voice: SpeechSynthesisVoice) => {
        const own = voice.lang.toLowerCase().replace('_', '-');
        const match = own === wanted ? 30 : own.split('-')[0] === primary ? 20 : -1000;
        return match + clarity(voice);
    };
    const voice = window.speechSynthesis.getVoices()
        .filter(v => score(v) > 0)
        .sort((a, b) => score(b) - score(a))[0];

    cachedVoice = { lang, voice };
    return voice;
}

let speaking = 0;

/** Speak one piece; resolves when it ends, fails, or speech was stopped since `run` began. */
function utter(text: string, lang: string, voice: SpeechSynthesisVoice | undefined, run: number): Promise<void> {
    return new Promise<void>(resolve => {
        if (run !== speaking) return resolve();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = voice?.lang ?? lang;
        if (voice) utterance.voice = voice;
        utterance.rate = 1;
        utterance.onend = () => resolve();
        utterance.onerror = () => resolve();
        window.speechSynthesis.speak(utterance);
    });
}

/** Speak the text; resolves when done, stopped or impossible. */
export function speak(text: string, lang: string): Promise<void> {
    const plain = plainSpeech(text);
    if (!speechSynthesisSupported() || plain === '') return Promise.resolve();

    stopSpeaking();
    const run = ++speaking;
    const sentences = plain.match(/[^.!?]+[.!?]*/g)?.map(s => s.trim()).filter(Boolean) ?? [plain];
    const voice = voiceFor(lang);

    return sentences.reduce<Promise<void>>((previous, sentence) => previous.then(() => utter(sentence, lang, voice, run)), Promise.resolve());
}

/**
 * Where the first sentence in `text` ends: after . ! or ? followed by a space,
 * or at a line break. "3.5 h", "site.com/x" and a list number such as "1."
 * stay whole. Null when no sentence is complete yet.
 */
function sentenceEnd(text: string): { end: number; next: number } | null {
    const boundary = /[.!?](?=\s)|\n/g;
    for (let match = boundary.exec(text); match; match = boundary.exec(text)) {
        const before = text.slice(0, match.index);
        if (match[0] !== '\n' && /(?:^|\s)\d{1,3}$/.test(before)) continue; // "1." of a list
        const end = match.index + (match[0] === '\n' ? 0 : 1);
        const rest = text.slice(end).match(/^\s+/);
        return { end, next: end + (rest ? rest[0].length : 0) };
    }
    return null;
}

/** A reply that arrives in pieces (streamed): each sentence is spoken as soon as it is complete. */
export interface SpeechQueue {
    /** Add reply text; complete sentences start speaking. */
    push(text: string): void;
    /** Speak what is left; resolves when everything is spoken or speech was stopped. */
    finish(): Promise<void>;
    /** Say something that is not part of the reply ("One moment."); it does not count toward maxSentences. */
    aside(text: string): void;
    /** Something has been queued to speak. */
    started(): boolean;
}

/**
 * @param options.maxSentences  speak at most this many sentences of the reply (the rest stays on screen)
 * @param options.more          said once when sentences were left out, e.g. "The rest is on screen."
 */
export function speechQueue(lang: string, options: { maxSentences?: number; more?: string } = {}): SpeechQueue {
    stopSpeaking();
    const run = ++speaking;
    const voice = speechSynthesisSupported() ? voiceFor(lang) : undefined;
    const limit = options.maxSentences ?? Infinity;
    let buffer = '';
    let chain = Promise.resolve();
    let queued = false;
    let spoken = 0;
    let cut = false;

    const say = (sentence: string, counts = true) => {
        const plain = plainSpeech(sentence);
        if (!speechSynthesisSupported() || plain === '') return;
        if (counts && spoken >= limit) {
            cut = true;
            return;
        }
        if (counts) spoken++;
        queued = true;
        chain = chain.then(() => utter(plain, lang, voice, run));
    };

    return {
        push(text) {
            buffer += text;
            for (let found = sentenceEnd(buffer); found; found = sentenceEnd(buffer)) {
                say(buffer.slice(0, found.end));
                buffer = buffer.slice(found.next);
            }
        },
        finish() {
            say(buffer);
            buffer = '';
            if (cut && options.more) say(options.more, false);
            return chain;
        },
        aside: text => say(text, false),
        started: () => queued,
    };
}

export function stopSpeaking(): void {
    speaking++;
    if (speechSynthesisSupported()) window.speechSynthesis.cancel();
}
