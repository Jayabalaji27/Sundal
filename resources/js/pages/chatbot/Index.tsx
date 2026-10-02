import { useState, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Send, Bot, User, Loader2, Settings, Trash2, BookOpen } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

interface Message { role: 'user'|'bot'; text: string; }

const INITIAL: Message[] = [
    { role: 'bot', text: 'Hi! I\'m your workspace assistant. Ask me anything — I also search your Knowledge Base for relevant answers.' },
];

export default function ChatbotPage() {
    const { t } = useTranslation();
    const { hasChatgptKey, settingsUrl } = usePage().props as any;
    const [messages, setMessages] = useState<Message[]>(INITIAL);
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
            const res = await fetch(route('chatbot.ask'), {
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
        <AppLayout breadcrumbs={[{ title: 'Chatbot', href: route('chatbot.index') }]}>
            <Head title={t('Chatbot')} />
            <div className="flex h-[calc(100vh-4rem)] flex-col">

                {/* Header */}
                <div className="border-b px-4 py-4 sm:px-6 flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-3 min-w-0">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <Bot className="h-5 w-5 text-primary" />
                        </div>
                        <div className="min-w-0">
                            <p className="font-semibold truncate">{t('Workspace Assistant')}</p>
                            <p className="text-xs text-muted-foreground truncate">{t('Searches your Knowledge Base automatically')}</p>
                        </div>
                    </div>
                    <div className="flex gap-2 shrink-0">
                        <Button size="sm" variant="ghost" title={t('Knowledge Base')}
                            onClick={() => router.get(route('kb.index'))}>
                            <BookOpen className="h-4 w-4 sm:mr-1.5" /> <span className="hidden sm:inline">{t('Knowledge Base')}</span>
                        </Button>
                        <Button size="sm" variant="ghost" title={t('Clear conversation')}
                            onClick={() => setMessages(INITIAL)}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                {/* No API key state */}
                {!hasChatgptKey ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                            <Bot className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold">{t('ChatGPT not configured')}</h2>
                            <p className="mt-1 text-sm text-muted-foreground max-w-sm">
                                {t('Add your OpenAI API key in Settings to enable the AI assistant.')}
                            </p>
                        </div>
                        <Button onClick={() => router.get(settingsUrl + '#chatgpt-settings')}>
                            <Settings className="mr-2 h-4 w-4" /> {t('Go to Settings → ChatGPT')}
                        </Button>
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
                                    <div className={cn('max-w-[70%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap',
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
                                    <div className="rounded-2xl rounded-tl-sm bg-muted px-4 py-3 flex items-center gap-2">
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
                                    placeholder={t('Ask anything about your workspace…')} className="flex-1" />
                                <Button onClick={send} disabled={!input.trim() || loading} size="icon">
                                    <Send className="h-4 w-4" />
                                </Button>
                            </div>
                            <p className="mt-1 text-[11px] text-muted-foreground">
                                {t('Press Enter to send · Responses use your published Knowledge Base articles as context')}
                            </p>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

