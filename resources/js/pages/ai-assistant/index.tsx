import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import {
    ArrowLeft, ArrowUp, Bot, Bug, Check, CheckCheck, ChevronDown, Clock, Copy, ExternalLink, FolderKanban, HelpCircle, ListTodo, Loader2,
    MessageSquare, PanelLeftClose, PanelLeftOpen, Pencil, Plus, Receipt, Settings as SettingsIcon, Sparkles, Trash2, Undo2, Users, X,
} from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/components/custom-toast';
import { AI_MODE_ICON_BUTTON, AiModeShell, type AiModeConfig } from '@/components/ai-mode-shell';

declare const route: any;

type CardStatus = 'pending' | 'done' | 'failed' | 'cancelled' | 'expired' | 'undone';

interface ToolCard {
    id: number;
    tool: string;
    summary: string;
    status: CardStatus;
    details: Record<string, string>;
    /** Every record a bulk action touches, listed in full. */
    items: string[];
    /** Risky actions: the user must type this to confirm. */
    confirm_phrase: string | null;
    link: string | null;
    can_undo: boolean;
    undo_until: string | null;
    error: string | null;
    /** Drafts: "question" while a must-know value is missing, then "review". */
    stage: 'question' | 'review';
    /** Missing must-know fields, in order; the first is asked. */
    ask: string[];
    question: string | null;
    /** The draft's values: shown as choice buttons, a summary, or the Edit form. */
    fields: FormFieldState[];
    form_error: string | null;
}

interface FormFieldState {
    name: string;
    label: string;
    type: 'select' | 'multi' | 'date' | 'number' | 'text';
    required: boolean;
    value: string | string[] | null;
    options: { value: string; label: string }[];
    /** Why the field was left for the user, e.g. "Several match Ravi". */
    note: string | null;
    error: string | null;
}

interface TopicOption {
    key: string;
    label: string;
}

interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
    cards: ToolCard[];
    /** Shown in the chat only, never saved: the provider refused or failed. */
    error?: boolean;
}

interface Conversation {
    id: number;
    title: string | null;
    topic?: string | null;
    last_message_at?: string | null;
}

interface ProviderOption {
    label: string;
    default_model: string | null;
    models: { id: string; label: string }[];
}

interface Settings {
    provider: string;
    model: string;
    masked_key: string;
    azure_endpoint: string | null;
    azure_deployment: string | null;
    organization: string | null;
    monthly_token_cap: number | null;
    managers_enabled: boolean;
    retention_days: number;
    idle_timeout_minutes: number;
    last_tested_at: string | null;
    last_test_passed: boolean | null;
}

interface ModelInfo {
    provider: string;
    name: string;
}

interface Props {
    access: 'allowed' | 'managers_off' | 'plan' | 'role';
    isOwner: boolean;
    configured: boolean;
    conversations: Conversation[];
    settings: Settings | null;
    usage: { tokens_this_month: number; daily: { date: string; tokens: number }[] } | null;
    providers: Record<string, ProviderOption> | null;
    retentionOptions: number[];
    idleTimeoutOptions?: number[];
    topics: TopicOption[];
    /** The connected provider and model, for the pill in the chat header. */
    model?: ModelInfo | null;
    /** Set when the page is the AI mode tab (route ai-mode). */
    standalone?: boolean;
    aiMode?: AiModeConfig;
}

const errorMessage = (error: any, fallback: string): string =>
    error?.response?.data?.error
    ?? error?.response?.data?.message
    ?? (Object.values(error?.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0]
    ?? fallback;

export default function AiAssistantPage(props: Props) {
    const { t } = useTranslation();
    const breadcrumbs = [{ title: t('Dashboard'), href: route('dashboard') }, { title: t('AI Assistant') }];
    // Owners open Settings with the gear (AI mode bar, or above the chat on the
    // Sundal page); without a provider, Settings is all there is.
    const [view, setView] = useState<'chat' | 'settings'>(props.configured ? 'chat' : 'settings');
    const canOpenSettings = props.isOwner && props.configured && props.access === 'allowed';
    const standalone = !!(props.standalone && props.aiMode);

    let body;
    let framed = true;
    if (props.access === 'plan') {
        body = <UpgradeNotice />;
    } else if (props.access === 'managers_off') {
        body = <Notice title={t('The AI Assistant is turned off for managers')} text={t('Your company owner has turned the AI Assistant off for managers.')} />;
    } else if (!props.configured && !props.isOwner) {
        body = <Notice title={t('No AI provider connected')} text={t('Ask your company owner to connect an AI provider on this page.')} />;
    } else if (props.isOwner && (view === 'settings' || !props.configured)) {
        body = (
            <div className="space-y-4">
                {props.configured && (
                    <Button variant="ghost" size="sm" className="-ml-2" onClick={() => setView('chat')}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" />
                        {t('Back to the assistant')}
                    </Button>
                )}
                <SettingsForm {...props} />
            </div>
        );
    } else {
        body = (
            <Chat
                conversations={props.conversations}
                topics={props.topics}
                model={props.model ?? null}
                standalone={standalone}
                onOpenSettings={canOpenSettings && !standalone ? () => setView('settings') : undefined}
            />
        );
        framed = false;
    }

    // AI mode tab: full screen, its own header and locks, no Sundal sidebar.
    // The chat fills the whole page; settings and notices keep a readable width.
    if (standalone && props.aiMode) {
        const settingsOpen = view === 'settings';
        const settingsButton = canOpenSettings ? (
            <Button
                variant="ghost"
                size="icon"
                className={`${AI_MODE_ICON_BUTTON} ${settingsOpen ? 'bg-muted text-foreground' : ''}`}
                onClick={() => setView(settingsOpen ? 'chat' : 'settings')}
                aria-label={settingsOpen ? t('Back to the assistant') : t('Settings')}
                title={settingsOpen ? t('Back to the assistant') : t('Settings')}
                aria-pressed={settingsOpen}
            >
                <SettingsIcon className="h-4 w-4" />
            </Button>
        ) : null;

        return (
            <AiModeShell config={props.aiMode} model={props.model} actions={settingsButton}>
                {framed ? <div className="mx-auto max-w-7xl p-4 sm:p-6">{body}</div> : body}
            </AiModeShell>
        );
    }

    return (
        <PageTemplate title={t('AI Assistant')} breadcrumbs={breadcrumbs} noPadding={!framed}>
            {body}
        </PageTemplate>
    );
}

/**
 * The model's replies use a little Markdown: **bold**, [links](/tasks/12) and
 * "- " bullets. Rendered as React elements, never as HTML, so a reply cannot
 * inject markup. Only links inside Sundal (relative paths, as the tools
 * return) are clickable: a reply could repeat a link planted in a record
 * (prompt injection), so other addresses are shown as plain text instead.
 */
function FormattedText({ text }: { text: string }) {
    const inline = (line: string, key: string) =>
        line.split(/(\*\*[^*]+\*\*|\[[^\]]+\]\([^)\s]+\))/g).map((part, i) => {
            const bold = part.match(/^\*\*([^*]+)\*\*$/);
            if (bold) return <strong key={`${key}-${i}`}>{bold[1]}</strong>;
            const link = part.match(/^\[([^\]]+)\]\(([^)\s]+)\)$/);
            // "/tasks/12" yes; "//evil.com" and "/\evil.com" (other sites) no.
            if (link && /^\/(?![/\\])/.test(link[2])) {
                return <a key={`${key}-${i}`} href={link[2]} className="text-primary underline">{link[1]}</a>;
            }
            if (link) {
                return <span key={`${key}-${i}`}>{link[1]} ({link[2]})</span>;
            }
            return <span key={`${key}-${i}`}>{part}</span>;
        });

    return (
        <>
            {text.split('\n').map((line, i) => {
                const bullet = line.match(/^\s*[-*]\s+(.*)$/);
                return bullet
                    ? <div key={i} className="flex gap-2"><span>•</span><span>{inline(bullet[1], `l${i}`)}</span></div>
                    : <div key={i}>{line.trim() === '' ? ' ' : inline(line, `l${i}`)}</div>;
            })}
        </>
    );
}

