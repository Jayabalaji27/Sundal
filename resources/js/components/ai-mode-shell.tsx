import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Clock, Loader2, Lock, LogIn, Moon, Play, RefreshCw, Sparkles, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { AI_ALIVE_EVERY_MS, type AiModeMessage, openChannel } from '@/lib/ai-mode';

declare const route: any;

export interface AiModeConfig {
    workspaceId: number;
    workspaceName: string | null;
    idleSeconds: number;
    warningSeconds: number;
    heartbeatSeconds: number;
}

/** Why AI mode is not usable right now (codes match App\Services\Ai\AiMode). */
type LockReason = 'locked_idle' | 'locked_app_closed' | 'workspace_changed' | 'ai_mode_ended' | 'signed_out' | 'turned_off';

/** After a Sundal tab closes, wait this long for it to come back (a reload) before locking. */
const RELOAD_GRACE_MS = 5000;
const PING_WAIT_MS = 2000;

/**
 * The AI mode tab: the AI Assistant full screen, with no Sundal sidebar.
 * Every assistant request from this tab carries X-AI-Mode and its workspace,
 * so the server can refuse it while the tab is locked. The tab locks (the
 * chat stays underneath) when the user is idle, when Sundal is closed, when
 * the workspace changes, or on sign-out.
 */
/** The round icon button at the top right of the AI mode bar (theme). */
const AI_MODE_ICON_BUTTON = 'h-8 w-8 rounded-full text-muted-foreground hover:text-foreground';

