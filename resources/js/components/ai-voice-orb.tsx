import { useTranslation } from 'react-i18next';
import { Maximize2, Mic, MicOff, PictureInPicture2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ConfirmCard, FormattedText } from '@/components/ai-confirm-card';
import { endLive, setMicMuted, tapOrb } from '@/lib/ai-voice/assistant';
import { updateCard, type VoiceState } from '@/lib/ai-voice/store';

/**
 * The parts of voice mode shared by the docked view (over the AI Assistant
 * chat) and the floating card (over any Sundal page).
 */

/** What voice mode is doing, in a few words. */
export function useVoiceStatusText(voice: VoiceState): string {
    const { t } = useTranslation();

    if (voice.idlePaused) return t('Paused. Tap the orb to continue.');
    if (voice.micMuted) return t('Mic off. Tap the orb to talk.');
    switch (voice.status) {
        case 'listening':
            return voice.interim || t('Listening…');
        case 'transcribing':
            return t('Getting your words…');
        case 'thinking':
            return t('Working on it…');
        case 'speaking':
            return t('Speaking. Tap the orb to interrupt.');
        default:
            return t('Starting…');
    }
}

/**
 * The orb: breathes while listening (and grows with the voice), spins while
 * working, pulses while speaking. Tapping it interrupts, finishes listening,
 * or continues after a pause.
 */
export function VoiceOrb({ voice, size = 'lg' }: { voice: VoiceState; size?: 'sm' | 'lg' }) {
    const { t } = useTranslation();
    const paused = voice.micMuted || voice.idlePaused;
    const working = voice.status === 'thinking' || voice.status === 'transcribing';
    const scale = voice.status === 'listening' ? 1 + Math.min(voice.level, 1) * 0.25 : 1;
    const dimensions = size === 'lg' ? 'h-40 w-40' : 'h-12 w-12';

    const colors = paused
        ? 'from-slate-300 to-slate-400 dark:from-slate-600 dark:to-slate-700'
        : voice.status === 'speaking'
            ? 'from-sky-400 via-violet-500 to-fuchsia-500'
            : 'from-violet-400 via-indigo-500 to-sky-400';

    const motion = paused ? '' : working ? 'animate-spin [animation-duration:3s]' : voice.status === 'speaking' ? 'animate-pulse' : '';

    return (
        <button
            type="button"
            onClick={tapOrb}
            className={`relative shrink-0 rounded-full focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-violet-400/50 ${dimensions}`}
            aria-label={voice.status === 'speaking' ? t('Interrupt') : paused ? t('Continue') : t('Voice mode')}
            title={voice.status === 'speaking' ? t('Interrupt') : paused ? t('Continue') : undefined}
        >
            {voice.status === 'listening' && !paused && (
                <span aria-hidden className="absolute inset-0 animate-ping rounded-full bg-violet-400/20 [animation-duration:2s]" />
            )}
            <span
                aria-hidden
                className={`absolute inset-0 rounded-full bg-gradient-to-br shadow-xl transition-transform duration-150 ${colors} ${motion}`}
                style={{ transform: `scale(${scale})` }}
            />
            <span aria-hidden className="absolute inset-[18%] rounded-full bg-white/25 blur-md" />
            {paused && <MicOff aria-hidden className={`absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-white ${size === 'lg' ? 'h-10 w-10' : 'h-5 w-5'}`} />}
        </button>
    );
}

