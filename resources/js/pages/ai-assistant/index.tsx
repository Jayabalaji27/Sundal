import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Bot, Check, ExternalLink, Loader2, ChevronDown, MessageSquarePlus, Plus, Send, Sparkles, Trash2, Undo2, X } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/components/custom-toast';

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
    /** Form cards: fields the user completes before confirming (no AI call). */
    fields: FormFieldState[];
    form_error: string | null;
}

interface FormFieldState {
    name: string;
    label: string;
    type: 'select' | 'multi' | 'date' | 'text';
    required: boolean;
    value: string | string[] | null;
    options: { value: string; label: string }[];
    /** Why the field was left for the user, e.g. "Several match Ravi". */
    note: string | null;
    error: string | null;
}

interface QuickAction {
    tool: string;
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
    last_tested_at: string | null;
    last_test_passed: boolean | null;
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
    quickActions: QuickAction[];
}

const errorMessage = (error: any, fallback: string): string =>
    error?.response?.data?.error
    ?? error?.response?.data?.message
    ?? (Object.values(error?.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0]
    ?? fallback;

export default function AiAssistantPage(props: Props) {
    const { t } = useTranslation();
    const breadcrumbs = [{ title: t('Dashboard'), href: route('dashboard') }, { title: t('AI Assistant') }];
    const [tab, setTab] = useState(props.configured ? 'assistant' : 'settings');

    let body;
    if (props.access === 'plan') {
        body = <UpgradeNotice />;
    } else if (props.access === 'managers_off') {
        body = <Notice title={t('The AI Assistant is turned off for managers')} text={t('Your company owner has turned the AI Assistant off for managers.')} />;
    } else if (!props.configured && !props.isOwner) {
        body = <Notice title={t('No AI provider connected')} text={t('Ask your company owner to connect an AI provider on this page.')} />;
    } else if (props.isOwner) {
        body = (
            <Tabs value={tab} onValueChange={setTab}>
                <TabsList>
                    <TabsTrigger value="assistant" disabled={!props.configured}>{t('Assistant')}</TabsTrigger>
                    <TabsTrigger value="settings">{t('Settings')}</TabsTrigger>
                </TabsList>
                <TabsContent value="assistant" className="mt-4">{props.configured && <Chat conversations={props.conversations} quickActions={props.quickActions} />}</TabsContent>
                <TabsContent value="settings" className="mt-4"><SettingsForm {...props} /></TabsContent>
            </Tabs>
        );
    } else {
        body = <Chat conversations={props.conversations} quickActions={props.quickActions} />;
    }

    return (
        <PageTemplate title={t('AI Assistant')} breadcrumbs={breadcrumbs}>
            {body}
        </PageTemplate>
    );
}

/**
 * The model's replies use a little Markdown: **bold**, [links](/tasks/12) and
 * "- " bullets. Rendered as React elements, never as HTML, so a reply cannot
 * inject markup; only relative or http(s) links become anchors.
 */
function FormattedText({ text }: { text: string }) {
    const inline = (line: string, key: string) =>
        line.split(/(\*\*[^*]+\*\*|\[[^\]]+\]\([^)\s]+\))/g).map((part, i) => {
            const bold = part.match(/^\*\*([^*]+)\*\*$/);
            if (bold) return <strong key={`${key}-${i}`}>{bold[1]}</strong>;
            const link = part.match(/^\[([^\]]+)\]\(([^)\s]+)\)$/);
            if (link && /^(\/(?!\/)|https?:\/\/)/.test(link[2])) {
                return <a key={`${key}-${i}`} href={link[2]} className="text-primary underline">{link[1]}</a>;
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

/** Shown as buttons above the chat; the other quick actions are under "More". */
const PRIMARY_ACTIONS = ['create_task', 'create_bug', 'assign_task', 'change_task_status'];

const EXAMPLES = [
    'What tasks are overdue in my projects?',
    'Assign the login bug to Ravi, due Friday.',
    'Which timesheets are waiting for my approval?',
    'Who is over budget this month?',
    'Write the weekly report for my biggest project.',
    'Create a project called Mobile App.',
];

function Chat({ conversations: initial, quickActions }: { conversations: Conversation[]; quickActions: QuickAction[] }) {
    const { t } = useTranslation();
    const [conversations, setConversations] = useState<Conversation[]>(initial);
    const [activeId, setActiveId] = useState<number | null>(initial[0]?.id ?? null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const [loading, setLoading] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);
    // Set when send() creates a conversation: its messages are already on screen
    // (including any error bubble), so don't reload and overwrite them.
    const createdHereRef = useRef<number | null>(null);

    useEffect(() => {
        if (activeId === null) {
            setMessages([]);
            return;
        }
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
            setMessages(prev => [...prev.filter(m => m.id !== optimistic.id), ...data.messages]);
            if (activeId === null) createdHereRef.current = data.conversation.id;
            setActiveId(current => current ?? data.conversation.id);
            setConversations(prev => [data.conversation, ...prev.filter(c => c.id !== data.conversation.id)]);
        };

        try {
            const { data } = await axios.post(route('ai-assistant.send'), { conversation_id: activeId, content });
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

    // The most-used forms as buttons; the rest under "More".
    const primaryActions = PRIMARY_ACTIONS.map(tool => quickActions.find(a => a.tool === tool)).filter((a): a is QuickAction => !!a);
    const moreActions = quickActions.filter(a => !PRIMARY_ACTIONS.includes(a.tool));

    /** Quick action: open a form card straight away, with no AI call. */
    const openForm = async (tool: string) => {
        if (sending) return;
        setSending(true);
        try {
            const { data } = await axios.post(route('ai-assistant.forms.start'), { tool, conversation_id: activeId });
            setMessages(prev => [...prev, ...data.messages]);
            if (activeId === null) createdHereRef.current = data.conversation.id;
            setActiveId(current => current ?? data.conversation.id);
            setConversations(prev => [data.conversation, ...prev.filter(c => c.id !== data.conversation.id)]);
        } catch (error) {
            toast.error(errorMessage(error, t('Could not open the form.')));
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
                setMessages(prev => [...prev.filter(m => m.id <= afterId), ...fresh]);
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

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            send(input);
        }
    };

    return (
        <div className="grid min-h-[600px] gap-4 md:grid-cols-[260px_1fr]">
            <Card className="flex flex-col overflow-hidden">
                <div className="border-b p-3">
                    <Button className="w-full" variant="outline" size="sm" onClick={() => setActiveId(null)}>
                        <MessageSquarePlus className="mr-2 h-4 w-4" />
                        {t('New chat')}
                    </Button>
                </div>
                <div className="flex-1 space-y-1 overflow-y-auto p-2">
                    {conversations.length === 0 && <p className="p-2 text-sm text-muted-foreground">{t('No conversations yet.')}</p>}
                    {conversations.map(c => (
                        <div
                            key={c.id}
                            className={`group flex cursor-pointer items-center justify-between rounded-md px-2 py-2 text-sm hover:bg-muted ${c.id === activeId ? 'bg-muted font-medium' : ''}`}
                            onClick={() => setActiveId(c.id)}
                        >
                            <span className="truncate">{c.title || t('New conversation')}</span>
                            <button
                                type="button"
                                className="ml-2 opacity-0 group-hover:opacity-100"
                                onClick={e => { e.stopPropagation(); remove(c); }}
                                aria-label={t('Delete')}
                            >
                                <Trash2 className="h-3.5 w-3.5 text-muted-foreground" />
                            </button>
                        </div>
                    ))}
                </div>
            </Card>

            <Card className="flex flex-col overflow-hidden">
                <div className="flex-1 space-y-4 overflow-y-auto p-4" style={{ maxHeight: '65vh' }}>
                    {loading && <Loader2 className="mx-auto h-5 w-5 animate-spin text-muted-foreground" />}
                    {!loading && messages.length === 0 && (
                        <div className="flex flex-col items-center gap-3 py-12 text-center">
                            <Sparkles className="h-10 w-10 text-violet-500" />
                            <p className="text-sm text-muted-foreground">
                                {t('Ask about your projects, tasks and bugs, or ask me to assign and update work. I always show a card for you to confirm before changing anything.')}
                            </p>
                            <div className="flex flex-wrap justify-center gap-2">
                                {EXAMPLES.map(example => (
                                    <Button key={example} variant="outline" size="sm" onClick={() => send(t(example))}>{t(example)}</Button>
                                ))}
                            </div>
                        </div>
                    )}
                    {messages.map(message => (
                        <div key={message.id} className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                            <div className="max-w-[80%] space-y-2">
                                <div className={`whitespace-pre-wrap rounded-lg px-3 py-2 text-sm ${
                                    message.error
                                        ? 'border border-destructive/40 bg-destructive/10 text-destructive'
                                        : message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted'
                                }`}>
                                    {message.role === 'assistant' && !message.error ? <FormattedText text={message.content} /> : message.content}
                                </div>
                                {message.cards.map(card => <ConfirmCard key={card.id} card={card} onChange={onCardChange} />)}
                            </div>
                        </div>
                    ))}
                    {sending && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 className="h-4 w-4 animate-spin" /> {t('Thinking…')}
                        </div>
                    )}
                    <div ref={bottomRef} />
                </div>
                {quickActions.length > 0 && (
                    <div className="flex flex-wrap items-center gap-1.5 border-t px-3 pt-3" aria-label={t('Quick actions')}>
                        {primaryActions.map(action => (
                            <Button
                                key={action.tool}
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 px-2 text-xs"
                                disabled={sending}
                                onClick={() => openForm(action.tool)}
                            >
                                <Plus className="mr-1 h-3 w-3" />
                                {action.label}
                            </Button>
                        ))}
                        {moreActions.length > 0 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button type="button" variant="ghost" size="sm" className="h-7 px-2 text-xs" disabled={sending}>
                                        {t('More')} <ChevronDown className="ml-1 h-3 w-3" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    {moreActions.map(action => (
                                        <DropdownMenuItem key={action.tool} onSelect={() => openForm(action.tool)}>
                                            {action.label}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                )}
                <form
                    className={`flex items-end gap-2 p-3 ${quickActions.length > 0 ? '' : 'border-t'}`}
                    onSubmit={(e: FormEvent) => { e.preventDefault(); send(input); }}
                >
                    <Textarea
                        value={input}
                        onChange={e => setInput(e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder={t('Ask the AI Assistant…')}
                        rows={2}
                        maxLength={4000}
                        className="resize-none"
                    />
                    <Button type="submit" disabled={sending || !input.trim()} aria-label={t('Send')}>
                        <Send className="h-4 w-4" />
                    </Button>
                </form>
            </Card>
        </div>
    );
}

function ConfirmCard({ card, onChange }: { card: ToolCard; onChange: (card: ToolCard, note: Message | null) => void }) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState<'confirm' | 'cancel' | 'undo' | null>(null);
    const [phrase, setPhrase] = useState('');
    // Hide the Undo button once its 10 minutes are over, without a reload.
    const [undoOpen, setUndoOpen] = useState(card.can_undo);
    useEffect(() => {
        setUndoOpen(card.can_undo);
        if (!card.can_undo || !card.undo_until) return;
        const ms = new Date(card.undo_until).getTime() - Date.now();
        const timer = setTimeout(() => setUndoOpen(false), Math.max(0, ms));
        return () => clearTimeout(timer);
    }, [card.can_undo, card.undo_until]);

    // Form cards: the user's picks, reset whenever the server sends the card back.
    const fields = card.fields ?? [];
    const isForm = card.status === 'pending' && fields.length > 0;
    const [values, setValues] = useState<Record<string, string | string[]>>(() => formValues(fields));
    useEffect(() => setValues(formValues(fields)), [card.fields]);
    const missing = isForm && fields.some(f => f.required && isEmpty(values[f.name]));

    const phraseOk = !card.confirm_phrase || phrase.trim().toLowerCase() === card.confirm_phrase.toLowerCase();

    const act = async (action: 'confirm' | 'cancel' | 'undo') => {
        setBusy(action);
        try {
            const body = action === 'confirm'
                ? { ...(card.confirm_phrase ? { phrase } : {}), ...(isForm ? { fields: values } : {}) }
                : {};
            const { data } = await axios.post(route(`ai-assistant.tool-calls.${action}`, card.id), body);
            onChange(data.card, data.message);
            if (data.card.status === 'failed') toast.error(data.card.error || t('The action failed.'));
        } catch (error) {
            toast.error(errorMessage(error, t('Could not update this card.')));
        } finally {
            setBusy(null);
        }
    };

    const statusLabel: Record<CardStatus, string> = {
        pending: t('Waiting for you'),
        done: t('Done'),
        failed: t('Failed'),
        cancelled: t('Cancelled'),
        expired: t('Expired'),
        undone: t('Undone'),
    };

    return (
        <Card className={card.status === 'pending' ? 'border-violet-300 dark:border-violet-700' : ''}>
            <CardContent className="space-y-3 p-3">
                <div className="flex items-start justify-between gap-2">
                    <p className="text-sm font-medium">{card.summary}</p>
                    <Badge variant={card.status === 'done' ? 'default' : card.status === 'failed' ? 'destructive' : 'secondary'}>
                        {statusLabel[card.status]}
                    </Badge>
                </div>
                {isForm ? (
                    <CardForm cardId={card.id} fields={fields} values={values} onChange={(name, value) => setValues(prev => ({ ...prev, [name]: value }))} />
                ) : (
                    <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                        {Object.entries(card.details).map(([label, value]) => (
                            <div key={label} className="contents">
                                <dt className="text-muted-foreground">{label}</dt>
                                <dd>{value}</dd>
                            </div>
                        ))}
                    </dl>
                )}
                {card.status === 'pending' && card.form_error && <p className="text-xs text-destructive">{card.form_error}</p>}
                {card.items.length > 0 && (
                    <ul className="max-h-48 list-disc space-y-0.5 overflow-y-auto rounded-md bg-muted/50 py-2 pl-6 pr-2 text-xs">
                        {card.items.map((item, i) => <li key={i}>{item}</li>)}
                    </ul>
                )}
                {card.error && <p className="text-xs text-destructive">{card.error}</p>}
                {card.status === 'pending' && card.confirm_phrase && (
                    <div className="space-y-1">
                        <Label htmlFor={`phrase-${card.id}`} className="text-xs">
                            {t('This cannot be undone. Type {{phrase}} to confirm.', { phrase: card.confirm_phrase })}
                        </Label>
                        <Input id={`phrase-${card.id}`} value={phrase} onChange={e => setPhrase(e.target.value)} className="h-8 text-xs" autoComplete="off" />
                    </div>
                )}
                {card.status === 'pending' && (
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            onClick={() => act('confirm')}
                            disabled={busy !== null || !phraseOk || missing}
                            title={missing ? t('Fill in the required fields first.') : undefined}
                        >
                            {busy === 'confirm' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Check className="mr-1 h-3 w-3" />}
                            {t('Confirm')}
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => act('cancel')} disabled={busy !== null}>
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
                            <Button size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={() => act('undo')} disabled={busy !== null}>
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

                        {(field.type === 'date' || field.type === 'text') && (
                            <Input
                                id={id}
                                type={field.type === 'date' ? 'date' : 'text'}
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

function SettingsForm({ settings, providers, usage, retentionOptions, configured }: Props) {
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