export function AiModeShell({ config, model, children }: {
    config: AiModeConfig;
    /** The connected model, shown next to the title. */
    model?: { provider: string; name: string } | null;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const [lock, setLock] = useState<LockReason | null>(null);
    const [secondsLeft, setSecondsLeft] = useState(config.idleSeconds);
    const lastAction = useRef(Date.now());
    const lastAppSeen = useRef(Date.now());
    const lockRef = useRef<LockReason | null>(null);
    lockRef.current = lock;

    // Tag this tab's requests and react to the server's locks.
    useEffect(() => {
        axios.defaults.headers.common['X-AI-Mode'] = '1';
        axios.defaults.headers.common['X-AI-Workspace'] = String(config.workspaceId);

        const interceptor = axios.interceptors.response.use(
            response => {
                // The user's own actions restart the idle clock, as on the server.
                const url = response.config.url ?? '';
                if (response.config.method !== 'get' && !url.includes('/ai-mode/')) lastAction.current = Date.now();
                return response;
            },
            error => {
                const status = error?.response?.status;
                const code = error?.response?.data?.code;
                if ((status === 423 || status === 409) && code) setLock(code);
                else if (status === 401 || status === 419) setLock('signed_out');
                return Promise.reject(error);
            },
        );

        return () => {
            axios.interceptors.response.eject(interceptor);
            delete axios.defaults.headers.common['X-AI-Mode'];
            delete axios.defaults.headers.common['X-AI-Workspace'];
        };
    }, [config.workspaceId]);

    // Talk to the Sundal tabs: announce this tab, and notice Sundal closing.
    useEffect(() => {
        const channel = openChannel();
        const say = (type: AiModeMessage['type']) => channel?.postMessage({ type } as AiModeMessage);
        let closingTimer: number | undefined;

        const onMessage = (event: MessageEvent<AiModeMessage>) => {
            switch (event.data?.type) {
                case 'ping':
                    say('ai-alive');
                    break;
                case 'app-alive':
                    lastAppSeen.current = Date.now();
                    if (lockRef.current === 'locked_app_closed') checkStatus();
                    break;
                case 'app-closing':
                    // A reload also closes the page: give Sundal a moment to come back.
                    window.clearTimeout(closingTimer);
                    closingTimer = window.setTimeout(() => {
                        const asked = Date.now();
                        say('ping');
                        window.setTimeout(() => {
                            if (lastAppSeen.current < asked) setLock('locked_app_closed');
                        }, PING_WAIT_MS);
                    }, RELOAD_GRACE_MS);
                    break;
                case 'close':
                    turnOff();
                    break;
                case 'signed-out':
                    setLock('signed_out');
                    break;
            }
        };
        channel?.addEventListener('message', onMessage);

        say('ai-alive');
        const alive = window.setInterval(() => say('ai-alive'), AI_ALIVE_EVERY_MS);
        const onPageHide = () => say('ai-closed');
        window.addEventListener('pagehide', onPageHide);

        return () => {
            window.clearInterval(alive);
            window.clearTimeout(closingTimer);
            window.removeEventListener('pagehide', onPageHide);
            channel?.removeEventListener('message', onMessage);
            channel?.close();
        };
    }, []);

    // Idle countdown; the warning shows in the last `warningSeconds`.
    useEffect(() => {
        const timer = window.setInterval(() => {
            const left = Math.max(0, Math.round(config.idleSeconds - (Date.now() - lastAction.current) / 1000));
            setSecondsLeft(left);
            if (left === 0 && !lockRef.current) setLock('locked_idle');
        }, 1000);
        return () => window.clearInterval(timer);
    }, [config.idleSeconds]);

    // While locked because Sundal is closed, keep checking whether it is back.
    useEffect(() => {
        if (lock !== 'locked_app_closed') return;
        const timer = window.setInterval(checkStatus, 5000);
        return () => window.clearInterval(timer);
    }, [lock]);

    async function checkStatus() {
        try {
            const { data } = await axios.get(route('ai-mode.status'));
            if (data.code === 'ok') {
                setLock(null);
            } else {
                setLock(data.code);
            }
        } catch {
            // The interceptor already set the lock for 401/419.
        }
    }

    function turnOff() {
        const channel = openChannel();
        channel?.postMessage({ type: 'ai-closed' } satisfies AiModeMessage);
        channel?.close();
        window.close();
        // Still open (not opened by Sundal, e.g. a typed URL): show the "off" screen.
        window.setTimeout(() => setLock('turned_off'), 300);
    }

    const stayActive = async () => {
        try {
            await axios.post(route('ai-mode.keep-alive'));
            lastAction.current = Date.now();
            setSecondsLeft(config.idleSeconds);
        } catch {
            // Locked meanwhile: the interceptor shows the lock.
        }
    };

    const warning = !lock && secondsLeft > 0 && secondsLeft <= config.warningSeconds;

    return (
        <div className="flex h-dvh flex-col bg-background">
            <Head title={t('AI mode')} />
            <header className="flex shrink-0 items-center justify-between gap-3 border-b px-4 py-2.5 sm:px-6">
                <div className="flex min-w-0 items-center gap-2">
                    <Sparkles className="h-5 w-5 shrink-0 text-violet-500" />
                    <span className="whitespace-nowrap font-semibold">{t('Sundal AI mode')}</span>
                    {model && (
                        <span className="hidden min-w-0 items-center gap-1.5 text-sm text-muted-foreground sm:flex" title={`${model.provider} · ${model.name}`}>
                            <span aria-hidden>·</span>
                            <span className="truncate">{model.name}</span>
                        </span>
                    )}
                </div>
                {/* Settings, Help and the user are in the sidebar; here: the theme, and the AI mode switch in its own pill. */}
                <div className="flex shrink-0 items-center gap-2">
                    <ThemeToggle />
                    <label className="flex cursor-pointer items-center gap-2 rounded-full border bg-muted/40 py-1 pl-3 pr-1 text-xs font-medium">
                        {t('AI mode')}
                        <Switch checked={lock !== 'turned_off'} onCheckedChange={on => (on ? window.location.reload() : turnOff())} aria-label={t('AI mode')} />
                    </label>
                </div>
            </header>

            {warning && (
                <div className="flex flex-wrap items-center justify-center gap-3 border-b bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    <Clock className="h-4 w-4" />
                    {t('AI mode will lock in {{time}} without activity.', { time: formatSeconds(secondsLeft) })}
                    <Button size="sm" variant="outline" className="h-7" onClick={stayActive}>{t('Stay signed in')}</Button>
                </div>
            )}

            {/* The whole page below the header: the chat fills it edge to edge. */}
            <main className="relative min-h-0 flex-1 overflow-y-auto">
                {/* The chat stays mounted under a lock, so nothing typed or waiting is lost. */}
                <div aria-hidden={!!lock} className={`h-full ${lock ? 'pointer-events-none select-none blur-sm' : ''}`}>{children}</div>
                {lock && <LockScreen reason={lock} onUnlocked={() => { lastAction.current = Date.now(); setSecondsLeft(config.idleSeconds); setLock(null); }} onLock={setLock} />}
            </main>
        </div>
    );
}

const THEME_KEY = 'sundal.aiMode.theme';

/** Light or dark on the page right now. */
const isDark = () => document.documentElement.classList.contains('dark');

function applyDark(dark: boolean) {
    document.documentElement.classList.toggle('dark', dark);
    document.body.classList.toggle('dark', dark);
}

/**
 * Light / dark for AI mode only. Sundal's theme is a company brand setting,
 * so this is the viewer's own choice, remembered in this browser; without
 * one, AI mode follows the company theme.
 */
function ThemeToggle() {
    const { t } = useTranslation();
    const [dark, setDark] = useState(false);

    useEffect(() => {
        let saved: string | null = null;
        try {
            saved = window.localStorage.getItem(THEME_KEY);
        } catch {
            // Storage blocked (private window): follow the company theme.
        }
        if (saved === 'dark' || saved === 'light') applyDark(saved === 'dark');
        setDark(isDark());
    }, []);

    const toggle = () => {
        const next = !isDark();
        applyDark(next);
        setDark(next);
        try {
            window.localStorage.setItem(THEME_KEY, next ? 'dark' : 'light');
        } catch {
            // Not remembered, but still switched for now.
        }
    };

    const label = dark ? t('Switch to light theme') : t('Switch to dark theme');

    return (
        <Button variant="ghost" size="icon" className={AI_MODE_ICON_BUTTON} onClick={toggle} aria-label={label} title={label}>
            {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
        </Button>
    );
}

function formatSeconds(seconds: number): string {
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

function LockScreen({ reason, onUnlocked, onLock }: { reason: LockReason; onUnlocked: () => void; onLock: (reason: LockReason) => void }) {
    const { t } = useTranslation();
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    /** Resume after the idle pause with the current login (no password). */
    const resume = async () => {
        setBusy(true);
        setError(null);
        try {
            const { data } = await axios.post(route('ai-mode.unlock'));
            if (data.code === 'ok') onUnlocked();
            else onLock(data.code);
        } catch (e: any) {
            setError(e?.response?.data?.message ?? t('Could not continue.'));
        } finally {
            setBusy(false);
        }
    };

    const content: Record<LockReason, { title: string; text: string; action: ReactNode }> = {
        locked_idle: {
            title: t('AI mode is paused'),
            text: t('It was paused after a while without activity. Continue where you left off.'),
            action: (
                <div className="flex flex-col items-center gap-2">
                    {error && <p className="text-xs text-destructive">{error}</p>}
                    <Button onClick={resume} disabled={busy} autoFocus>
                        {busy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Play className="mr-2 h-4 w-4" />}
                        {t('Continue')}
                    </Button>
                </div>
            ),
        },
        locked_app_closed: {
            title: t('Sundal is closed'),
            text: t('AI mode works while Sundal is open. Open Sundal and AI mode continues by itself.'),
            action: <Button onClick={() => window.open(route('dashboard'), '_blank')}>{t('Open Sundal')}</Button>,
        },
        workspace_changed: {
            title: t('Workspace changed'),
            text: t('You switched workspace in Sundal. Reload AI mode to work in the new workspace.'),
            action: <Button onClick={() => window.location.reload()}><RefreshCw className="mr-2 h-4 w-4" />{t('Reload AI mode')}</Button>,
        },
        ai_mode_ended: {
            title: t('AI mode has ended'),
            text: t('Open it again to continue.'),
            action: <Button onClick={() => (window.location.href = route('ai-mode'))}>{t('Reopen AI mode')}</Button>,
        },
        signed_out: {
            title: t('You are signed out'),
            text: t('Sign in to Sundal again to use AI mode.'),
            action: <Button onClick={() => (window.location.href = route('login'))}><LogIn className="mr-2 h-4 w-4" />{t('Sign in')}</Button>,
        },
        turned_off: {
            title: t('AI mode is off'),
            text: t('You can close this tab, or turn AI mode on again.'),
            action: <Button onClick={() => (window.location.href = route('dashboard'))}>{t('Back to Sundal')}</Button>,
        },
    };

    const { title, text, action } = content[reason] ?? content.ai_mode_ended;

    return (
        <div className="absolute inset-0 z-10 flex items-start justify-center p-6 pt-24" role="alertdialog" aria-modal="true" aria-labelledby="ai-mode-lock-title">
            <Card className="w-full max-w-md shadow-lg">
                <CardContent className="flex flex-col items-center gap-3 p-6 text-center">
                    <Lock className="h-8 w-8 text-muted-foreground" />
                    <h2 id="ai-mode-lock-title" className="text-lg font-semibold">{title}</h2>
                    <p className="text-sm text-muted-foreground">{text}</p>
                    {action}
                </CardContent>
            </Card>
        </div>
    );
}
