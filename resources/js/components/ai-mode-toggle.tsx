import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Sparkles } from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { useIsMobile } from '@/hooks/use-mobile';
import { AI_ALIVE_TIMEOUT_MS, AI_MODE_WINDOW, type AiModeMessage, openAiModeTab, openChannel } from '@/lib/ai-mode';

declare const route: any;

/** How often an open Sundal tab tells the server it is still open while AI mode is on. */
const HEARTBEAT_MS = 30_000;

/**
 * "AI mode" switch in the Sundal header, like Outlook's "New Outlook".
 * On = the AI mode tab is open. Turning it on opens (or focuses) that tab;
 * turning it off closes it. While it is on, this tab checks in with the
 * server so AI mode knows Sundal is still open.
 */
export function AiModeToggle() {
    const { t } = useTranslation();
    const isMobile = useIsMobile();
    const { auth } = usePage().props as any;
    const status: string | null = auth?.aiAssistant ?? null;
    const isOwner = auth?.user?.workspace_role === 'owner';

    const [open, setOpen] = useState(false);
    const [blocked, setBlocked] = useState(false);
    const lastAlive = useRef(0);
    const lastHeartbeat = useRef(0);

    // Owners without the AI add-on see the switch too; it leads to the upgrade page.
    const visible = status === 'allowed' || (status === 'plan' && isOwner);

    const heartbeat = (force = false) => {
        if (!force && Date.now() - lastHeartbeat.current < HEARTBEAT_MS - 5000) return;
        lastHeartbeat.current = Date.now();
        axios.post(route('ai-mode.heartbeat')).catch(() => undefined);
    };

    useEffect(() => {
        if (!visible) return;
        const channel = openChannel();

        const onMessage = (event: MessageEvent<AiModeMessage>) => {
            switch (event.data?.type) {
                case 'ai-alive':
                    lastAlive.current = Date.now();
                    setOpen(true);
                    heartbeat();
                    break;
                case 'ai-closed':
                    setOpen(false);
                    break;
                case 'ping':
                    channel?.postMessage({ type: 'app-alive' } satisfies AiModeMessage);
                    break;
            }
        };
        channel?.addEventListener('message', onMessage);

        // Announce this tab (a locked AI tab unlocks), and find an AI tab already open.
        channel?.postMessage({ type: 'app-alive' } satisfies AiModeMessage);
        channel?.postMessage({ type: 'ping' } satisfies AiModeMessage);

        const timer = window.setInterval(() => {
            if (Date.now() - lastAlive.current > AI_ALIVE_TIMEOUT_MS) {
                setOpen(false);
            } else {
                heartbeat();
            }
        }, 2000);

        const onPageHide = () => channel?.postMessage({ type: 'app-closing' } satisfies AiModeMessage);
        window.addEventListener('pagehide', onPageHide);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('pagehide', onPageHide);
            channel?.removeEventListener('message', onMessage);
            channel?.close();
        };
    }, [visible]);

    if (!visible) return null;

    const toggle = () => {
        if (status === 'plan') {
            router.visit(route('ai-assistant.index'));
            return;
        }
        if (open) {
            const channel = openChannel();
            channel?.postMessage({ type: 'close' } satisfies AiModeMessage);
            channel?.close();
            setOpen(false);
            return;
        }
        // Phones: no second tab, AI mode replaces this page.
        if (isMobile) {
            router.visit(route('ai-mode'));
            return;
        }

        const opened = openAiModeTab();
        setBlocked(!opened);
        if (opened) {
            lastAlive.current = Date.now();
            setOpen(true);
            heartbeat(true);
        }
    };

    return (
        <div className="flex items-center gap-2">
            <label className="flex cursor-pointer items-center gap-1.5 text-xs font-medium" title={t('Open the AI Assistant in its own tab')}>
                <Sparkles className="h-3.5 w-3.5 text-violet-500" />
                <span className="hidden sm:inline">{t('AI mode')}</span>
                <Switch checked={open} onCheckedChange={toggle} aria-label={t('AI mode')} />
            </label>
            {blocked && (
                <a href={route('ai-mode')} target={AI_MODE_WINDOW} className="text-xs text-primary underline" onClick={() => setBlocked(false)}>
                    {t('Open AI mode')}
                </a>
            )}
        </div>
    );
}
