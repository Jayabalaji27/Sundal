import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Bot, Check, ExternalLink, Loader2, MessageSquarePlus, Send, Sparkles, Trash2, X } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/components/custom-toast';

declare const route: any;

type CardStatus = 'pending' | 'done' | 'failed' | 'cancelled' | 'expired';

interface ToolCard {
    id: number;
    tool: string;
    summary: string;
    status: CardStatus;
    details: Record<string, string>;
    link: string | null;
    error: string | null;
}

interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
    cards: ToolCard[];
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
    usage: { tokens_this_month: number } | null;
    providers: Record<string, ProviderOption> | null;
    retentionOptions: number[];
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
    if (props.access === 'managers_off') {
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
                <TabsContent value="assistant" className="mt-4">{props.configured && <Chat conversations={props.conversations} />}</TabsContent>
                <TabsContent value="settings" className="mt-4"><SettingsForm {...props} /></TabsContent>
            </Tabs>
        );
    } else {
        body = <Chat conversations={props.conversations} />;
    }

    return (
        <PageTemplate title={t('AI Assistant')} breadcrumbs={breadcrumbs}>
            {body}
        </PageTemplate>
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

const EXAMPLES = [
    'What tasks are overdue in my projects?',
    'Show the open bugs assigned to me.',
    'Assign the login bug to Ravi, due Friday.',
    "Move 'API docs' to Done.",
];

function Chat({ conversations: initial }: { conversations: Conversation[] }) {
    const { t } = useTranslation();
    const [conversations, setConversations] = useState<Conversation[]>(initial);
    const [activeId, setActiveId] = useState<number | null>(initial[0]?.id ?? null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const [loading, setLoading] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (activeId === null) {
            setMessages([]);
            return;
        }
        setLoading(true);
        axios.get(route('ai-assistant.conversations.show', activeId))
            .then(({ data }) => setMessages(data.messages))
            .catch(() => toast.error(t('Could not load this conversation.')))
            .finally(() => setLoading(false));
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
            setActiveId(current => current ?? data.conversation.id);
            setConversations(prev => [data.conversation, ...prev.filter(c => c.id !== data.conversation.id)]);
        };

        try {
            const { data } = await axios.post(route('ai-assistant.send'), { conversation_id: activeId, content });
            apply(data);
        } catch (error: any) {
            apply(error?.response?.data);
            toast.error(errorMessage(error, t('The assistant could not answer. Please try again.')));
        } finally {
            setSending(false);
        }
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
                                <div className={`whitespace-pre-wrap rounded-lg px-3 py-2 text-sm ${message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted'}`}>
                                    {message.content}
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
                <form
                    className="flex items-end gap-2 border-t p-3"
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
    const [busy, setBusy] = useState<'confirm' | 'cancel' | null>(null);

    const act = async (action: 'confirm' | 'cancel') => {
        setBusy(action);
        try {
            const { data } = await axios.post(route(`ai-assistant.tool-calls.${action}`, card.id));
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
                <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                    {Object.entries(card.details).map(([label, value]) => (
                        <div key={label} className="contents">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd>{value}</dd>
                        </div>
                    ))}
                </dl>
                {card.error && <p className="text-xs text-destructive">{card.error}</p>}
                {card.status === 'pending' && (
                    <div className="flex gap-2">
                        <Button size="sm" onClick={() => act('confirm')} disabled={busy !== null}>
                            {busy === 'confirm' ? <Loader2 className="mr-1 h-3 w-3 animate-spin" /> : <Check className="mr-1 h-3 w-3" />}
                            {t('Confirm')}
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => act('cancel')} disabled={busy !== null}>
                            <X className="mr-1 h-3 w-3" />
                            {t('Cancel')}
                        </Button>
                    </div>
                )}
                {card.status === 'done' && card.link && (
                    <Link href={card.link} className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                        {t('Open record')} <ExternalLink className="h-3 w-3" />
                    </Link>
                )}
            </CardContent>
        </Card>
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
                        <CardContent>
                            <p className="text-2xl font-semibold">{usage.tokens_this_month.toLocaleString()}</p>
                            <p className="text-xs text-muted-foreground">
                                {settings?.monthly_token_cap ? t('of {{cap}} tokens', { cap: settings.monthly_token_cap.toLocaleString() }) : t('tokens, no cap set')}
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </form>
    );
}
