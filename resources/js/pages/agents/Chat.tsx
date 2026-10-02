import { useState, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Send, Bot, User, ArrowLeft, Trash2, Settings } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { hasPermission } from '@/utils/authorization';

interface Msg { role: 'user'|'bot'; text: string; }

export default function AgentChat() {
    const { t } = useTranslation();
    const { agent, hasKey, settingsUrl, auth } = usePage().props as any;
    const canConfigureKey = hasPermission(auth?.permissions || [], 'settings_chatgpt');

    const greeting: Msg = {
        role: 'bot',
        text: agent.greeting_message || `Hi! I'm ${agent.name}. How can I help you today?`,
    };
    const [messages, setMessages] = useState<Msg[]>([greeting]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const endRef = useRef<HTMLDivElement>(null);

    useEffect(() => { endRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages]);

    const csrfToken = () =>
        (window as any).page?.props?.csrf_token ||
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

    const send = async () => {
        const msg = input.trim();
        if (!msg || loading) return;
        setInput('');
        setMessages(p => [...p, { role: 'user', text: msg }]);
        setLoading(true);
        try {
            const res = await fetch(route('agents.ask', agent.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ message: msg }),
            });
            const data = await res.json();
            setMessages(p => [...p, { role: 'bot', text: data.answer || data.error || 'No response.' }]);
        } catch {
            setMessages(p => [...p, { role: 'bot', text: 'Something went wrong. Please try again.' }]);
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={[
            { title: t('Agents'), href: route('agents.index') },
            { title: agent.name, href: route('agents.chat', agent.id) },
        ]}>
            <Head title={agent.name} />
            <div className="flex h-[calc(100vh-4rem)] flex-col">

                {/* Header */}
                <div className="flex items-center gap-3 border-b px-6 py-3">
                    <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0"
                        onClick={() => window.history.back()}>
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <Bot className="h-4 w-4 text-primary" />
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="font-semibold truncate">{agent.name}</p>
                        {agent.description && <p className="text-xs text-muted-foreground truncate">{agent.description}</p>}
                    </div>
                    <Button size="sm" variant="ghost" onClick={() => setMessages([greeting])}>
                        <Trash2 className="mr-1.5 h-4 w-4" /> {t('Clear')}
                    </Button>
                </div>

                {/* No API key */}
                {!hasKey ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
                        <Bot className="h-12 w-12 text-muted-foreground" />
                        <div>
                            <h2 className="text-lg font-semibold">{t('ChatGPT not configured')}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {canConfigureKey
                                    ? t('Add your OpenAI API key in Settings to enable agents.')
                                    : t('Please contact your admin or manager to configure the OpenAI API key.')}
                            </p>
                        </div>
                        {canConfigureKey && (
                            <Button onClick={() => router.get(settingsUrl + '#chatgpt-settings')}>
                                <Settings className="mr-2 h-4 w-4" /> {t('Configure API Key')}
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        {/* Messages */}
                        <div className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                            {messages.map((m, i) => (
                                <div key={i} className={cn('flex gap-3', m.role === 'user' && 'flex-row-reverse')}>
                                    <div className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                        m.role === 'bot' ? 'bg-primary/10' : 'bg-muted')}>
                                        {m.role === 'bot' ? <Bot className="h-4 w-4 text-primary" /> : <User className="h-4 w-4" />}
                                    </div>
                                    <div className={cn('max-w-[70%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap leading-relaxed',
                                        m.role === 'bot' ? 'rounded-tl-sm bg-muted' : 'rounded-tr-sm bg-primary text-primary-foreground')}>
                                        {m.text}
                                    </div>
                                </div>
                            ))}
                            {loading && (
                                <div className="flex gap-3">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10">
                                        <Bot className="h-4 w-4 text-primary" />
                                    </div>
                                    <div className="rounded-2xl rounded-tl-sm bg-muted px-4 py-3 flex gap-1 items-center">
                                        <span className="h-2 w-2 rounded-full bg-muted-foreground/50 animate-bounce [animation-delay:-0.3s]" />
                                        <span className="h-2 w-2 rounded-full bg-muted-foreground/50 animate-bounce [animation-delay:-0.15s]" />
                                        <span className="h-2 w-2 rounded-full bg-muted-foreground/50 animate-bounce" />
                                    </div>
                                </div>
                            )}
                            <div ref={endRef} />
                        </div>

                        {/* Input */}
                        <div className="border-t px-6 py-4">
                            <div className="flex gap-2">
                                <Input value={input} onChange={e => setInput(e.target.value)}
                                    onKeyDown={e => e.key === 'Enter' && !e.shiftKey && send()}
                                    placeholder={`${t('Message')} ${agent.name}…`} className="flex-1" />
                                <Button onClick={send} disabled={!input.trim() || loading} size="icon">
                                    <Send className="h-4 w-4" />
                                </Button>
                            </div>
                            <p className="mt-1 text-[11px] text-muted-foreground">
                                {t('Press Enter to send')}
                                {agent.kb_category_ids?.length > 0 && ` · ${t('Using')} ${agent.kb_category_ids.length} KB ${t('categories as context')}`}
                            </p>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