/** Owners without the AI add-on: what the assistant does, and where to get it. */
function UpgradeNotice() {
    const { t } = useTranslation();
    const samples = [
        'Assign the login bug to Ravi, due Friday.',
        'Approve all pending timesheets for Website Redesign.',
        'Who is over budget this month?',
        'How much did we bill in September?',
    ];

    return (
        <Card className="mx-auto max-w-2xl">
            <CardHeader className="items-center text-center">
                <Sparkles className="mb-2 h-10 w-10 text-violet-500" />
                <CardTitle>{t('Run your projects by chat')}</CardTitle>
                <CardDescription>
                    {t('The AI Assistant answers questions about your projects and does the work for you and your managers: assigning tasks and bugs, approving timesheets and expenses, creating projects and more. Every change is shown for you to confirm first. It runs on your own AI provider account.')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <ul className="space-y-2 text-sm">
                    {samples.map(sample => (
                        <li key={sample} className="rounded-md bg-muted px-3 py-2">“{t(sample)}”</li>
                    ))}
                </ul>
                <p className="text-center text-sm text-muted-foreground">{t('The AI Assistant is part of the Pro Add-on.')}</p>
                <div className="flex justify-center">
                    <Button onClick={() => router.visit(route('plans.index'))}>{t('See plans')}</Button>
                </div>
            </CardContent>
        </Card>
    );
}

/** Daily tokens, last 30 days: one series, one hue, hover for the exact value. */
function UsageChart({ daily }: { daily: { date: string; tokens: number }[] }) {
    const { t } = useTranslation();
    const [hover, setHover] = useState<number | null>(null);
    const max = Math.max(1, ...daily.map(d => d.tokens));
    const label = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });

    return (
        <div>
            <div className="relative flex h-24 items-end gap-[2px]" onMouseLeave={() => setHover(null)}>
                {daily.map((d, i) => (
                    <div key={d.date} className="flex h-full flex-1 items-end" onMouseEnter={() => setHover(i)}>
                        <div
                            className={`w-full rounded-t-[4px] ${d.tokens ? 'bg-primary' : 'bg-muted'} ${hover === i ? 'opacity-80' : ''}`}
                            style={{ height: d.tokens ? `${Math.max(4, (d.tokens / max) * 100)}%` : '2px' }}
                        />
                    </div>
                ))}
                {hover !== null && (
                    <div className="pointer-events-none absolute -top-9 rounded-md border bg-popover px-2 py-1 text-xs text-popover-foreground shadow-sm"
                        style={{ left: `clamp(0px, calc(${(hover / daily.length) * 100}% - 40px), calc(100% - 110px))` }}>
                        {label(daily[hover].date)}: {daily[hover].tokens.toLocaleString()} {t('tokens')}
                    </div>
                )}
            </div>
            <div className="mt-1 flex justify-between text-[11px] text-muted-foreground">
                <span>{label(daily[0].date)}</span>
                <span>{label(daily[daily.length - 1].date)}</span>
            </div>
            <table className="sr-only">
                <caption>{t('Tokens used per day')}</caption>
                <tbody>{daily.map(d => <tr key={d.date}><td>{d.date}</td><td>{d.tokens}</td></tr>)}</tbody>
            </table>
        </div>
    );
}

function Notice({ title, text }: { title: string; text: string }) {
    return (
        <Card className="mx-auto max-w-xl">
            <CardHeader className="items-center text-center">
                <Bot className="mb-2 h-10 w-10 text-muted-foreground" />
                <CardTitle>{title}</CardTitle>
                <CardDescription>{text}</CardDescription>
            </CardHeader>
        </Card>
    );
}

// ─── Chat ───────────────────────────────────────────────────────────────────

/** Starter prompts on the welcome screen, each with the topic it belongs to (for its icon). */
const EXAMPLES: { text: string; topic: string }[] = [
    { text: 'What tasks are overdue in my projects?', topic: 'tasks' },
    { text: 'Assign the login bug to Ravi, due Friday.', topic: 'bugs' },
    { text: 'Which timesheets are waiting for my approval?', topic: 'approvals' },
    { text: 'Create an invoice for the Website Redesign tasks.', topic: 'finance' },
];

/** The box hint while a topic button is on. */
const TOPIC_HINTS: Record<string, string> = {
    tasks: 'Describe the task, e.g. "Login page for Mobile App, high priority"',
    bugs: 'Describe the bug, e.g. "Login button does nothing on mobile"',
    projects: 'Ask about a project, or create one',
    time: 'e.g. "Log 3 hours on Website Redesign for today"',
    approvals: 'e.g. "Approve the pending timesheets for Website Redesign"',
    finance: 'e.g. "Create an invoice for the Website Redesign tasks"',
    team: 'e.g. "Invite john@acme.com as a client"',
    help: 'Ask how to do something in Sundal',
};

const TOPIC_EXAMPLES: Record<string, string[]> = {
    tasks: ['Login page for the Mobile App project, high priority', "Move 'API docs' to Done", 'What tasks are overdue?', 'Assign the checkout task to me'],
    bugs: ['Login button does nothing on mobile', 'Which bugs are still open?', 'Assign the login bug to me'],
    projects: ['Create a project called Mobile App', 'Write the weekly report for my biggest project', 'Who is over budget this month?'],
    time: ['Log 3 hours on Website Redesign for today', 'Start the timer on Mobile App', 'How many hours did I log this week?', 'Submit my timesheet'],
    approvals: ['Which timesheets are waiting for my approval?', 'Which expenses are pending?'],
    finance: ['Create an invoice for the Website Redesign tasks', 'Mark INV-104 as paid', 'Add a 120 hosting expense to Mobile App', 'Which invoices are unpaid?'],
    team: ['Who is on my team?', 'Invite john@acme.com as a client'],
    help: ['How do I submit a timesheet?'],
};

const TOPIC_ICONS: Record<string, typeof Bot> = {
    tasks: ListTodo,
    bugs: Bug,
    projects: FolderKanban,
    time: Clock,
    approvals: CheckCheck,
    finance: Receipt,
    team: Users,
    help: HelpCircle,
};

const MAX_LENGTH = 4000;

/** Where the open / collapsed state of the chat list is remembered. */
const LIST_KEY = 'sundal.aiAssistant.chatList';

/** Phones and narrow windows: below Tailwind's md breakpoint. */
const isSmallScreen = () => typeof window !== 'undefined' && window.innerWidth < 768;