/** Mute the mic, float or expand, end. */
export function VoiceControls({ voice, onFloat, onExpand, compact = false }: {
    voice: VoiceState;
    /** Docked: float over the app. Absent where floating is impossible (the AI mode tab). */
    onFloat?: () => void;
    /** Floating: back to the AI Assistant, docked. */
    onExpand?: () => void;
    compact?: boolean;
}) {
    const { t } = useTranslation();
    const size = compact ? 'h-8 w-8' : 'h-12 w-12';
    const icon = compact ? 'h-4 w-4' : 'h-5 w-5';

    return (
        <div className="flex items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="icon"
                className={`${size} rounded-full ${voice.micMuted ? 'border-rose-300 bg-rose-50 text-rose-600 hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-950/40' : ''}`}
                onClick={() => setMicMuted(!voice.micMuted)}
                aria-pressed={voice.micMuted}
                aria-label={voice.micMuted ? t('Turn the mic on') : t('Turn the mic off')}
                title={voice.micMuted ? t('Turn the mic on') : t('Turn the mic off')}
            >
                {voice.micMuted ? <MicOff className={icon} /> : <Mic className={icon} />}
            </Button>
            {onFloat && (
                <Button type="button" variant="outline" size="icon" className={`${size} rounded-full`} onClick={onFloat} aria-label={t('Float over Sundal')} title={t('Float over Sundal')}>
                    <PictureInPicture2 className={icon} />
                </Button>
            )}
            {onExpand && (
                <Button type="button" variant="outline" size="icon" className={`${size} rounded-full`} onClick={onExpand} aria-label={t('Continue in AI mode')} title={t('Continue in AI mode')}>
                    <Maximize2 className={icon} />
                </Button>
            )}
            <Button
                type="button"
                variant="destructive"
                size="icon"
                className={`${size} rounded-full`}
                onClick={endLive}
                aria-label={t('End voice mode')}
                title={t('End voice mode')}
            >
                <X className={icon} />
            </Button>
        </div>
    );
}

/** Voice mode docked over the AI Assistant chat, like ChatGPT's: the orb in the middle, controls below. */
export function VoiceDocked({ voice, onFloat }: { voice: VoiceState; onFloat?: () => void }) {
    const { t } = useTranslation();
    const statusText = useVoiceStatusText(voice);

    return (
        <div className="absolute inset-0 z-10 flex flex-col items-center gap-6 bg-background/95 px-4 py-8 backdrop-blur-sm" role="region" aria-label={t('Voice mode')}>
            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{t('Voice mode')}</p>
            <div className="flex min-h-0 w-full max-w-xl flex-1 flex-col items-center justify-center gap-6">
                <VoiceOrb voice={voice} size="lg" />
                <p className="text-center text-sm text-muted-foreground" aria-live="polite">{statusText}</p>
                <div className="min-h-0 w-full overflow-y-auto">
                    <VoiceCaption voice={voice} />
                </div>
            </div>
            <VoiceControls voice={voice} onFloat={onFloat} />
        </div>
    );
}

/** The last thing said, the last reply, and a card that is waiting. */
export function VoiceCaption({ voice, compact = false }: { voice: VoiceState; compact?: boolean }) {
    const userAt = voice.messages.findLastIndex(m => m.role === 'user');
    const replyAt = voice.messages.findLastIndex(m => m.role === 'assistant');
    const lastUser = userAt >= 0 ? voice.messages[userAt] : undefined;
    // A reply older than the user's latest words is stale while the new answer is on its way.
    const lastReply = replyAt > userAt ? voice.messages[replyAt] : undefined;
    const waiting = [...voice.messages].reverse().find(m => m.role === 'assistant' && m.cards.some(c => c.status === 'pending'))
        ?.cards.filter(c => c.status === 'pending') ?? [];

    return (
        <div className={`w-full space-y-2 ${compact ? 'text-xs' : 'text-sm'}`} aria-live="polite">
            {lastUser && <p className="line-clamp-2 text-muted-foreground">“{lastUser.content}”</p>}
            {lastReply?.content && (
                <div className={`${compact ? 'line-clamp-3' : 'max-h-40 overflow-y-auto'} whitespace-pre-wrap break-words ${lastReply.error ? 'text-destructive' : ''}`}>
                    {lastReply.error ? lastReply.content : <FormattedText text={lastReply.content} />}
                </div>
            )}
            {voice.notice && <p className="text-amber-700 dark:text-amber-400" role="alert">{voice.notice}</p>}
            {waiting.map(card => <ConfirmCard key={card.id} card={card} onChange={updateCard} />)}
        </div>
    );
}
