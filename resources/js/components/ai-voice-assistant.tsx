import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { AudioLines, Sparkles } from 'lucide-react';
import { VoiceCaption, VoiceControls, VoiceOrb, useVoiceStatusText } from '@/components/ai-voice-orb';
import { useVoice } from '@/lib/ai-voice/store';
import { endLive, setFloating, setVoiceMode, startLive, voiceEngine, type VoiceMode } from '@/lib/ai-voice/assistant';
import { aiModeUrl, openAiModeTab } from '@/lib/ai-mode';
import { useIsMobile } from '@/hooks/use-mobile';

declare const route: any;

/**
 * Voice mode on every Sundal page, for owners and managers with the AI
 * Assistant: a small launcher at the bottom right (or Alt+V), and voice mode
 * floating over the page once it runs, so the user keeps talking while the
 * assistant opens pages for them. Docked voice mode is drawn by the AI
 * Assistant page itself.
 */
export function AiVoiceAssistant() {
    const { t } = useTranslation();
    const { auth, features } = usePage<{ auth?: { aiAssistant?: string | null; aiVoice?: VoiceMode | null }; features?: { ai_assistant?: boolean } }>().props;
    // Off when the AI Assistant is switched off for this installation (config/features.php: ai_assistant).
    const mode: VoiceMode | null = features?.ai_assistant && auth?.aiAssistant === 'allowed' ? auth?.aiVoice ?? null : null;
    const engine = voiceEngine(mode);
    const voice = useVoice();
    const statusText = useVoiceStatusText(voice);
    const isMobile = useIsMobile();

    let onAssistantPage = false;
    try {
        onAssistantPage = route().current('ai-assistant.*');
    } catch {
        // Ziggy not ready: treat as another page.
    }

    useEffect(() => {
        if (mode) setVoiceMode(mode);
    }, [mode]);

    // Lost access (workspace without the assistant): end voice mode.
    useEffect(() => {
        if (!mode && voice.live) endLive();
    }, [mode, voice.live]);

    // Docked voice mode lives on the assistant page; anywhere else it floats.
    useEffect(() => {
        if (voice.live && !voice.floating && !onAssistantPage) setFloating(true);
    }, [voice.live, voice.floating, onAssistantPage]);

    // Alt+V starts and ends voice mode.
    useEffect(() => {
        if (!engine) return;
        const onKey = (event: KeyboardEvent) => {
            if (!event.altKey || event.code !== 'KeyV' || event.repeat) return;
            event.preventDefault();
            if (voice.live) endLive();
            else startLive({ floating: !onAssistantPage });
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [engine, voice.live, onAssistantPage]);

    if (!mode || !engine) return null;

    if (voice.live && voice.floating) {
        // Expand: carry this conversation and voice mode over to AI mode (the header
        // switch turns on by itself when that tab opens). The AI tab takes the mic, so
        // voice mode ends here. Phones have no second tab: AI mode replaces this page.
        const expand = () => {
            const start = { conversationId: voice.conversationId, voice: true };
            if (isMobile) {
                endLive();
                router.visit(aiModeUrl(start));
                return;
            }
            if (openAiModeTab(start)) {
                endLive();
                return;
            }
            // The browser blocked the new tab: open the assistant here instead.
            setFloating(false);
            if (!onAssistantPage) router.visit(route('ai-assistant.index'));
        };

        return (
            <section
                aria-label={t('Voice mode')}
                className="fixed bottom-4 right-4 z-40 flex max-h-[min(70vh,560px)] w-[340px] max-w-[calc(100vw-2rem)] flex-col gap-3 overflow-hidden rounded-2xl border bg-background/95 p-3 shadow-2xl backdrop-blur print:hidden"
            >
                <div className="flex items-center gap-3">
                    <VoiceOrb voice={voice} size="sm" />
                    <div className="min-w-0 flex-1">
                        <p className="flex items-center gap-1.5 text-xs font-semibold">
                            <Sparkles className="h-3.5 w-3.5 text-violet-500" />
                            {t('Voice mode')}
                        </p>
                        <p className="truncate text-xs text-muted-foreground" aria-live="polite">{statusText}</p>
                    </div>
                    <VoiceControls voice={voice} onExpand={expand} compact />
                </div>
                <div className="min-h-0 overflow-y-auto">
                    <VoiceCaption voice={voice} compact />
                </div>
            </section>
        );
    }

    // The assistant page has its own mic and voice-mode buttons in the message box.
    if (voice.live || onAssistantPage) return null;

    return (
        <button
            type="button"
            onClick={() => startLive({ floating: true })}
            className="fixed bottom-4 right-4 z-40 flex h-11 items-center gap-2 rounded-full bg-violet-600 pl-3 pr-4 text-sm font-medium text-white shadow-lg transition-colors hover:bg-violet-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-2 print:hidden"
            title={t('Talk to the assistant (Alt+V)')}
            aria-label={t('Start voice mode')}
        >
            <AudioLines className="h-5 w-5" />
            <span className="hidden sm:inline">{t('Voice')}</span>
        </button>
    );
}
