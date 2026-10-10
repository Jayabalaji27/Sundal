import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent, type ReactNode } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import {
    Archive, ArchiveRestore, ArrowLeft, ArrowUp, Bot, Bug, Check, CheckCheck, ChevronDown, ChevronsUpDown, Clock, Copy, CornerDownLeft, ExternalLink,
    FolderKanban, HelpCircle, ListTodo, Loader2, LogOut, MessageSquare, MoreHorizontal, PanelLeftClose, PanelLeftOpen, Pencil, Plus,
    Receipt, Settings as SettingsIcon, Sparkles, Star, Trash2, Undo2, UserRound, Users, X,
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
import { AiModeShell, type AiModeConfig } from '@/components/ai-mode-shell';
import { ImportTable, type ImportChanges, type ImportView } from '@/components/ai-import-card';
import { PlanList, type PlanChanges, type PlanView } from '@/components/ai-plan-card';
import { AttachMenu, DropZone, FileChip, FileSuggestions, SundalFilesDialog, openDrivePicker, useAttachments, type AttachmentChip, type GoogleDriveConfig } from '@/components/ai-attachments';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { notifySignedOut } from '@/lib/ai-mode';

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
    /** A richer card: the import table (sheet rows → bugs or tasks). */
    view?: CardView | null;
}

type CardView = ImportView | PlanView;

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
    /** Files sent with this message. */
    attachments?: AttachmentChip[];
    /** Shown in the chat only, never saved: the provider refused or failed. */
    error?: boolean;
}

interface Conversation {
    id: number;
    title: string | null;
    topic?: string | null;
    last_message_at?: string | null;
    is_favorite?: boolean;
    archived?: boolean;
    /** The latest message, one line. */
    preview?: string;
    /** Confirm cards still waiting for the user in this chat. */
    waiting?: number;
}

type ListFilter = 'all' | 'favorites' | 'archived';

