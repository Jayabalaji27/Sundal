/**
 * Speech to text, two ways with the same handle:
 *
 *  - server:  record with MediaRecorder and send the clip to Sundal, which
 *             transcribes it with the company's own OpenAI or Gemini key.
 *             Recording ends by itself after a pause in speech.
 *  - browser: the browser's own speech recognition (Chrome, Edge, Safari),
 *             for workspaces whose provider cannot take audio.
 *
 * `result` resolves with the words heard, or '' when nothing was said.
 */

export interface ListenHandle {
    result: Promise<string>;
    /** Finish now and use what was heard (push-to-talk released). */
    stop(): void;
    /** Throw it away. */
    cancel(): void;
}

/** Thrown when the user or the browser blocks the microphone. */
export class MicBlockedError extends Error {}

/** The parts of the Web Speech API used here (not in TypeScript's DOM types). */
interface SpeechResultList {
    length: number;
    [index: number]: { isFinal: boolean; 0?: { transcript: string } };
}

interface SpeechRecognitionLike {
    lang: string;
    interimResults: boolean;
    continuous: boolean;
    maxAlternatives: number;
    onresult: ((event: { results: SpeechResultList }) => void) | null;
    onerror: ((event: { error: string }) => void) | null;
    onend: (() => void) | null;
    start(): void;
    stop(): void;
    abort(): void;
}

type SpeechRecognitionClass = new () => SpeechRecognitionLike;

function recognitionClass(): SpeechRecognitionClass | undefined {
    if (typeof window === 'undefined') return undefined;
    const speechWindow = window as unknown as { SpeechRecognition?: SpeechRecognitionClass; webkitSpeechRecognition?: SpeechRecognitionClass };
    return speechWindow.SpeechRecognition ?? speechWindow.webkitSpeechRecognition;
}

/** RMS level above which the mic counts as speech. */
const SPEECH_LEVEL = 0.025;
/** Silence after speech that ends the recording (shorter = quicker turns; too short cuts people off mid-pause). */
const END_SILENCE_MS = 800;
/** Give up when nothing is said for this long. */
const NO_SPEECH_MS = 7000;
/** Browser speech: finish when the words stop changing for this long (Chrome alone waits longer). */
const QUICK_END_MS = 800;

/** Talking over the reply: louder than this (and than the reply's own echo)… */
const BARGE_MIN_LEVEL = 0.06;
/** …for this long interrupts it. */
const BARGE_HOLD_MS = 400;
/** The first moments of the reply set how loud its own echo is in the mic. */
const BARGE_CALIBRATE_MS = 700;

export function browserSpeechSupported(): boolean {
    return recognitionClass() !== undefined;
}

export function recordingSupported(): boolean {
    return typeof window !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && typeof MediaRecorder !== 'undefined';
}

function recorderType(): string | undefined {
    return ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'].find(type => MediaRecorder.isTypeSupported?.(type));
}

/**
 * Record one utterance and transcribe it on the server.
 *
 * @param transcribe sends the clip to Sundal and returns the text
 */
export function listenWithServer(options: {
    maxSeconds: number;
    onLevel: (level: number) => void;
    onRecorded: () => void;
    transcribe: (clip: Blob) => Promise<string>;
}): ListenHandle {
    let finish: (keep: boolean) => void = () => undefined;

    const result = new Promise<string>((resolve, reject) => {
        let settled = false;
        let stream: MediaStream | null = null;
        let context: AudioContext | null = null;
        let timer = 0;
        let recorder: MediaRecorder | null = null;
        let keep = true;
        let spoke = false;
        const chunks: Blob[] = [];

        const cleanup = () => {
            window.clearInterval(timer);
            stream?.getTracks().forEach(track => track.stop());
            context?.close().catch(() => undefined);
            options.onLevel(0);
        };

        finish = (keepIt: boolean) => {
            keep = keepIt;
            if (recorder && recorder.state !== 'inactive') {
                recorder.stop();
            } else if (!settled) {
                // Stopped before the mic was ready.
                settled = true;
                cleanup();
                resolve('');
            }
        };

        navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }).then(media => {
            stream = media;
            if (settled) {
                cleanup();
                return;
            }

            const type = recorderType();
            recorder = new MediaRecorder(media, type ? { mimeType: type } : undefined);
            recorder.ondataavailable = event => event.data.size > 0 && chunks.push(event.data);
            recorder.onstop = () => {
                cleanup();
                if (settled) return;
                settled = true;
                if (!keep || !spoke || chunks.length === 0) {
                    resolve('');
                    return;
                }
                options.onRecorded();
                options.transcribe(new Blob(chunks, { type: recorder?.mimeType || type || 'audio/webm' })).then(resolve, reject);
            };
            recorder.start();

            // Loudness, to end the clip after a pause and to draw the mic ring.
            context = new AudioContext();
            const analyser = context.createAnalyser();
            analyser.fftSize = 1024;
            context.createMediaStreamSource(media).connect(analyser);
            const samples = new Float32Array(analyser.fftSize);
            const started = Date.now();
            let lastSpeech = 0;

            timer = window.setInterval(() => {
                analyser.getFloatTimeDomainData(samples);
                const level = Math.sqrt(samples.reduce((sum, s) => sum + s * s, 0) / samples.length);
                options.onLevel(Math.min(1, level * 8));

                const now = Date.now();
                if (level > SPEECH_LEVEL) {
                    spoke = true;
                    lastSpeech = now;
                }
                const ended = spoke && now - lastSpeech > END_SILENCE_MS;
                const silent = !spoke && now - started > NO_SPEECH_MS;
                if (ended || silent || now - started > options.maxSeconds * 1000) finish(true);
            }, 100);
        }, error => {
            settled = true;
            cleanup();
            reject(error?.name === 'NotAllowedError' || error?.name === 'SecurityError' ? new MicBlockedError(error.message) : error);
        });
    });

    return { result, stop: () => finish(true), cancel: () => finish(false) };
}