/** A card that moved to a newer message (a draft the AI updated) is shown only there. */
function withFresh(prev: Message[], fresh: Message[]): Message[] {
    const moved = new Set(fresh.flatMap(m => m.cards.map(c => c.id)));
    const freshIds = new Set(fresh.map(m => m.id));

    return [
        ...prev.filter(m => !freshIds.has(m.id)).map(m => ({ ...m, cards: m.cards.filter(c => !moved.has(c.id)) })),
        ...fresh,
    ];
}

/** "Today", "Yesterday", "Previous 7 days", "Older" for the conversation list. */
function dayGroup(iso: string | null | undefined): string {
    if (!iso) return 'Today';
    const startOfToday = new Date();
    startOfToday.setHours(0, 0, 0, 0);
    const days = Math.floor((startOfToday.getTime() - new Date(iso).getTime()) / 86_400_000) + 1;
    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days <= 7) return 'Previous 7 days';
    return 'Older';
}

/** "now", "5m", "3h", "2d", or a short date. */
function timeAgo(iso: string | null | undefined): string {
    if (!iso) return '';
    const minutes = Math.floor((Date.now() - new Date(iso).getTime()) / 60_000);
    if (minutes < 1) return 'now';
    if (minutes < 60) return `${minutes}m`;
    if (minutes < 24 * 60) return `${Math.floor(minutes / 60)}h`;
    if (minutes < 7 * 24 * 60) return `${Math.floor(minutes / (24 * 60))}d`;
    return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

function greeting(): string {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
}

/** The assistant's round mark: a soft violet orb, also its avatar. */
function AssistantMark({ size = 'sm' }: { size?: 'sm' | 'lg' }) {
    return size === 'lg' ? (
        <div className="relative mx-auto h-14 w-14" aria-hidden>
            <div className="absolute inset-0 rounded-full bg-violet-500/40 blur-xl" />
            <div className="relative h-14 w-14 rounded-full bg-gradient-to-br from-fuchsia-300 via-violet-500 to-violet-700 shadow-inner" />
        </div>
    ) : (
        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-fuchsia-400 via-violet-500 to-violet-700 text-white" aria-hidden>
            <Sparkles className="h-4 w-4" />
        </div>
    );
}

function Chat({ conversations: initial, topics, model, standalone, onOpenSettings }: {
    conversations: Conversation[];
    topics: TopicOption[];
    model: ModelInfo | null;
    standalone: boolean;
    /** Owners: opens the settings view. */
    onOpenSettings?: () => void;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const [conversations, setConversations] = useState<Conversation[]>(initial);
    const [activeId, setActiveId] = useState<number | null>(initial[0]?.id ?? null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const [loading, setLoading] = useState(false);
    // The conversation list: open, or collapsed to a narrow rail. Remembered in this
    // browser; small screens always start collapsed (the open list covers the chat).
    const [listOpen, setListOpen] = useState(() => {
        if (isSmallScreen()) return false;
        try {
            return window.localStorage.getItem(LIST_KEY) !== 'closed';
        } catch {
            return true;
        }
    });
    const toggleList = () => setListOpen(wasOpen => {
        if (!isSmallScreen()) {
            try {
                window.localStorage.setItem(LIST_KEY, wasOpen ? 'closed' : 'open');
            } catch {
                // Not remembered; still toggled.
            }
        }
        return !wasOpen;
    });
    // The topic button stays on for the conversation until removed.
    const [topic, setTopic] = useState<string | null>(initial[0]?.topic ?? null);
    const bottomRef = useRef<HTMLDivElement>(null);
    // Set when send() creates a conversation: its messages are already on screen
    // (including any error bubble), so don't reload and overwrite them.
    const createdHereRef = useRef<number | null>(null);

    useEffect(() => {
        if (activeId === null) {
            setMessages([]);
            setTopic(null);
            return;
        }
        setTopic(conversations.find(c => c.id === activeId)?.topic ?? null);
        if (createdHereRef.current === activeId) {
            createdHereRef.current = null;
            return;
        }
        // Ignore the response if the user has switched chats (or started a new
        // one) before it arrived, so an old conversation never lands in the new one.
        let stale = false;
        setLoading(true);
        axios.get(route('ai-assistant.conversations.show', activeId))
            .then(({ data }) => { if (!stale) setMessages(data.messages); })
            .catch(() => { if (!stale) toast.error(t('Could not load this conversation.')); })
            .finally(() => { if (!stale) setLoading(false); });

        return () => {
            stale = true;
            setLoading(false);
        };
    }, [activeId]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, sending]);

    const send = async (text: string) => {
        const content = text.trim();
        if (!content || sending) return;
        setSending(true);
        setInput('');
        const optimistic: Message = { id: -Date.now(), role: 'user', content, created_at: new Date().toISOString(), cards: [] };
        setMessages(prev => [...prev, optimistic]);

        const apply = (data: any) => {
            if (!data?.conversation) return;
            setMessages(prev => withFresh(prev.filter(m => m.id !== optimistic.id), data.messages));
            if (activeId === null) createdHereRef.current = data.conversation.id;
            setActiveId(current => current ?? data.conversation.id);
            setConversations(prev => [
                { ...data.conversation, last_message_at: new Date().toISOString() },
                ...prev.filter(c => c.id !== data.conversation.id),
            ]);
        };

        try {
            const { data } = await axios.post(route('ai-assistant.send'), { conversation_id: activeId, content, topic: topic ?? '' });
            apply(data);
            if (data.pending) {
                await waitForReply(data.conversation.id, data.messages.at(-1)?.id ?? 0);
            }
        } catch (error: any) {
            const data = error?.response?.data;
            apply(data);
            // The server saves provider errors ("no credits left") in the chat;
            // only show a local one when it could not (network error, 429…).
            if (!data?.messages?.some((m: Message) => m.error)) {
                const reason = errorMessage(error, t('The assistant could not answer. Please try again.'));
                setMessages(prev => [
                    ...prev,
                    { id: -Date.now() - 1, role: 'assistant', content: reason, created_at: new Date().toISOString(), cards: [], error: true },
                ]);
            }
        } finally {
            setSending(false);
        }
    };

    /** Queue mode: poll the conversation until the assistant's reply arrives (up to 3 minutes). */
    const waitForReply = async (conversationId: number, afterId: number) => {
        for (let attempt = 0; attempt < 120; attempt++) {
            await new Promise(resolve => setTimeout(resolve, 1500));
            const { data } = await axios.get(route('ai-assistant.conversations.show', conversationId));
            const fresh: Message[] = data.messages.filter((m: Message) => m.id > afterId);
            if (fresh.some(m => m.role === 'assistant')) {
                setMessages(prev => withFresh(prev.filter(m => m.id <= afterId), fresh));
                return;
            }
        }
        throw new Error(t('The assistant is taking too long. Please try again.'));
    };

    const onCardChange = (card: ToolCard, note: Message | null) => {
        setMessages(prev => {
            const updated = prev.map(m => ({ ...m, cards: m.cards.map(c => (c.id === card.id ? card : c)) }));
            return note && !updated.some(m => m.id === note.id) ? [...updated, note] : updated;
        });
    };

    const remove = async (conversation: Conversation) => {
        if (!confirm(t('Delete this conversation? The record of actions taken stays in the audit log.'))) return;
        try {
            await axios.delete(route('ai-assistant.conversations.destroy', conversation.id));
            setConversations(prev => prev.filter(c => c.id !== conversation.id));
            if (activeId === conversation.id) setActiveId(null);
        } catch (error) {
            toast.error(errorMessage(error, t('Could not delete the conversation.')));
        }
    };

    const open = (id: number | null) => {
        setActiveId(id);
        // On a phone the open list covers the chat: close it once a chat is picked.
        if (isSmallScreen()) setListOpen(false);
    };

    const firstName = String(auth?.user?.name ?? '').split(' ')[0];
    const showWelcome = !loading && messages.length === 0;

    const composer = (
        <Composer
            value={input}
            onChange={setInput}
            onSend={() => send(input)}
            sending={sending}
            topics={topics}
            topic={topic}
            onTopic={setTopic}
            large={showWelcome}
        />
    );

    return (
        <div className={`relative flex overflow-hidden bg-background ${standalone ? 'h-full min-h-[480px]' : 'h-[calc(100dvh-11rem)] min-h-[560px] rounded-xl border'}`}>
            {/* Conversation list: open, or a narrow rail. On a phone the open list slides over the chat. */}
            {listOpen ? (
                <>
                    <div className="absolute inset-0 z-20 bg-black/30 md:hidden" onClick={toggleList} aria-hidden />
                    <aside id="ai-chat-list" className="absolute inset-y-0 left-0 z-30 flex w-72 shrink-0 flex-col border-r bg-background shadow-xl md:static md:z-auto md:bg-muted/40 md:shadow-none">
                        <ConversationList conversations={conversations} activeId={activeId} onOpen={open} onDelete={remove} onCollapse={toggleList} />
                    </aside>
                </>
            ) : (
                <aside className="flex w-14 shrink-0 flex-col items-center gap-2 border-r bg-muted/40 py-3">
                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={toggleList} aria-label={t('Show chats')} title={t('Show chats')} aria-expanded={false} aria-controls="ai-chat-list">
                        <PanelLeftOpen className="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="icon" className="h-8 w-8 rounded-full bg-background" onClick={() => open(null)} aria-label={t('New chat')} title={t('New chat')}>
                        <Plus className="h-4 w-4" />
                    </Button>
                </aside>
            )}

            <section className="flex min-w-0 flex-1 flex-col">
                {/* The Sundal page has no AI mode bar: the model and Settings sit above the chat. */}
                {!standalone && (model || onOpenSettings) && (
                    <header className="flex items-center gap-2 border-b px-3 py-2.5 sm:px-4">
                        {model && (
                            <div className="flex min-w-0 items-center gap-2" title={`${model.provider} · ${model.name}`}>
                                <Sparkles className="h-4 w-4 shrink-0 text-violet-500" />
                                <span className="truncate text-sm font-semibold">{model.name}</span>
                                <span className="hidden shrink-0 rounded-full border px-2 py-0.5 text-[11px] text-muted-foreground sm:inline">{model.provider}</span>
                            </div>
                        )}
                        {onOpenSettings && (
                            <Button variant="ghost" size="icon" className="ml-auto h-8 w-8" onClick={onOpenSettings} aria-label={t('Settings')} title={t('Settings')}>
                                <SettingsIcon className="h-4 w-4" />
                            </Button>
                        )}
                    </header>
                )}

                {showWelcome ? (
                    <div className="flex flex-1 flex-col items-center overflow-y-auto px-4 py-10 sm:justify-center">
                        <div className="w-full max-w-3xl">
                            <AssistantMark size="lg" />
                            <h2 className="mt-6 text-center text-2xl font-semibold tracking-tight sm:text-3xl">
                                {t(greeting())}{firstName ? `, ${firstName}` : ''}
                            </h2>
                            <p className="text-center text-2xl font-semibold tracking-tight sm:text-3xl">
                                {t('What can I')}{' '}
                                <span className="bg-gradient-to-r from-fuchsia-500 to-violet-600 bg-clip-text text-transparent">{t('do for you today?')}</span>
                            </p>
                            <p className="mx-auto mt-3 max-w-xl text-center text-sm text-muted-foreground">
                                {t('Ask about your projects, tasks, time and invoices, or ask me to do the work. I show a card for you to confirm before changing anything.')}
                            </p>

                            <div className="mt-8">{composer}</div>

                            <p className="mb-3 mt-10 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                {topic ? t('Try one for {{topic}}', { topic: topics.find(o => o.key === topic)?.label.toLowerCase() ?? '' }) : t('Get started with an example')}
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {(topic ? (TOPIC_EXAMPLES[topic] ?? []).slice(0, 4).map(text => ({ text, topic })) : EXAMPLES).map(example => {
                                    const Icon = TOPIC_ICONS[example.topic] ?? Sparkles;
                                    return (
                                        <button
                                            key={example.text}
                                            type="button"
                                            onClick={() => send(t(example.text))}
                                            disabled={sending}
                                            className="group flex min-h-[104px] flex-col justify-between rounded-xl border bg-muted/40 p-3.5 text-left text-sm transition-colors hover:border-violet-300 hover:bg-violet-50 disabled:opacity-60 dark:hover:border-violet-800 dark:hover:bg-violet-950/30"
                                        >
                                            <span>{t(example.text)}</span>
                                            <Icon className="mt-3 h-4 w-4 text-muted-foreground transition-colors group-hover:text-violet-600" />
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="flex-1 overflow-y-auto">
                            <div className="mx-auto max-w-4xl space-y-6 px-4 py-6">
                                {loading && <Loader2 className="mx-auto h-5 w-5 animate-spin text-muted-foreground" />}
                                {!loading && messages.map(message => (
                                    <ChatMessage key={message.id} message={message} userName={auth?.user?.name ?? ''} onCardChange={onCardChange} />
                                ))}
                                {sending && (
                                    <div className="flex gap-3" aria-live="polite">
                                        <AssistantMark />
                                        <div className="flex items-center gap-1 rounded-2xl border bg-card px-4 py-3" aria-label={t('Thinking…')}>
                                            {[0, 150, 300].map(delay => (
                                                <span key={delay} className="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-500" style={{ animationDelay: `${delay}ms` }} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                <div ref={bottomRef} />
                            </div>
                        </div>
                        <div className="px-4 pb-3 pt-1">
                            <div className="mx-auto max-w-4xl">
                                {composer}
                                <p className="mt-2 text-center text-[11px] text-muted-foreground">
                                    {t('The AI Assistant can make mistakes. Nothing changes until you confirm a card.')}
                                </p>
                            </div>
                        </div>
                    </>
                )}
            </section>
        </div>
    );
}

/** Past conversations, newest first, grouped by day. */
function ConversationList({ conversations, activeId, onOpen, onDelete, onCollapse }: {
    conversations: Conversation[];
    activeId: number | null;
    onOpen: (id: number | null) => void;
    onDelete: (conversation: Conversation) => void;
    onCollapse: () => void;
}) {
    const { t } = useTranslation();
    const groups = conversations.reduce<Record<string, Conversation[]>>((all, c) => {
        (all[dayGroup(c.last_message_at)] ??= []).push(c);
        return all;
    }, {});

    return (
        <>
            <div className="flex items-center gap-2 px-4 pb-2 pt-3">
                <AssistantMark />
                <span className="truncate text-sm font-semibold">{t('AI Assistant')}</span>
                <div className="ml-auto flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8 rounded-full bg-background"
                        onClick={() => onOpen(null)}
                        aria-label={t('New chat')}
                        title={t('New chat')}
                    >
                        <Plus className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={onCollapse} aria-label={t('Hide chats')} title={t('Hide chats')} aria-expanded>
                        <PanelLeftClose className="h-4 w-4" />
                    </Button>
                </div>
            </div>
            <nav className="flex-1 overflow-y-auto px-2 pb-3" aria-label={t('Conversations')}>
                {conversations.length === 0 && <p className="px-2 py-4 text-xs text-muted-foreground">{t('No conversations yet. Your chats appear here.')}</p>}
                {['Today', 'Yesterday', 'Previous 7 days', 'Older'].filter(g => groups[g]).map(group => (
                    <div key={group}>
                        <p className="px-2 pb-1 pt-3 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{t(group)}</p>
                        <ul className="space-y-0.5">
                            {groups[group].map(c => {
                                const Icon = (c.topic && TOPIC_ICONS[c.topic]) || MessageSquare;
                                const on = c.id === activeId;
                                return (
                                    <li key={c.id}>
                                        <div
                                            role="button"
                                            tabIndex={0}
                                            onClick={() => onOpen(c.id)}
                                            onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onOpen(c.id); } }}
                                            aria-current={on ? 'true' : undefined}
                                            className={`group flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring ${
                                                on ? 'bg-background font-medium shadow-sm ring-1 ring-border' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'
                                            }`}
                                        >
                                            <Icon className={`h-4 w-4 shrink-0 ${on ? 'text-violet-600' : ''}`} />
                                            <span className="min-w-0 flex-1 truncate">{c.title || t('New conversation')}</span>
                                            <span className="text-[11px] text-muted-foreground group-hover:hidden">{t(timeAgo(c.last_message_at))}</span>
                                            <button
                                                type="button"
                                                className="hidden rounded p-0.5 hover:bg-muted group-hover:block"
                                                onClick={e => { e.stopPropagation(); onDelete(c); }}
                                                aria-label={t('Delete')}
                                                title={t('Delete')}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </nav>
        </>
    );
}

/** The message box: grows with the text; topic picker and send button underneath. */
function Composer({ value, onChange, onSend, sending, topics, topic, onTopic, large }: {
    value: string;
    onChange: (value: string) => void;
    onSend: () => void;
    sending: boolean;
    topics: TopicOption[];
    topic: string | null;
    onTopic: (topic: string | null) => void;
    large: boolean;
}) {
    const { t } = useTranslation();
    const ref = useRef<HTMLTextAreaElement>(null);
    const current = topics.find(o => o.key === topic);
    const CurrentIcon = (topic && TOPIC_ICONS[topic]) || Sparkles;

    // Grow with the text, up to about 8 lines.
    useEffect(() => {
        const box = ref.current;
        if (!box) return;
        box.style.height = 'auto';
        box.style.height = `${Math.min(box.scrollHeight, 200)}px`;
    }, [value]);

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            onSend();
        }
    };

    return (
        <form
            className="rounded-2xl border bg-card shadow-sm transition focus-within:border-violet-300 focus-within:ring-4 focus-within:ring-violet-500/10 dark:focus-within:border-violet-700"
            onSubmit={(e: FormEvent) => { e.preventDefault(); onSend(); }}
        >
            <div className="flex gap-2.5 px-4 pt-3.5">
                <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-violet-500" aria-hidden />
                <textarea
                    ref={ref}
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder={topic ? t(TOPIC_HINTS[topic]) : t('Ask a question or tell me what to do…')}
                    rows={large ? 3 : 1}
                    maxLength={MAX_LENGTH}
                    aria-label={t('Message')}
                    className="max-h-[200px] w-full resize-none border-0 !bg-transparent p-0 text-sm leading-6 shadow-none outline-none ring-0 placeholder:text-muted-foreground focus:ring-0"
                />
            </div>
            <div className="flex items-center gap-2 px-3 pb-3 pt-2">
                {topics.length > 0 && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button type="button" variant="outline" size="sm" className={`h-8 gap-1.5 rounded-full text-xs ${current ? 'border-violet-300 bg-violet-50 text-violet-700 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-300' : ''}`}>
                                <CurrentIcon className="h-3.5 w-3.5" />
                                {current ? current.label : t('Topic')}
                                <ChevronDown className="h-3 w-3 opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-56">
                            <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">{t('What is this chat about?')}</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {topics.map(option => {
                                const Icon = TOPIC_ICONS[option.key] ?? Sparkles;
                                return (
                                    <DropdownMenuItem key={option.key} onSelect={() => onTopic(option.key)}>
                                        <Icon className="mr-2 h-4 w-4" />
                                        {option.label}
                                        {option.key === topic && <Check className="ml-auto h-4 w-4 text-violet-600" />}
                                    </DropdownMenuItem>
                                );
                            })}
                            {topic && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onSelect={() => onTopic(null)}>
                                        <X className="mr-2 h-4 w-4" />
                                        {t('Any topic')}
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
                {current && (
                    <button type="button" onClick={() => onTopic(null)} className="rounded-full p-1 text-muted-foreground hover:bg-muted" aria-label={t('Remove topic')} title={t('Remove topic')}>
                        <X className="h-3.5 w-3.5" />
                    </button>
                )}
                <span className={`ml-auto hidden text-[11px] tabular-nums sm:inline ${value.length > MAX_LENGTH * 0.9 ? 'text-amber-600' : 'text-muted-foreground'}`}>
                    {value.length}/{MAX_LENGTH}
                </span>
                <Button type="submit" size="icon" className="ml-auto h-8 w-8 rounded-full sm:ml-0" disabled={sending || !value.trim()} aria-label={t('Send')}>
                    {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <ArrowUp className="h-4 w-4" />}
                </Button>
            </div>
        </form>
    );
}

/** One message: avatar, name and time, the text, and any confirm cards. */
function ChatMessage({ message, userName, onCardChange }: {
    message: Message;
    userName: string;
    onCardChange: (card: ToolCard, note: Message | null) => void;
}) {
    const { t } = useTranslation();
    const mine = message.role === 'user';
    const initials = userName.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('') || '?';
    const time = new Date(message.created_at).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(message.content);
            toast.success(t('Copied'));
        } catch {
            toast.error(t('Could not copy.'));
        }
    };

    return (
        <div className="group flex gap-3">
            {mine ? (
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground" aria-hidden>{initials}</div>
            ) : (
                <AssistantMark />
            )}
            <div className="min-w-0 flex-1 space-y-2">
                <div className="flex items-baseline gap-2 text-xs">
                    <span className="font-semibold">{mine ? t('You') : t('AI Assistant')}</span>
                    <span className="text-muted-foreground">{time}</span>
                </div>
                {message.content && (
                    <div className={`whitespace-pre-wrap break-words text-sm leading-6 ${
                        message.error
                            ? 'rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-3 text-destructive'
                            : mine ? '' : 'rounded-xl border bg-card px-4 py-3 shadow-sm'
                    }`}>
                        {!mine && !message.error ? <FormattedText text={message.content} /> : message.content}
                    </div>
                )}
                {message.cards.map(card => <ConfirmCard key={card.id} card={card} onChange={onCardChange} />)}
                {!mine && !message.error && message.content && (
                    <div className="flex opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                        <Button variant="ghost" size="sm" className="h-7 px-2 text-xs text-muted-foreground" onClick={copy}>
                            <Copy className="mr-1 h-3.5 w-3.5" />
                            {t('Copy')}
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}

/** "Title: Login page · Priority: Medium · Assignee: Unassigned" for a draft. */
function knownValues(fields: FormFieldState[]): string {
    return fields
        .filter(f => !isEmpty(f.value))
        .map(f => {
            const values = Array.isArray(f.value) ? f.value : [f.value as string];
            const labels = values.map(v => f.options.find(o => o.value === v)?.label ?? v);
            return `${f.label}: ${labels.join(', ')}`;
        })
        .join(' · ');
}

function ConfirmCard({ card, onChange }: { card: ToolCard; onChange: (card: ToolCard, note: Message | null) => void }) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState<'confirm' | 'cancel' | 'undo' | 'update' | null>(null);
    const [phrase, setPhrase] = useState('');
    const [editing, setEditing] = useState(false);
    // Hide the Undo button once its 10 minutes are over, without a reload.
    const [undoOpen, setUndoOpen] = useState(card.can_undo);
    useEffect(() => {
        setUndoOpen(card.can_undo);
        if (!card.can_undo || !card.undo_until) return;
        const ms = new Date(card.undo_until).getTime() - Date.now();
        const timer = setTimeout(() => setUndoOpen(false), Math.max(0, ms));
        return () => clearTimeout(timer);
    }, [card.can_undo, card.undo_until]);

    const fields = card.fields ?? [];
    const pending = card.status === 'pending';
    // A draft still missing a must-know value: one question, answered with buttons.
    const asking = pending && card.stage === 'question' && (card.ask?.length ?? 0) > 0;
    const askField = asking ? fields.find(f => f.name === card.ask[0]) : undefined;

    // Edit form values, reset whenever the server sends the card back.
    const [values, setValues] = useState<Record<string, string | string[]>>(() => formValues(fields));
    useEffect(() => setValues(formValues(fields)), [card.fields]);

    const phraseOk = !card.confirm_phrase || phrase.trim().toLowerCase() === card.confirm_phrase.toLowerCase();

    const post = async (action: 'confirm' | 'cancel' | 'undo' | 'update', body: object = {}) => {
        setBusy(action);
        try {
            const { data } = await axios.post(route(`ai-assistant.tool-calls.${action}`, card.id), body);
            onChange(data.card, data.message);
            if (data.card.status === 'failed') toast.error(data.card.error || t('The action failed.'));
            return data.card as ToolCard;
        } catch (error) {
            toast.error(errorMessage(error, t('Could not update this card.')));
            return null;
        } finally {
            setBusy(null);
        }
    };

    /** A clicked choice, a typed answer or the Edit form: no AI call. */
    const update = async (fieldValues: Record<string, string | string[]>) => {
        const updated = await post('update', { fields: fieldValues });
        if (updated && !(updated.fields ?? []).some(f => f.error)) setEditing(false);
    };

    const statusLabel: Record<CardStatus, string> = {
        pending: asking ? t('Needs an answer') : t('Waiting for you'),
        done: t('Done'),
        failed: t('Failed'),
        cancelled: t('Cancelled'),
        expired: t('Expired'),
        undone: t('Undone'),
    };

    const known = knownValues(fields);

    return (
        <Card className={pending ? 'border-violet-300 dark:border-violet-700' : ''}>
            <CardContent className="space-y-3 p-3">
                <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-medium">{card.summary}</p>
                    <Badge variant={card.status === 'done' ? 'default' : card.status === 'failed' ? 'destructive' : 'secondary'}>
                        {statusLabel[card.status]}
                    </Badge>
                </div>

                {pending && editing ? (
                    <>
                        <CardForm cardId={card.id} fields={fields} values={values} onChange={(name, value) => setValues(prev => ({ ...prev, [name]: value }))} />
                        <div className="flex gap-2">
                            <Button size="sm" onClick={() => update(values)} disabled={busy !== null}>
                                {busy === 'update' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Check className="mr-1 h-3 w-3" />}
                                {t('Save')}
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => { setEditing(false); setValues(formValues(fields)); }} disabled={busy !== null}>
                                {t('Back')}
                            </Button>
                        </div>
                    </>
                ) : asking && askField ? (
                    <>
                        <ChoiceButtons field={askField} question={card.question ?? askField.label} disabled={busy !== null} onPick={value => update({ [askField.name]: value })} />
                        {known && <p className="text-xs text-muted-foreground">{known}</p>}
                    </>
                ) : (
                    <>
                        {Object.keys(card.details).length > 0 ? (
                            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                {Object.entries(card.details).map(([label, value]) => (
                                    <div key={label} className="contents">
                                        <dt className="text-muted-foreground">{label}</dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        ) : known && <p className="text-xs text-muted-foreground">{known}</p>}
                        {card.items.length > 0 && (
                            <ul className="max-h-48 list-disc space-y-0.5 overflow-y-auto rounded-md bg-muted/50 py-2 pl-6 pr-2 text-xs">
                                {card.items.map((item, i) => <li key={i}>{item}</li>)}
                            </ul>
                        )}
                    </>
                )}

                {pending && card.form_error && <p className="text-xs text-destructive">{card.form_error}</p>}
                {card.error && <p className="text-xs text-destructive">{card.error}</p>}

                {pending && !editing && !asking && card.confirm_phrase && (
                    <div className="space-y-1">
                        <Label htmlFor={`phrase-${card.id}`} className="text-xs">
                            {t('This cannot be undone. Type {{phrase}} to confirm.', { phrase: card.confirm_phrase })}
                        </Label>
                        <Input id={`phrase-${card.id}`} value={phrase} onChange={e => setPhrase(e.target.value)} className="h-8 text-xs" autoComplete="off" />
                    </div>
                )}

                {pending && !editing && (
                    <div className="flex flex-wrap gap-2">
                        {!asking && (
                            <Button size="sm" onClick={() => post('confirm', card.confirm_phrase ? { phrase } : {})} disabled={busy !== null || !phraseOk || !!card.form_error}>
                                {busy === 'confirm' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Check className="mr-1 h-3 w-3" />}
                                {t('Confirm')}
                            </Button>
                        )}
                        {fields.length > 0 && (
                            <Button size="sm" variant="outline" onClick={() => setEditing(true)} disabled={busy !== null}>
                                <Pencil className="mr-1 h-3 w-3" />
                                {t('Edit')}
                            </Button>
                        )}
                        <Button size="sm" variant="ghost" onClick={() => post('cancel')} disabled={busy !== null}>
                            <X className="mr-1 h-3 w-3" />
                            {t('Cancel')}
                        </Button>
                    </div>
                )}

                {card.status === 'done' && (card.link || undoOpen) && (
                    <div className="flex flex-wrap items-center gap-3">
                        {card.link && (
                            <Link href={card.link} className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                {t('Open record')} <ExternalLink className="h-3 w-3" />
                            </Link>
                        )}
                        {undoOpen && (
                            <Button size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={() => post('undo')} disabled={busy !== null}>
                                {busy === 'undo' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Undo2 className="mr-1 h-3 w-3" />}
                                {t('Undo')}
                            </Button>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

const MAX_CHOICE_BUTTONS = 8;

/**
 * The answer to one draft question: the choices as buttons (the rest in a
 * short list), or a small box for a name, email or date. One click updates
 * the draft on the server; no AI call.
 */
function ChoiceButtons({ field, question, disabled, onPick }: {
    field: FormFieldState;
    question: string;
    disabled: boolean;
    onPick: (value: string | string[]) => void;
}) {
    const { t } = useTranslation();
    const [text, setText] = useState('');
    const [picked, setPicked] = useState<string[]>([]);
    const shown = field.options.slice(0, MAX_CHOICE_BUTTONS);
    const rest = field.options.slice(MAX_CHOICE_BUTTONS);

    return (
        <div className="space-y-2">
            <p className="text-sm">{question}</p>
            {field.note && <p className="text-xs text-amber-600 dark:text-amber-500">{field.note}</p>}

            {field.type === 'select' && (
                <div className="flex flex-wrap items-center gap-1.5">
                    {shown.map(option => (
                        <Button key={option.value} type="button" size="sm" variant="outline" className="h-7 px-2.5 text-xs" disabled={disabled} onClick={() => onPick(option.value)}>
                            {option.label}
                        </Button>
                    ))}
                    {rest.length > 0 && (
                        <select className={`${fieldClass} h-7 w-auto`} value="" disabled={disabled} onChange={e => e.target.value && onPick(e.target.value)} aria-label={t('More choices')}>
                            <option value="">{t('More…')}</option>
                            {rest.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    )}
                </div>
            )}

            {field.type === 'multi' && (
                <div className="space-y-2">
                    <div className="flex flex-wrap gap-1.5">
                        {field.options.slice(0, 20).map(option => {
                            const on = picked.includes(option.value);
                            return (
                                <Button
                                    key={option.value}
                                    type="button"
                                    size="sm"
                                    variant={on ? 'default' : 'outline'}
                                    className="h-7 px-2.5 text-xs"
                                    aria-pressed={on}
                                    disabled={disabled}
                                    onClick={() => setPicked(prev => (on ? prev.filter(v => v !== option.value) : [...prev, option.value]))}
                                >
                                    {option.label}
                                </Button>
                            );
                        })}
                    </div>
                    <Button type="button" size="sm" className="h-7 text-xs" disabled={disabled || picked.length === 0} onClick={() => onPick(picked)}>
                        {t('Done')}
                    </Button>
                </div>
            )}

            {(field.type === 'text' || field.type === 'date' || field.type === 'number') && (
                <form className="flex gap-2" onSubmit={(e: FormEvent) => { e.preventDefault(); if (text.trim()) onPick(text.trim()); }}>
                    <Input type={field.type} min={field.type === 'number' ? 0 : undefined} step={field.type === 'number' ? 'any' : undefined} value={text} onChange={e => setText(e.target.value)} className="h-8 text-xs" autoFocus />
                    <Button type="submit" size="sm" className="h-8" disabled={disabled || !text.trim()}>{t('OK')}</Button>
                </form>
            )}
        </div>
    );
}

const isEmpty = (value: string | string[] | null | undefined) =>
    value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);

function formValues(fields: FormFieldState[]): Record<string, string | string[]> {
    return Object.fromEntries(fields.map(f => [f.name, f.type === 'multi' ? (Array.isArray(f.value) ? f.value : []) : (f.value as string | null) ?? '']));
}

const fieldClass = 'h-8 w-full rounded-md border border-input bg-background px-2 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

/**
 * The editable part of a form card. Values the user named are pre-filled;
 * the rest are picked here, from lists the server built for this user.
 */
function CardForm({ cardId, fields, values, onChange }: {
    cardId: number;
    fields: FormFieldState[];
    values: Record<string, string | string[]>;
    onChange: (name: string, value: string | string[]) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-2.5">
            {fields.map(field => {
                const id = `card-${cardId}-${field.name}`;
                const value = values[field.name];
                const invalid = !!field.error;

                return (
                    <div key={field.name} className="space-y-1">
                        <Label htmlFor={id} className="text-xs">
                            {field.label}
                            {field.required && <span className="text-destructive"> *</span>}
                        </Label>

                        {field.type === 'select' && (
                            <select
                                id={id}
                                className={`${fieldClass} ${invalid ? 'border-destructive' : ''}`}
                                value={(value as string) ?? ''}
                                onChange={e => onChange(field.name, e.target.value)}
                                aria-invalid={invalid}
                            >
                                <option value="">{t('Choose…')}</option>
                                {field.options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                        )}

                        {field.type === 'multi' && (
                            <div id={id} className={`max-h-36 space-y-1 overflow-y-auto rounded-md border p-2 ${invalid ? 'border-destructive' : ''}`}>
                                {field.options.length === 0 && <p className="text-xs text-muted-foreground">{t('Nobody to choose from.')}</p>}
                                {field.options.map(option => {
                                    const selected = Array.isArray(value) && value.includes(option.value);
                                    return (
                                        <label key={option.value} className="flex cursor-pointer items-center gap-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={selected}
                                                onChange={() => {
                                                    const current = Array.isArray(value) ? value : [];
                                                    onChange(field.name, selected ? current.filter(v => v !== option.value) : [...current, option.value]);
                                                }}
                                            />
                                            {option.label}
                                        </label>
                                    );
                                })}
                            </div>
                        )}

                        {(field.type === 'date' || field.type === 'text' || field.type === 'number') && (
                            <Input
                                id={id}
                                type={field.type}
                                min={field.type === 'number' ? 0 : undefined}
                                step={field.type === 'number' ? 'any' : undefined}
                                className={`h-8 text-xs ${invalid ? 'border-destructive' : ''}`}
                                value={(value as string) ?? ''}
                                onChange={e => onChange(field.name, e.target.value)}
                                aria-invalid={invalid}
                            />
                        )}

                        {field.error
                            ? <p className="text-xs text-destructive">{field.error}</p>
                            : field.note && <p className="text-xs text-amber-600 dark:text-amber-500">{field.note}</p>}
                    </div>
                );
            })}
        </div>
    );
}

// ─── Settings (company owner) ───────────────────────────────────────────────

function SettingsForm({ settings, providers, usage, retentionOptions, idleTimeoutOptions = [15, 30, 60], configured }: Props) {
    const { t } = useTranslation();
    const providerOptions = providers ?? {};
    const [form, setForm] = useState({
        provider: settings?.provider ?? 'anthropic',
        model: settings?.model ?? providerOptions.anthropic?.default_model ?? '',
        api_key: '',
        azure_endpoint: settings?.azure_endpoint ?? '',
        azure_deployment: settings?.azure_deployment ?? '',
        organization: settings?.organization ?? '',
        monthly_token_cap: settings?.monthly_token_cap?.toString() ?? '',
        managers_enabled: settings?.managers_enabled ?? true,
        retention_days: settings?.retention_days ?? 90,
        idle_timeout_minutes: settings?.idle_timeout_minutes ?? 30,
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [testing, setTesting] = useState(false);
    const [saving, setSaving] = useState(false);
    const [testResult, setTestResult] = useState<{ passed: boolean; message: string } | null>(null);

    const set = (key: keyof typeof form, value: any) => setForm(prev => ({ ...prev, [key]: value }));
    const provider = providerOptions[form.provider];
    const keyChanged = !settings || settings.provider !== form.provider;

    const payload = () => ({
        ...form,
        monthly_token_cap: form.monthly_token_cap === '' ? null : Number(form.monthly_token_cap),
    });

    const changeProvider = (value: string) => {
        setForm(prev => ({ ...prev, provider: value, model: providerOptions[value]?.default_model ?? '' }));
        setTestResult(null);
    };

    const test = async () => {
        setTesting(true);
        setTestResult(null);
        setErrors({});
        try {
            const { data } = await axios.post(route('ai-assistant.settings.test'), payload());
            setTestResult(data);
        } catch (error: any) {
            const fieldErrors = error?.response?.data?.errors;
            if (fieldErrors) setErrors(Object.fromEntries(Object.entries(fieldErrors).map(([k, v]) => [k, (v as string[])[0]])));
            else toast.error(errorMessage(error, t('The test could not run.')));
        } finally {
            setTesting(false);
        }
    };

    const save = (event: FormEvent) => {
        event.preventDefault();
        setSaving(true);
        router.put(route('ai-assistant.settings.update'), payload(), {
            preserveScroll: true,
            onError: e => setErrors(e as Record<string, string>),
            onSuccess: () => { setErrors({}); set('api_key', ''); },
            onFinish: () => setSaving(false),
        });
    };

    const disconnect = () => {
        if (!confirm(t('Disconnect the AI provider? The assistant stops working until a new key is saved.'))) return;
        router.delete(route('ai-assistant.settings.destroy'), { preserveScroll: true });
    };

    const field = (name: string) => errors[name] && <p className="text-xs text-destructive">{errors[name]}</p>;

    return (
        <form onSubmit={save} className="grid gap-6 lg:grid-cols-[1fr_320px]">
            <Card>
                <CardHeader>
                    <CardTitle>{configured ? t('AI provider') : t('Connect your AI provider')}</CardTitle>
                    <CardDescription>
                        {t('The assistant runs on your company\'s own AI account. Your messages, and the Sundal records the assistant reads to answer them, are sent to this provider using your API key, under your agreement with that provider.')}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label>{t('Provider')}</Label>
                            <Select value={form.provider} onValueChange={changeProvider}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {Object.entries(providerOptions).map(([id, option]) => (
                                        <SelectItem key={id} value={id}>{option.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {field('provider')}
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Model')}</Label>
                            {/* Tested models are suggested; any other model id can be typed. */}
                            <Input
                                list="ai-model-options"
                                value={form.model}
                                onChange={e => set('model', e.target.value)}
                                placeholder={t('Model name')}
                            />
                            <datalist id="ai-model-options">
                                {provider?.models.map(m => <option key={m.id} value={m.id}>{m.label}</option>)}
                            </datalist>
                            {field('model')}
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label>{t('API key')}</Label>
                        <Input
                            type="password"
                            autoComplete="off"
                            value={form.api_key}
                            onChange={e => set('api_key', e.target.value)}
                            placeholder={settings && !keyChanged ? t('Saved ({{key}}). Leave empty to keep it.', { key: settings.masked_key }) : t('Paste your API key')}
                        />
                        <p className="text-xs text-muted-foreground">{t('Stored encrypted. It is never shown again, only replaced.')}</p>
                        {field('api_key')}
                    </div>

                    {form.provider === 'azure_openai' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label>{t('Azure endpoint')}</Label>
                                <Input value={form.azure_endpoint} onChange={e => set('azure_endpoint', e.target.value)} placeholder="https://my-company.openai.azure.com" />
                                {field('azure_endpoint')}
                            </div>
                            <div className="space-y-1">
                                <Label>{t('Deployment name')}</Label>
                                <Input value={form.azure_deployment} onChange={e => set('azure_deployment', e.target.value)} />
                                {field('azure_deployment')}
                            </div>
                        </div>
                    )}

                    {form.provider === 'openai' && (
                        <div className="space-y-1">
                            <Label>{t('Organization ID (optional)')}</Label>
                            <Input value={form.organization} onChange={e => set('organization', e.target.value)} />
                            {field('organization')}
                        </div>
                    )}

                    {testResult && (
                        <p className={`text-sm ${testResult.passed ? 'text-green-600' : 'text-destructive'}`}>{testResult.message}</p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" onClick={test} disabled={testing}>
                            {testing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {t('Test connection')}
                        </Button>
                        <Button type="submit" disabled={saving}>{t('Save')}</Button>
                        {configured && <Button type="button" variant="ghost" className="text-destructive" onClick={disconnect}>{t('Disconnect')}</Button>}
                    </div>
                </CardContent>
            </Card>

            <div className="space-y-6">
                <Card>
                    <CardHeader><CardTitle className="text-base">{t('Access and limits')}</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between gap-4">
                            <Label htmlFor="managers_enabled">{t('Allow managers to use the assistant')}</Label>
                            <Switch id="managers_enabled" checked={form.managers_enabled} onCheckedChange={v => set('managers_enabled', v)} />
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Monthly token cap (optional)')}</Label>
                            <Input type="number" min={1000} value={form.monthly_token_cap} onChange={e => set('monthly_token_cap', e.target.value)} />
                            {field('monthly_token_cap')}
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Keep chats for')}</Label>
                            <Select value={String(form.retention_days)} onValueChange={v => set('retention_days', Number(v))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {retentionOptions.map(days => <SelectItem key={days} value={String(days)}>{t('{{days}} days', { days })}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">{t('The log of actions the assistant took is kept for the life of the workspace.')}</p>
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Pause the AI Assistant after')}</Label>
                            <Select value={String(form.idle_timeout_minutes)} onValueChange={v => set('idle_timeout_minutes', Number(v))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {idleTimeoutOptions.map(minutes => <SelectItem key={minutes} value={String(minutes)}>{t('{{minutes}} minutes without activity', { minutes })}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">{t('It then waits for Continue. Chats and waiting cards are kept.')}</p>
                        </div>
                    </CardContent>
                </Card>
                {usage && (
                    <Card>
                        <CardHeader><CardTitle className="text-base">{t('Usage this month')}</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-2xl font-semibold">{usage.tokens_this_month.toLocaleString()}</p>
                                <p className="text-xs text-muted-foreground">
                                    {settings?.monthly_token_cap ? t('of {{cap}} tokens', { cap: settings.monthly_token_cap.toLocaleString() }) : t('tokens, no cap set')}
                                </p>
                                {settings?.monthly_token_cap ? (() => {
                                    const pct = Math.min(100, (usage.tokens_this_month / settings.monthly_token_cap) * 100);
                                    return (
                                        <div className="mt-2 space-y-1">
                                            <div className="h-2 rounded-full bg-muted" role="progressbar" aria-valuenow={Math.round(pct)} aria-valuemin={0} aria-valuemax={100}>
                                                <div className={`h-2 rounded-full ${pct >= 80 ? 'bg-amber-500' : 'bg-primary'}`} style={{ width: `${pct}%` }} />
                                            </div>
                                            {pct >= 80 && <p className="text-xs text-amber-600">{t('{{pct}}% of the monthly cap used', { pct: Math.round(pct) })}</p>}
                                        </div>
                                    );
                                })() : null}
                            </div>
                            {usage.daily.length > 0 && (
                                <div>
                                    <p className="mb-2 text-xs text-muted-foreground">{t('Tokens per day, last 30 days')}</p>
                                    <UsageChart daily={usage.daily} />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </form>
    );
}