type ListCounts = Record<Exclude<ListFilter, 'all'>, number>;

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
    conversationsHasMore?: boolean;
    conversationCounts?: ListCounts;
    settings: Settings | null;
    usage: { tokens_this_month: number; daily: { date: string; tokens: number }[] } | null;
    providers: Record<string, ProviderOption> | null;
    retentionOptions: number[];
    idleTimeoutOptions?: number[];
    topics: TopicOption[];
    /** The connected provider and model, for the pill in the chat header. */
    model?: ModelInfo | null;
    /** The Google Drive picker, when the install has it set up. */
    googleDrive?: GoogleDriveConfig | null;
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
                hasMore={props.conversationsHasMore ?? false}
                counts={props.conversationCounts ?? { favorites: 0, archived: 0 }}
                topics={props.topics}
                googleDrive={props.googleDrive ?? null}
                model={props.model ?? null}
                standalone={standalone}
                onOpenSettings={canOpenSettings ? () => setView('settings') : undefined}
            />
        );
        framed = false;
    }

    // AI mode tab: full screen, its own header and locks, no Sundal sidebar.
    // The chat fills the whole page; settings and notices keep a readable width.
    if (standalone && props.aiMode) {
        return (
            <AiModeShell config={props.aiMode} model={props.model}>
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

function Chat({ conversations: initial, hasMore: initialHasMore, counts: initialCounts, topics, googleDrive, model, standalone, onOpenSettings }: {
    googleDrive: GoogleDriveConfig | null;
    conversations: Conversation[];
    hasMore: boolean;
    counts: ListCounts;
    topics: TopicOption[];
    model: ModelInfo | null;
    standalone: boolean;
    /** Owners: opens the settings view. */
    onOpenSettings?: () => void;
}) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const [conversations, setConversations] = useState<Conversation[]>(initial);
    // The sidebar list: which filter, how many pages are loaded, and the filter counts.
    const [filter, setFilter] = useState<ListFilter>('all');
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(initialHasMore);
    const [counts, setCounts] = useState<ListCounts>(initialCounts);
    const [loadingList, setLoadingList] = useState(false);
    // "Ask how to do something": the Help topic for the new chat about to open.
    const nextTopicRef = useRef<string | null>(null);
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
    // Files in the message box (+ button, drag and drop, paste).
    const attach = useAttachments();
    const [sundalOpen, setSundalOpen] = useState(false);
    // Set when send() creates a conversation: its messages are already on screen
    // (including any error bubble), so don't reload and overwrite them.
    const createdHereRef = useRef<number | null>(null);

    useEffect(() => {
        if (activeId === null) {
            setMessages([]);
            setTopic(nextTopicRef.current);
            nextTopicRef.current = null;
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
        // Scroll only the message list; scrollIntoView would also move the page around it.
        const list = bottomRef.current?.parentElement?.parentElement;
        list?.scrollTo({ top: list.scrollHeight, behavior: 'smooth' });
    }, [messages, sending]);

    const send = async (text: string) => {
        const content = text.trim();
        const files = attach.ready;
        if ((!content && files.length === 0) || sending || attach.uploading) return;
        setSending(true);
        setInput('');
        const optimistic: Message = {
            id: -Date.now(), role: 'user', content: content || t('Please look at the attached file.'), created_at: new Date().toISOString(), cards: [], attachments: files,
        };
        setMessages(prev => [...prev, optimistic]);

        const apply = (data: any) => {
            if (!data?.conversation) return;
            // The message was saved with its files: they leave the box.
            attach.clear();
            setMessages(prev => withFresh(prev.filter(m => m.id !== optimistic.id), data.messages));
            if (activeId === null) createdHereRef.current = data.conversation.id;
            setActiveId(current => current ?? data.conversation.id);
            const row: Conversation = { ...data.conversation, last_message_at: data.conversation.last_message_at ?? new Date().toISOString() };
            setConversations(prev => (filter === 'all' || prev.some(c => c.id === row.id)
                ? [row, ...prev.filter(c => c.id !== row.id)]
                : prev));
        };

        try {
            const { data } = await axios.post(route('ai-assistant.send'), { conversation_id: activeId, content, topic: topic ?? '', attachment_ids: files.map(f => f.id) });
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
            refreshCounts();
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

    /** Load a page of the list for a filter (page 1 replaces the list). */
    const loadList = async (nextFilter: ListFilter, nextPage = 1) => {
        setLoadingList(true);
        try {
            const { data } = await axios.get(route('ai-assistant.conversations.index'), { params: { filter: nextFilter, page: nextPage } });
            setConversations(prev => (nextPage === 1 ? data.conversations : [...prev, ...data.conversations.filter((c: Conversation) => !prev.some(p => p.id === c.id))]));
            setHasMore(data.has_more);
            setCounts(data.counts);
            setPage(nextPage);
        } catch (error) {
            toast.error(errorMessage(error, t('Could not load your chats.')));
        } finally {
            setLoadingList(false);
        }
    };

    const chooseFilter = (next: ListFilter) => {
        setFilter(next);
        loadList(next);
    };

    /** The numbers next to Favorites and Archive. */
    const refreshCounts = () => {
        axios.get(route('ai-assistant.conversations.index'), { params: { filter, page: 1 } })
            .then(({ data }) => setCounts(data.counts))
            .catch(() => undefined);
    };

    /** Star or archive: update the row, and drop it from a list it no longer belongs to. */
    const change = async (conversation: Conversation, values: { is_favorite?: boolean; archived?: boolean }) => {
        try {
            const { data } = await axios.patch(route('ai-assistant.conversations.update', conversation.id), values);
            const row: Conversation = data.conversation;
            const belongs = filter === 'all' ? !row.archived
                : filter === 'favorites' ? !!row.is_favorite && !row.archived
                : filter === 'archived' ? !!row.archived
                : !row.archived;
            setConversations(prev => (belongs ? prev.map(c => (c.id === row.id ? row : c)) : prev.filter(c => c.id !== row.id)));
            setCounts(data.counts);
            if (values.archived !== undefined) {
                toast.success(values.archived ? t('Chat archived') : t('Chat moved out of the archive'));
            }
        } catch (error) {
            toast.error(errorMessage(error, t('Could not update the chat.')));
        }
    };

    const remove = async (conversation: Conversation) => {
        if (!confirm(t('Delete this conversation? The record of actions taken stays in the audit log.'))) return;
        try {
            await axios.delete(route('ai-assistant.conversations.destroy', conversation.id));
            setConversations(prev => prev.filter(c => c.id !== conversation.id));
            if (activeId === conversation.id) setActiveId(null);
            refreshCounts();
        } catch (error) {
            toast.error(errorMessage(error, t('Could not delete the conversation.')));
        }
    };

    /** Help: a new chat with the Help topic on. */
    const askHelp = () => {
        if (activeId === null) {
            setTopic('help');
        } else {
            nextTopicRef.current = 'help';
            open(null);
        }
    };

    const open = (id: number | null) => {
        setActiveId(id);
        // On a phone the open list covers the chat: close it once a chat is picked.
        if (isSmallScreen()) setListOpen(false);
    };

    const sidebarProps: SidebarProps = {
        conversations,
        activeId,
        filter,
        counts,
        hasMore,
        loadingList,
        standalone,
        helpTopic: topics.some(o => o.key === 'help'),
        onFilter: chooseFilter,
        onOpen: open,
        onMore: () => loadList(filter, page + 1),
        onFavorite: c => change(c, { is_favorite: !c.is_favorite }),
        onArchive: c => change(c, { archived: !c.archived }),
        onDelete: remove,
        onCollapse: toggleList,
        onAskHelp: askHelp,
        onOpenSettings,
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
            attach={attach}
            onSundal={() => setSundalOpen(true)}
            onDrive={googleDrive ? () => {
                openDrivePicker(googleDrive, (fileId, name, token) => attach.addChip(
                    `drive-${fileId}-${Date.now()}`,
                    name,
                    () => axios.post(route('ai-assistant.attachments.from-drive'), { file_id: fileId, access_token: token }),
                )).catch(() => toast.error(t('Google Drive could not be opened. Try again.')));
            } : undefined}
        />
    );

    return (
        <div className={`relative flex overflow-hidden bg-background ${standalone ? 'h-full min-h-[480px]' : 'h-[calc(100dvh-11rem)] min-h-[560px] rounded-xl border'}`}>
            {/* Sidebar: open, or a narrow rail of icons. On a phone the open sidebar slides over the chat. */}
            {listOpen ? (
                <>
                    <div className="absolute inset-0 z-20 bg-black/30 md:hidden" onClick={toggleList} aria-hidden />
                    <aside id="ai-chat-list" className="absolute inset-y-0 left-0 z-30 flex w-72 shrink-0 flex-col border-r bg-background shadow-xl md:static md:z-auto md:bg-muted/40 md:shadow-none">
                        <ChatSidebar {...sidebarProps} />
                    </aside>
                </>
            ) : (
                <aside className="flex w-14 shrink-0 flex-col items-center gap-1.5 border-r bg-muted/40 py-3">
                    <ChatSidebarRail {...sidebarProps} />
                </aside>
            )}

            <SundalFilesDialog open={sundalOpen} onOpenChange={setSundalOpen} onPick={file => attach.addFromSundal(file.id, file.name, file.size)} />
            <DropZone onFiles={attach.add} className="flex min-h-0 min-w-0 flex-1 flex-col">
            <section className="flex min-h-0 min-w-0 flex-1 flex-col">
                {/* The Sundal page has no AI mode bar: the model sits above the chat. */}
                {!standalone && model && (
                    <header className="flex items-center gap-2 border-b px-3 py-2.5 sm:px-4">
                        <div className="flex min-w-0 items-center gap-2" title={`${model.provider} · ${model.name}`}>
                            <Sparkles className="h-4 w-4 shrink-0 text-violet-500" />
                            <span className="truncate text-sm font-semibold">{model.name}</span>
                            <span className="hidden shrink-0 rounded-full border px-2 py-0.5 text-[11px] text-muted-foreground sm:inline">{model.provider}</span>
                        </div>
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
            </DropZone>
        </div>
    );
}

/** The sidebar's filters, after the New chat button. */
const LIST_FILTERS: { key: Exclude<ListFilter, 'all'>; label: string; hint: string; icon: typeof Bot }[] = [
    { key: 'favorites', label: 'Favorites', hint: 'Chats you starred', icon: Star },
    { key: 'archived', label: 'Archive', hint: 'Chats you put away', icon: Archive },
];

const ROLE_LABELS: Record<string, string> = { owner: 'Company owner', manager: 'Manager' };

interface SidebarProps {
    conversations: Conversation[];
    activeId: number | null;
    filter: ListFilter;
    counts: ListCounts;
    hasMore: boolean;
    loadingList: boolean;
    standalone: boolean;
    helpTopic: boolean;
    onFilter: (filter: ListFilter) => void;
    onOpen: (id: number | null) => void;
    onMore: () => void;
    onFavorite: (conversation: Conversation) => void;
    onArchive: (conversation: Conversation) => void;
    onDelete: (conversation: Conversation) => void;
    onCollapse: () => void;
    onAskHelp: () => void;
    onOpenSettings?: () => void;
}

/** The open sidebar: New chat, the filters, the chats, then Settings, Help and the user. */
function ChatSidebar(props: SidebarProps) {
    const { t } = useTranslation();
    const { conversations, activeId, filter, counts, hasMore, loadingList } = props;
    const current = LIST_FILTERS.find(f => f.key === filter);

    return (
        <>
            <div className="flex items-center gap-2 px-4 pb-1 pt-3">
                <AssistantMark />
                <span className="truncate text-sm font-semibold">{t('AI Assistant')}</span>
                <Button variant="ghost" size="icon" className="ml-auto h-8 w-8" onClick={props.onCollapse} aria-label={t('Hide chats')} title={t('Hide chats')} aria-expanded>
                    <PanelLeftClose className="h-4 w-4" />
                </Button>
            </div>

            <div className="space-y-1 px-3 pb-2 pt-2">
                <Button className="h-9 w-full justify-start gap-2 rounded-lg" onClick={() => props.onOpen(null)}>
                    <Plus className="h-4 w-4" />
                    {t('New chat')}
                </Button>
                <nav className="space-y-0.5 pt-2" aria-label={t('Chat filters')}>
                    {LIST_FILTERS.map(item => {
                        const on = filter === item.key;
                        const count = counts[item.key];
                        return (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() => props.onFilter(on ? 'all' : item.key)}
                                aria-pressed={on}
                                title={t(item.hint)}
                                className={`flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-colors ${
                                    on ? 'bg-background font-medium text-foreground shadow-sm ring-1 ring-border' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'
                                }`}
                            >
                                <item.icon className={`h-4 w-4 ${on ? 'text-violet-600' : ''}`} />
                                <span className="flex-1 text-left">{t(item.label)}</span>
                                {count > 0 && (
                                    <span className="min-w-5 rounded-full px-1.5 text-center text-[11px] tabular-nums text-muted-foreground">
                                        {count}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </nav>
            </div>

            <div className="flex items-center justify-between px-4 pb-1 pt-2">
                <p className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{current ? t(current.label) : t('Recent chats')}</p>
                {current && (
                    <button type="button" className="text-[11px] text-violet-600 hover:underline" onClick={() => props.onFilter('all')}>
                        {t('Show all')}
                    </button>
                )}
            </div>
            <div className="flex-1 overflow-y-auto px-3 pb-3" aria-label={t('Conversations')} role="list">
                {!loadingList && conversations.length === 0 && (
                    <p className="px-1 py-4 text-xs text-muted-foreground">
                        {filter === 'all' ? t('No conversations yet. Your chats appear here.') : t('Nothing here yet.')}
                    </p>
                )}
                <div className="space-y-1.5">
                    {conversations.map(c => (
                        <ConversationItem
                            key={c.id}
                            conversation={c}
                            active={c.id === activeId}
                            onOpen={() => props.onOpen(c.id)}
                            onFavorite={() => props.onFavorite(c)}
                            onArchive={() => props.onArchive(c)}
                            onDelete={() => props.onDelete(c)}
                        />
                    ))}
                </div>
                {loadingList && <Loader2 className="mx-auto mt-3 h-4 w-4 animate-spin text-muted-foreground" />}
                {hasMore && !loadingList && (
                    <Button variant="outline" size="sm" className="mt-2 h-8 w-full gap-1 rounded-lg bg-background text-xs" onClick={props.onMore}>
                        {t('Show more')}
                        <ChevronDown className="h-3.5 w-3.5" />
                    </Button>
                )}
            </div>

            <div className="space-y-0.5 border-t px-3 py-2">
                {props.onOpenSettings && (
                    <SidebarLink icon={SettingsIcon} label={t('Settings')} onClick={props.onOpenSettings} />
                )}
                <HelpDialog helpTopic={props.helpTopic} onAskHelp={props.onAskHelp}>
                    <SidebarLink icon={HelpCircle} label={t('Help')} />
                </HelpDialog>
            </div>
            <div className="border-t p-2">
                <ProfileMenu standalone={props.standalone} />
            </div>
        </>
    );
}

/** The collapsed sidebar: icons only, same actions. */
function ChatSidebarRail(props: SidebarProps) {
    const { t } = useTranslation();

    return (
        <>
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={props.onCollapse} aria-label={t('Show chats')} title={t('Show chats')} aria-expanded={false}>
                <PanelLeftOpen className="h-4 w-4" />
            </Button>
            <Button size="icon" className="h-8 w-8 rounded-lg" onClick={() => props.onOpen(null)} aria-label={t('New chat')} title={t('New chat')}>
                <Plus className="h-4 w-4" />
            </Button>
            <div className="my-1 h-px w-6 bg-border" aria-hidden />
            {LIST_FILTERS.map(item => (
                <Button
                    key={item.key}
                    variant="ghost"
                    size="icon"
                    className={`relative h-8 w-8 ${props.filter === item.key ? 'bg-background text-violet-600 shadow-sm ring-1 ring-border' : 'text-muted-foreground'}`}
                    onClick={() => { props.onFilter(item.key); props.onCollapse(); }}
                    aria-label={t(item.label)}
                    title={`${t(item.label)}${props.counts[item.key] ? ` (${props.counts[item.key]})` : ''}`}
                >
                    <item.icon className="h-4 w-4" />
                </Button>
            ))}
            <div className="mt-auto flex flex-col items-center gap-1">
                {props.onOpenSettings && (
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground" onClick={props.onOpenSettings} aria-label={t('Settings')} title={t('Settings')}>
                        <SettingsIcon className="h-4 w-4" />
                    </Button>
                )}
                <HelpDialog helpTopic={props.helpTopic} onAskHelp={props.onAskHelp}>
                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground" aria-label={t('Help')} title={t('Help')}>
                        <HelpCircle className="h-4 w-4" />
                    </Button>
                </HelpDialog>
                <ProfileMenu standalone={props.standalone} compact />
            </div>
        </>
    );
}

function SidebarLink({ icon: Icon, label, onClick, ...rest }: { icon: typeof Bot; label: string; onClick?: () => void } & Record<string, unknown>) {
    return (
        <button
            type="button"
            onClick={onClick}
            {...rest}
            className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-muted-foreground transition-colors hover:bg-background/70 hover:text-foreground"
        >
            <Icon className="h-4 w-4" />
            {label}
        </button>
    );
}

/** One chat in the list: title and time, a one-line preview, and a menu. */
function ConversationItem({ conversation: c, active, onOpen, onFavorite, onArchive, onDelete }: {
    conversation: Conversation;
    active: boolean;
    onOpen: () => void;
    onFavorite: () => void;
    onArchive: () => void;
    onDelete: () => void;
}) {
    const { t } = useTranslation();
    const Icon = (c.topic && TOPIC_ICONS[c.topic]) || MessageSquare;

    return (
        <div
            role="listitem"
            className={`group relative rounded-lg border px-2.5 py-2 transition-colors ${
                active
                    ? 'border-violet-300 bg-violet-50 dark:border-violet-800 dark:bg-violet-950/30'
                    : 'border-border/70 bg-background hover:border-border hover:shadow-sm'
            }`}
        >
            <button
                type="button"
                onClick={onOpen}
                aria-current={active ? 'true' : undefined}
                className="block w-full text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <span className="flex items-center gap-2">
                    <Icon className={`h-4 w-4 shrink-0 ${active ? 'text-violet-600' : 'text-muted-foreground'}`} />
                    <span className={`min-w-0 flex-1 truncate text-sm ${active ? 'font-medium' : ''}`}>{c.title || t('New conversation')}</span>
                    {c.is_favorite && <Star className="h-3 w-3 shrink-0 fill-amber-400 text-amber-400" aria-label={t('Favorite')} />}
                    <span className="shrink-0 pr-5 text-[11px] text-muted-foreground">{t(timeAgo(c.last_message_at))}</span>
                </span>
                <span className="mt-0.5 flex items-center gap-1.5 pl-6">
                    {(c.waiting ?? 0) > 0 && (
                        <span className="shrink-0 rounded-full bg-amber-100 px-1.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                            {t('{{count}} waiting', { count: c.waiting })}
                        </span>
                    )}
                    <span className="truncate text-xs text-muted-foreground">{c.preview || ' '}</span>
                </span>
            </button>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="absolute right-1.5 top-1.5 rounded-md p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-muted hover:text-foreground focus-visible:opacity-100 group-hover:opacity-100 data-[state=open]:opacity-100"
                        aria-label={t('Chat options')}
                        title={t('Chat options')}
                    >
                        <MoreHorizontal className="h-3.5 w-3.5" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuItem onSelect={onFavorite}>
                        <Star className="mr-2 h-4 w-4" />
                        {c.is_favorite ? t('Remove from favorites') : t('Add to favorites')}
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={onArchive}>
                        {c.archived ? <ArchiveRestore className="mr-2 h-4 w-4" /> : <Archive className="mr-2 h-4 w-4" />}
                        {c.archived ? t('Move out of archive') : t('Archive')}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onSelect={onDelete} className="text-destructive focus:text-destructive">
                        <Trash2 className="mr-2 h-4 w-4" />
                        {t('Delete')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

/** How the assistant works, and a way to ask a how-to question. */
function HelpDialog({ helpTopic, onAskHelp, children }: { helpTopic: boolean; onAskHelp: () => void; children: ReactNode }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const tips = [
        { icon: MessageSquare, text: 'Ask in plain words: "What is overdue?", "Assign the login bug to Ravi", "Log 3 hours on Mobile App".' },
        { icon: Check, text: 'Nothing changes until you confirm the card the assistant shows. If something is missing, it asks you with buttons.' },
        { icon: Undo2, text: 'Most changes can be undone for 10 minutes from the card.' },
        { icon: ListTodo, text: 'Pick a topic under the message box to keep a chat about tasks, time, finance and so on.' },
        { icon: Star, text: 'Star chats you come back to, and archive the ones you are done with.' },
        { icon: CornerDownLeft, text: 'Enter sends; Shift + Enter starts a new line.' },
    ];

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('How the AI Assistant works')}</DialogTitle>
                    <DialogDescription>{t('It works inside Sundal with your role\'s permissions, and only does what you confirm.')}</DialogDescription>
                </DialogHeader>
                <ul className="space-y-3 text-sm">
                    {tips.map(tip => (
                        <li key={tip.text} className="flex gap-3">
                            <tip.icon className="mt-0.5 h-4 w-4 shrink-0 text-violet-600" />
                            <span>{t(tip.text)}</span>
                        </li>
                    ))}
                </ul>
                {helpTopic && (
                    <DialogFooter>
                        <Button onClick={() => { setOpen(false); onAskHelp(); }}>
                            <HelpCircle className="mr-2 h-4 w-4" />
                            {t('Ask how to do something in Sundal')}
                        </Button>
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}

/** The signed-in user at the bottom of the sidebar, with Profile and Sign out. */
function ProfileMenu({ standalone, compact = false }: { standalone: boolean; compact?: boolean }) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const user = auth?.user ?? {};
    const role = ROLE_LABELS[user.workspace_role] ?? '';
    const workspace = user.current_workspace?.name ?? '';
    const initials = String(user.name ?? '').split(' ').filter(Boolean).slice(0, 2).map((w: string) => w[0]?.toUpperCase()).join('') || '?';

    // AI mode is its own tab: Sundal pages open in a new tab instead of replacing it.
    const visit = (url: string) => (standalone ? window.open(url, '_blank', 'noopener') : router.visit(url));
    const signOut = () => {
        notifySignedOut();
        router.post(route('logout'));
    };

    const avatar = (
        <span className="relative flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-violet-100 text-[11px] font-semibold text-violet-700 dark:bg-violet-900/50 dark:text-violet-200">
            {initials}
            {user.avatar && !String(user.avatar).endsWith('/images/avatar/avatar.png') && (
                <img src={user.avatar} alt="" className="absolute inset-0 h-full w-full object-cover" />
            )}
        </span>
    );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {compact ? (
                    <button type="button" className="rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label={t('Your account')} title={user.name}>
                        {avatar}
                    </button>
                ) : (
                    <button type="button" className="flex w-full items-center gap-2.5 rounded-lg p-1.5 text-left transition-colors hover:bg-background/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label={t('Your account')}>
                        {avatar}
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-medium">{user.name}</span>
                            <span className="block truncate text-[11px] text-muted-foreground">{[t(role), workspace].filter(Boolean).join(' · ')}</span>
                        </span>
                        <ChevronsUpDown className="h-4 w-4 shrink-0 text-muted-foreground" />
                    </button>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent side="top" align="start" className="w-60">
                <DropdownMenuLabel className="font-normal">
                    <span className="block truncate text-sm font-medium">{user.name}</span>
                    <span className="block truncate text-xs text-muted-foreground">{user.email}</span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => visit(route('profile'))}>
                    <UserRound className="mr-2 h-4 w-4" />
                    {t('Profile')}
                </DropdownMenuItem>
                {standalone && (
                    <DropdownMenuItem onSelect={() => visit(route('dashboard'))}>
                        <ExternalLink className="mr-2 h-4 w-4" />
                        {t('Open Sundal')}
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={signOut}>
                    <LogOut className="mr-2 h-4 w-4" />
                    {t('Sign out')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** The message box: grows with the text; topic picker and send button underneath. */
function Composer({ value, onChange, onSend, sending, topics, topic, onTopic, large, attach, onSundal, onDrive }: {
    attach: ReturnType<typeof useAttachments>;
    onSundal: () => void;
    onDrive?: () => void;
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
            {attach.files.length > 0 && (
                <div className="flex flex-wrap gap-2 px-3 pt-3" aria-label={t('Attached files')}>
                    {attach.files.map(file => <FileChip key={file.key} file={file} onRemove={() => attach.remove(file.key)} />)}
                </div>
            )}
            {!value.trim() && <div className="pt-2"><FileSuggestions files={attach.ready} onPick={text => { onChange(text); ref.current?.focus(); }} /></div>}
            <div className="flex gap-2.5 px-4 pt-3.5">
                <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-violet-500" aria-hidden />
                <textarea
                    ref={ref}
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    onKeyDown={onKeyDown}
                    onPaste={e => { if (e.clipboardData.files.length) { e.preventDefault(); attach.add(e.clipboardData.files); } }}
                    placeholder={topic ? t(TOPIC_HINTS[topic]) : t('Ask a question or tell me what to do…')}
                    rows={large ? 3 : 1}
                    maxLength={MAX_LENGTH}
                    aria-label={t('Message')}
                    className="max-h-[200px] w-full resize-none border-0 !bg-transparent p-0 text-sm leading-6 shadow-none outline-none ring-0 placeholder:text-muted-foreground focus:ring-0"
                />
            </div>
            <div className="flex items-center gap-2 px-3 pb-3 pt-2">
                <AttachMenu onFiles={attach.add} onSundal={onSundal} onDrive={onDrive} disabled={attach.full || sending} />
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
                <Button type="submit" size="icon" className="ml-auto h-8 w-8 rounded-full sm:ml-0" disabled={sending || attach.uploading || (!value.trim() && attach.ready.length === 0)} aria-label={attach.uploading ? t('Wait for the files to be read') : t('Send')}>
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
                {(message.attachments?.length ?? 0) > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {message.attachments!.map(file => <FileChip key={file.id} file={file} />)}
                    </div>
                )}
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
    const [busy, setBusy] = useState<'confirm' | 'cancel' | 'undo' | 'update' | 'edit' | null>(null);
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

    const post = async (action: 'confirm' | 'cancel' | 'undo' | 'update' | 'edit', body: object = {}) => {
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
    const view = card.view ?? null;
    // An editable table (sheet import): changes go straight to the server, no AI call.
    const editView = (changes: ImportChanges | PlanChanges) => post('edit', { changes });
    const confirmLabel = view?.type === 'import' && view.kind === 'bugs'
        ? t('Create {{count}} bugs', { count: view.included })
        : view ? t('Create {{count}} tasks', { count: view.included }) : t('Confirm');

    return (
        <Card className={pending ? 'border-violet-300 dark:border-violet-700' : ''}>
            <CardContent className="space-y-3 p-3">
                <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-medium">{card.summary}</p>
                    <Badge variant={card.status === 'done' ? 'default' : card.status === 'failed' ? 'destructive' : 'secondary'}>
                        {statusLabel[card.status]}
                    </Badge>
                </div>

                {pending && view?.type === 'import' ? (
                    <ImportTable view={view} busy={busy !== null} onEdit={editView} />
                ) : pending && view?.type === 'plan' ? (
                    <PlanList view={view} busy={busy !== null} onEdit={editView} />
                ) : pending && editing ? (
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
                            <Button size="sm" onClick={() => post('confirm', card.confirm_phrase ? { phrase } : {})} disabled={busy !== null || !phraseOk || !!card.form_error || !!view?.problem}>
                                {busy === 'confirm' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Check className="mr-1 h-3 w-3" />}
                                {confirmLabel}
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