/** Listen with the browser's own speech recognition. */
export function listenWithBrowser(options: { lang: string; onInterim: (text: string) => void }): ListenHandle {
    const Recognition = recognitionClass();
    if (!Recognition) {
        return { result: Promise.resolve(''), stop: () => undefined, cancel: () => undefined };
    }
    const recognition = new Recognition();
    recognition.lang = options.lang;
    recognition.interimResults = true;
    recognition.continuous = false;
    recognition.maxAlternatives = 1;

    let finalText = '';
    let heard = '';
    let changedAt = 0;
    let cancelled = false;

    // Finish once the words stop changing for a moment, instead of waiting for
    // Chrome's own (longer) end of speech.
    const quickEnd = window.setInterval(() => {
        if (heard && Date.now() - changedAt > QUICK_END_MS) {
            window.clearInterval(quickEnd);
            recognition.stop();
        }
    }, 100);

    const result = new Promise<string>((resolve, reject) => {
        recognition.onresult = event => {
            let interim = '';
            finalText = '';
            for (let i = 0; i < event.results.length; i++) {
                const text = event.results[i][0]?.transcript ?? '';
                if (event.results[i].isFinal) finalText += text;
                else interim += text;
            }
            const now = (finalText + ' ' + interim).trim();
            if (now !== heard) {
                heard = now;
                changedAt = Date.now();
            }
            options.onInterim(now);
        };
        recognition.onerror = event => {
            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') reject(new MicBlockedError(event.error));
            // 'no-speech', 'aborted', 'network'…: end with what was heard.
        };
        recognition.onend = () => {
            window.clearInterval(quickEnd);
            options.onInterim('');
            // Stopped early: the last words may not be marked final yet.
            resolve(cancelled ? '' : finalText.trim() || heard);
        };
        recognition.start();
    });

    return {
        result,
        stop: () => recognition.stop(),
        cancel: () => {
            cancelled = true;
            window.clearInterval(quickEnd);
            recognition.abort();
        },
    };
}

/**
 * Listen to the mic while the reply is spoken, and call onSpeech once when
 * the user talks over it. Echo cancellation removes most of the reply from the
 * mic; what is left is measured in the first moments and the user must be
 * clearly louder than that. Returns a function that stops listening.
 */
export function watchForSpeech(onSpeech: () => void): () => void {
    let stopped = false;
    let stream: MediaStream | null = null;
    let context: AudioContext | null = null;
    let timer = 0;

    const stop = () => {
        stopped = true;
        window.clearInterval(timer);
        stream?.getTracks().forEach(track => track.stop());
        context?.close().catch(() => undefined);
    };

    if (!recordingSupported()) return stop;

    navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } }).then(media => {
        stream = media;
        if (stopped) {
            stop();
            return;
        }
        context = new AudioContext();
        const analyser = context.createAnalyser();
        analyser.fftSize = 1024;
        context.createMediaStreamSource(media).connect(analyser);
        const samples = new Float32Array(analyser.fftSize);
        const started = Date.now();
        let echo = 0;
        let loudSince = 0;

        timer = window.setInterval(() => {
            analyser.getFloatTimeDomainData(samples);
            const level = Math.sqrt(samples.reduce((sum, s) => sum + s * s, 0) / samples.length);
            const now = Date.now();

            if (now - started < BARGE_CALIBRATE_MS) {
                echo = Math.max(echo, level);
                return;
            }
            if (level > Math.max(BARGE_MIN_LEVEL, echo * 2)) {
                loudSince ||= now;
                if (now - loudSince >= BARGE_HOLD_MS) {
                    stop();
                    onSpeech();
                }
            } else {
                loudSince = 0;
            }
        }, 50);
    }, () => undefined);

    return stop;
}
