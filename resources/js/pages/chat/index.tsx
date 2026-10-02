import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';import {
    MessageSquare, Plus, Search, Send, Users, FolderOpen, User as UserIcon,
    Hash, Check, CheckCheck, Edit3, X, ArrowLeft, ChevronRight
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { toast } from '@/components/custom-toast';

interface Participant {
    id: number;
    name: string;
    avatar: string | null;
}

interface LastMessage {
    message: string;
    sender_name: string;
    created_at: string;
}

interface Conversation {
    id: number;
    type: 'direct' | 'group' | 'project';
    name: string;
    participants: Participant[];
    last_message: LastMessage | null;
    unread_count: number;
}

interface Message {
    id: number;
    message: string;
    user_id: number;
    sender: Participant;
    created_at: string;
    is_mine: boolean;
}

interface WorkspaceUser {
    id: number;
    name: string;
    avatar: string | null;
}

interface Project {
    id: number;
    title: string;
}

interface Props {
    conversations: Conversation[];
    workspaceUsers: WorkspaceUser[];
    projects: Project[];
}

function getInitials(name: string) {
    return name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
}

// Hardcoded instead of Intl's `weekday: 'short'` — with no locale pinned, that
// format is resolved against the OS/browser's default locale (not the app's
// selected language), and some locales resolve 'short' down to a single
// narrow-width letter (e.g. "M") instead of the expected "Mon".
const WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function formatTime(dateStr: string) {
    const d = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - d.getTime()) / 86400000);
    if (diffDays === 0) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return WEEKDAYS_SHORT[d.getDay()];
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function formatDateDivider(dateStr: string) {
    const d = new Date(dateStr);
    const now = new Date();
    const diffDays = Math.floor((now.getTime() - d.getTime()) / 86400000);
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return d.toLocaleDateString([], { weekday: 'long' });
    return d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });
}

function isSameDay(a: string, b: string) {
    const da = new Date(a), db = new Date(b);
    return da.getFullYear() === db.getFullYear() && da.getMonth() === db.getMonth() && da.getDate() === db.getDate();
}

// Soft pastel color per conversation name
function avatarColor(name: string) {
    const colors = [
        'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
        'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
        'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
        'bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400',
        'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400',
        'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400',
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
}

export default function ChatIndex({ conversations: initialConversations, workspaceUsers, projects }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const currentUser = auth?.user;

    const [conversations, setConversations] = useState<Conversation[]>(initialConversations);
    const [activeConversation, setActiveConversation] = useState<Conversation | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [participantsRead, setParticipantsRead] = useState<Record<string, string | null>>({});
    const [messageInput, setMessageInput] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [sending, setSending] = useState(false);
    const [newChatOpen, setNewChatOpen] = useState(false);
    const [mobileShowChat, setMobileShowChat] = useState(false);

    const [newName, setNewName] = useState('');
    const [selectedParticipants, setSelectedParticipants] = useState<number[]>([]);
    const [participantSearch, setParticipantSearch] = useState('');
    const [selectedProject, setSelectedProject] = useState<string>('');
    const [newChatStep, setNewChatStep] = useState<'people' | 'project'>('people');
    const composeSearchRef = useRef<HTMLInputElement>(null);

    const messagesEndRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // Sync conversations
    useEffect(() => {
        setConversations((prev) => {
            const merged = initialConversations.map((ic) => {
                const local = prev.find((p) => p.id === ic.id);
                return local ? { ...ic, unread_count: local.unread_count } : ic;
            });
            return merged;
        });
        const params = new URLSearchParams(window.location.search);
        const convId = params.get('conversation');
        if (convId) {
            const found = initialConversations.find((c) => c.id === parseInt(convId));
            if (found) openConversation(found);
        }
    }, [initialConversations]);

    // Tracks which conversation is currently open so a slow/delayed response for a
    // conversation the user has since navigated away from can't overwrite the thread.
    const activeConversationIdRef = useRef<number | null>(null);
    const [messagesError, setMessagesError] = useState(false);

    const fetchMessages = useCallback(async (conversationId: number) => {
        try {
            const res = await fetch(route('chat.messages', conversationId), {
                headers: { Accept: 'application/json' },
            });
            if (activeConversationIdRef.current !== conversationId) return; // stale response, ignore
            if (!res.ok) { setMessagesError(true); return; }
            const data = await res.json();
            if (activeConversationIdRef.current !== conversationId) return;
            setMessagesError(false);
            if (Array.isArray(data)) {
                setMessages(data);
            } else {
                setMessages(data.messages ?? []);
                setParticipantsRead(data.participants_read ?? {});
            }
        } catch {
            if (activeConversationIdRef.current === conversationId) setMessagesError(true);
        }
    }, []);

    const openConversation = useCallback((conv: Conversation) => {
        activeConversationIdRef.current = conv.id;
        setActiveConversation(conv);
        setMessages([]);
        setMessagesError(false);
        setParticipantsRead({});
        fetchMessages(conv.id);
        if (pollingRef.current) clearInterval(pollingRef.current);
        pollingRef.current = setInterval(() => fetchMessages(conv.id), 3000);
        setConversations((prev) =>
            prev.map((c) => (c.id === conv.id ? { ...c, unread_count: 0 } : c))
        );
        setMobileShowChat(true);
        setNewChatOpen(false); // close compose panel when opening a conversation
    }, [fetchMessages]);

    // Keep the sidebar's unread counts / last-message previews in sync even when the
    // user isn't actively polling a specific conversation's messages.
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['conversations'], preserveScroll: true, preserveState: true });
        }, 15000);
        return () => clearInterval(interval);
    }, []);

    // Focus compose search when panel opens
    useEffect(() => {
        if (newChatOpen) setTimeout(() => composeSearchRef.current?.focus(), 50);
    }, [newChatOpen]);

    useEffect(() => () => { if (pollingRef.current) clearInterval(pollingRef.current); }, []);
    // block: 'nearest' keeps this scroll inside the messages ScrollArea. Without it,
    // scrollIntoView() also nudges the outer document (~16px) to bring the anchor into
    // view there too — and since the sidebar is position:fixed while the header/chat
    // panel scroll with the document, that whole-page scroll knocks them out of sync
    // with the sidebar (looked like a sidebar/main-content alignment bug).
    useEffect(() => { messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, [messages]);

    // Auto-resize textarea
    useEffect(() => {
        const ta = textareaRef.current;
        if (!ta) return;
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
    }, [messageInput]);

    const sendMessage = async () => {
        if (!messageInput.trim() || !activeConversation || sending) return;
        setSending(true);
        const content = messageInput.trim();
        setMessageInput('');
        try {
            const csrfToken =
                (window as any).page?.props?.csrf_token ||
                (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

            const res = await fetch(route('chat.send', activeConversation.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message: content }),
            });
            if (!res.ok) { toast.error('Failed to send message'); setMessageInput(content); return; }
            const msg: Message = await res.json();
            setMessages((prev) => [...prev, msg]);
            setConversations((prev) =>
                prev.map((c) =>
                    c.id === activeConversation.id
                        ? { ...c, last_message: { message: msg.message, sender_name: currentUser?.name ?? '', created_at: msg.created_at } }
                        : c
                )
            );
        } finally { setSending(false); }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    };

    const createConversation = () => {
        if (selectedParticipants.length === 0 && !selectedProject) { toast.error('Select at least one person'); return; }
        // Determine type automatically: direct if 1 person, group if multiple, project if project chosen
        const type = selectedProject ? 'project' : selectedParticipants.length === 1 ? 'direct' : 'group';
        router.post(route('chat.store'), {
            type,
            name: type === 'group' ? (newName || undefined) : undefined,
            participant_ids: selectedParticipants,
            project_id: selectedProject ? parseInt(selectedProject) : undefined,
        }, {
            onSuccess: () => closeCompose(),
            onError: () => toast.error('Failed to create conversation'),
        });
    };

    const closeCompose = () => {
        setNewChatOpen(false); setNewName(''); setSelectedParticipants([]);
        setSelectedProject(''); setParticipantSearch(''); setNewChatStep('people');
    };

    const toggleParticipant = (id: number) => {
        setSelectedParticipants((prev) =>
            prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id]
        );
    };

    // Derived: what type will be created
    const derivedType = selectedProject ? 'project' : selectedParticipants.length === 1 ? 'direct' : 'group';

    const filtered = conversations.filter((c) =>
        c.name.toLowerCase().includes(searchTerm.toLowerCase())
    );

    const filteredUsers = workspaceUsers.filter((u) =>
        u.name.toLowerCase().includes(participantSearch.toLowerCase())
    );

    const filteredProjects = projects.filter((p) =>
        p.title.toLowerCase().includes(participantSearch.toLowerCase())
    );

    const otherParticipant = (conv: Conversation) =>
        conv.participants.find((p) => p.id !== currentUser?.id);

    // Group consecutive messages by sender for visual grouping
    const groupedMessages = messages.reduce<Array<{ messages: Message[]; showDate: boolean }>>((acc, msg, i) => {
        const prev = messages[i - 1];
        const needsDate = !prev || !isSameDay(prev.created_at, msg.created_at);
        const newGroup = needsDate || !prev || prev.user_id !== msg.user_id || msg.is_mine !== prev.is_mine;
        if (newGroup) {
            acc.push({ messages: [msg], showDate: needsDate });
        } else {
            acc[acc.length - 1].messages.push(msg);
        }
        return acc;
    }, []);

    const totalUnread = conversations.reduce((sum, c) => sum + c.unread_count, 0);

    // CTA label for compose button
    const composeCTA = () => {
        if (selectedProject) {
            const p = projects.find((x) => String(x.id) === selectedProject);
            return `Open Project Chat${p ? ` · ${p.title}` : ''}`;
        }
        if (selectedParticipants.length === 0) return null;
        if (selectedParticipants.length === 1) {
            const u = workspaceUsers.find((x) => x.id === selectedParticipants[0]);
            return `Message ${u?.name ?? ''}`;   
        }
        return `Create Group · ${selectedParticipants.length} people`;
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('Chat'), href: route('chat.index') }]}>
            <Head title={`${totalUnread > 0 ? `(${totalUnread}) ` : ''}${t('Chat')}`} />
            <div className="flex h-[calc(100vh-3.5rem)] overflow-hidden bg-background">

                {/* ── Sidebar ── */}
                <div className={cn(
                    'flex flex-col border-r bg-background transition-all duration-200',
                    'w-full sm:w-96 flex-shrink-0',
                    mobileShowChat && 'hidden sm:flex'
                )}>
                    {/* ─ Compose Panel (replaces conversation list) ─ */}
                    {newChatOpen ? (
                        <>
                            {/* Compose header */}
                            <div className="flex items-center gap-2 border-b px-3 py-3">
                                <Button variant="ghost" size="icon" className="h-8 w-8 rounded-full flex-shrink-0" onClick={closeCompose}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Button>
                                <h2 className="font-semibold text-base">{t('New Message')}</h2>
                            </div>

                            {/* Selected people chips */}
                            {(selectedParticipants.length > 0 || selectedProject) && (
                                <div className="flex flex-wrap gap-1.5 border-b px-3 py-2.5">
                                    {selectedParticipants.map((id) => {
                                        const u = workspaceUsers.find((x) => x.id === id);
                                        if (!u) return null;
                                        return (
                                            <button
                                                key={id}
                                                onClick={() => toggleParticipant(id)}
                                                className="flex items-center gap-1 rounded-full bg-primary/10 text-primary pl-1.5 pr-1 py-0.5 text-xs font-medium hover:bg-primary/20 transition-colors"
                                            >
                                                <Avatar className="h-4 w-4">
                                                    <AvatarImage src={u.avatar ?? undefined} />
                                                    <AvatarFallback className="text-[8px]">{getInitials(u.name)}</AvatarFallback>
                                                </Avatar>
                                                {u.name}
                                                <X className="h-2.5 w-2.5 ml-0.5" />
                                            </button>
                                        );
                                    })}
                                    {selectedProject && (() => {
                                        const p = projects.find((x) => String(x.id) === selectedProject);
                                        return p ? (
                                            <button
                                                onClick={() => setSelectedProject('')}
                                                className="flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 pl-1.5 pr-1 py-0.5 text-xs font-medium hover:bg-emerald-200 transition-colors"
                                            >
                                                <FolderOpen className="h-3 w-3" />
                                                {p.title}
                                                <X className="h-2.5 w-2.5 ml-0.5" />
                                            </button>
                                        ) : null;
                                    })()}
                                </div>
                            )}

                            {/* Group name — only when 2+ people selected */}
                            {selectedParticipants.length >= 2 && (
                                <div className="border-b px-3 py-2">
                                    <input
                                        type="text"
                                        value={newName}
                                        onChange={(e) => setNewName(e.target.value)}
                                        placeholder={t('Group name (optional)…')}
                                        className="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                                    />
                                </div>
                            )}

                            {/* Search */}
                            <div className="px-3 py-2 border-b">
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                    <input
                                        ref={composeSearchRef}
                                        type="text"
                                        value={participantSearch}
                                        onChange={(e) => setParticipantSearch(e.target.value)}
                                        placeholder={t('Search people or projects…')}
                                        className="w-full rounded-lg bg-muted/60 pl-8 pr-3 py-1.5 text-sm outline-none placeholder:text-muted-foreground focus:ring-1 focus:ring-primary/30"
                                    />
                                </div>
                            </div>

                            {/* People list */}
                            <ScrollArea className="flex-1">
                                <div className="w-full min-w-0 py-1">
                                    {/* People section */}
                                    {filteredUsers.length > 0 && (
                                        <>
                                            <p className="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                {t('People')}
                                            </p>
                                            {filteredUsers.map((u) => {
                                                const selected = selectedParticipants.includes(u.id);
                                                return (
                                                    <button
                                                        key={u.id}
                                                        onClick={() => toggleParticipant(u.id)}
                                                        className={cn(
                                                            'flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                                            selected ? 'bg-primary/5' : 'hover:bg-muted/50'
                                                        )}
                                                    >
                                                        <div className="relative flex-shrink-0">
                                                            <Avatar className="h-9 w-9">
                                                                <AvatarImage src={u.avatar ?? undefined} />
                                                                <AvatarFallback className="text-xs font-semibold">{getInitials(u.name)}</AvatarFallback>
                                                            </Avatar>
                                                            {selected && (
                                                                <span className="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                                                    <Check className="h-2.5 w-2.5" />
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span className={cn('min-w-0 flex-1 truncate text-sm font-medium', selected && 'text-primary')}>
                                                            {u.name}
                                                        </span>
                                                        {/* Quick-message: click name to start instantly if only this user */}
                                                        {!selected && selectedParticipants.length === 0 && !selectedProject && (
                                                            <ChevronRight className="ml-auto h-3.5 w-3.5 flex-shrink-0 text-muted-foreground/50" />
                                                        )}
                                                    </button>
                                                );
                                            })}
                                        </>
                                    )}

                                    {/* Projects section */}
                                    {filteredProjects.length > 0 && (
                                        <>
                                            <p className="px-3 pt-3 pb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                {t('Project Channels')}
                                            </p>
                                            {filteredProjects.map((p) => {
                                                const isSelected = selectedProject === String(p.id);
                                                return (
                                                    <button
                                                        key={p.id}
                                                        onClick={() => setSelectedProject(isSelected ? '' : String(p.id))}
                                                        className={cn(
                                                            'flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                                            isSelected ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'hover:bg-muted/50'
                                                        )}
                                                    >
                                                        <div className="relative flex-shrink-0">
                                                            <div className={cn(
                                                                'flex h-9 w-9 items-center justify-center rounded-full text-sm',
                                                                isSelected
                                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400'
                                                                    : 'bg-muted text-muted-foreground'
                                                            )}>
                                                                <FolderOpen className="h-4 w-4" />
                                                            </div>
                                                            {isSelected && (
                                                                <span className="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 text-white">
                                                                    <Check className="h-2.5 w-2.5" />
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span className={cn('min-w-0 flex-1 truncate text-sm font-medium', isSelected && 'text-emerald-700 dark:text-emerald-400')}>
                                                            {p.title}
                                                        </span>
                                                    </button>
                                                );
                                            })}
                                        </>
                                    )}

                                    {filteredUsers.length === 0 && filteredProjects.length === 0 && (
                                        <div className="px-3 py-8 text-center text-sm text-muted-foreground">
                                            {t('No results for')} &ldquo;{participantSearch}&rdquo;
                                        </div>
                                    )}
                                </div>
                            </ScrollArea>

                            {/* Sticky CTA */}
                            {composeCTA() && (
                                <div className="border-t px-3 py-3">
                                    <Button className="w-full rounded-full" onClick={createConversation}>
                                        {composeCTA()}
                                    </Button>
                                </div>
                            )}
                        </>
                    ) : (
                        <>
                            {/* Normal sidebar header */}
                            <div className="flex items-center justify-between px-4 pt-5 pb-3">
                                <div className="flex items-center gap-2">
                                    <h2 className="text-xl font-bold tracking-tight">{t('Messages')}</h2>
                                    {totalUnread > 0 && (
                                        <Badge variant="destructive" className="h-5 min-w-[1.25rem] rounded-full px-1.5 text-[10px]">
                                            {totalUnread}
                                        </Badge>
                                    )}
                                </div>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button size="icon" variant="outline" onClick={() => setNewChatOpen(true)} className="h-8 w-8 rounded-full">
                                            <Edit3 className="h-3.5 w-3.5" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{t('New Message')}</TooltipContent>
                                </Tooltip>
                            </div>

                            {/* Search */}
                            <div className="px-4 pb-3">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        className="h-8 rounded-full bg-muted/60 border-none pl-9 text-sm focus-visible:ring-1"
                                        placeholder={t('Search...')}
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                    />
                                </div>
                            </div>

                            {/* Conversation list */}
                            <ScrollArea className="flex-1">
                                {filtered.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 px-4 py-12 text-center">
                                        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                                            <MessageSquare className="h-6 w-6 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">{t('No conversations yet')}</p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">{t('Start chatting with your team')}</p>
                                        </div>
                                        <Button size="sm" onClick={() => setNewChatOpen(true)}>
                                            <Plus className="mr-1.5 h-3.5 w-3.5" />{t('New Chat')}
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="w-full min-w-0 pb-2">
                                        {filtered.map((conv) => {
                                            const other = otherParticipant(conv);
                                            const isActive = activeConversation?.id === conv.id;
                                            const ac = avatarColor(conv.name);
                                            return (
                                                <button key={conv.id} onClick={() => openConversation(conv)}
                                                    className={cn(
                                                        'relative flex w-full items-center gap-3 px-4 py-3 text-left transition-colors',
                                                        isActive ? 'bg-primary/8 dark:bg-primary/10' : 'hover:bg-muted/50'
                                                    )}
                                                >
                                                    {isActive && <span className="absolute left-0 top-3 bottom-3 w-0.5 rounded-r-full bg-primary" />}
                                                    <div className="relative flex-shrink-0">
                                                        {conv.type === 'direct' && other ? (
                                                            <Avatar className="h-11 w-11">
                                                                <AvatarImage src={other.avatar ?? undefined} />
                                                                <AvatarFallback className="text-sm font-semibold">{getInitials(other.name)}</AvatarFallback>
                                                            </Avatar>
                                                        ) : (
                                                            <div className={cn('flex h-11 w-11 items-center justify-center rounded-full text-sm font-semibold', ac)}>
                                                                {conv.type === 'project' ? <FolderOpen className="h-5 w-5" /> : conv.name.slice(0, 2).toUpperCase()}
                                                            </div>
                                                        )}
                                                        {conv.unread_count > 0 && (
                                                            <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground">
                                                                {conv.unread_count > 99 ? '99+' : conv.unread_count}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-baseline justify-between gap-1">
                                                            <span className={cn('min-w-0 flex-1 truncate text-sm', conv.unread_count > 0 ? 'font-semibold' : 'font-medium text-foreground/80')}>
                                                                {conv.name}
                                                            </span>
                                                            {conv.last_message && (
                                                                <span className="flex-shrink-0 text-[10px] text-muted-foreground">
                                                                    {formatTime(conv.last_message.created_at)}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="mt-0.5 flex items-center gap-1">
                                                            {conv.type !== 'direct' && (
                                                                conv.type === 'group' ? <Hash className="h-3 w-3 text-muted-foreground flex-shrink-0" /> : <FolderOpen className="h-3 w-3 text-muted-foreground flex-shrink-0" />
                                                            )}
                                                            {conv.last_message ? (
                                                                <p className={cn('min-w-0 flex-1 truncate text-xs', conv.unread_count > 0 ? 'font-medium text-foreground/70' : 'text-muted-foreground')}>
                                                                    {conv.type !== 'direct' && `${conv.last_message.sender_name}: `}
                                                                    {conv.last_message.message}
                                                                </p>
                                                            ) : (
                                                                <p className="text-xs italic text-muted-foreground">{t('No messages yet')}</p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                            </ScrollArea>
                        </>
                    )}
                </div>

                {/* ── Chat Area ── */}
                <div className={cn('flex flex-1 flex-col min-w-0', !mobileShowChat && 'hidden sm:flex')}>
                    {activeConversation ? (
                        <>
                            {/* Chat header */}
                            <div className="flex items-center justify-between border-b bg-background px-4 py-3 shadow-sm">
                                <div className="flex items-center gap-3">
                                    <Button variant="ghost" size="icon" className="sm:hidden h-8 w-8" onClick={() => setMobileShowChat(false)}>
                                        <ArrowLeft className="h-4 w-4" />
                                    </Button>
                                    {(() => {
                                        const other = otherParticipant(activeConversation);
                                        const ac = avatarColor(activeConversation.name);
                                        return activeConversation.type === 'direct' && other ? (
                                            <Avatar className="h-9 w-9">
                                                <AvatarImage src={other.avatar ?? undefined} />
                                                <AvatarFallback className="text-sm font-semibold">{getInitials(other.name)}</AvatarFallback>
                                            </Avatar>
                                        ) : (
                                            <div className={cn('flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold', ac)}>
                                                {activeConversation.type === 'project' ? <FolderOpen className="h-4 w-4" /> : activeConversation.name.slice(0, 2).toUpperCase()}
                                            </div>
                                        );
                                    })()}
                                    <div>
                                        <p className="font-semibold leading-tight">{activeConversation.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {activeConversation.type === 'direct' ? t('Direct message') : `${activeConversation.participants.length} ${t('members')}`}
                                        </p>
                                    </div>
                                </div>
                                {activeConversation.type !== 'direct' && (
                                    <div className="hidden md:flex -space-x-2">
                                        {activeConversation.participants.slice(0, 4).map((p) => (
                                            <Tooltip key={p.id}>
                                                <TooltipTrigger asChild>
                                                    <Avatar className="h-7 w-7 ring-2 ring-background">
                                                        <AvatarImage src={p.avatar ?? undefined} />
                                                        <AvatarFallback className="text-[10px]">{getInitials(p.name)}</AvatarFallback>
                                                    </Avatar>
                                                </TooltipTrigger>
                                                <TooltipContent>{p.name}</TooltipContent>
                                            </Tooltip>
                                        ))}
                                        {activeConversation.participants.length > 4 && (
                                            <div className="flex h-7 w-7 items-center justify-center rounded-full bg-muted text-[10px] font-medium ring-2 ring-background">
                                                +{activeConversation.participants.length - 4}
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Messages */}
                            <ScrollArea className="flex-1 bg-muted/20">
                                <div className="w-full min-w-0 px-4 py-4">
                                    {messagesError && messages.length === 0 && (
                                        <div className="flex flex-col items-center gap-2 py-8 text-center">
                                            <p className="text-sm text-muted-foreground">{t("Couldn't load messages for this conversation.")}</p>
                                            <Button size="sm" variant="outline" onClick={() => fetchMessages(activeConversation.id)}>
                                                {t('Retry')}
                                            </Button>
                                        </div>
                                    )}
                                    {groupedMessages.map((group, gi) => {
                                        const firstMsg = group.messages[0];
                                        const lastMsg = group.messages[group.messages.length - 1];
                                        const isMine = firstMsg.is_mine;
                                        const msgTime = new Date(lastMsg.created_at).getTime();
                                        const isSeen = isMine && Object.values(participantsRead).some(
                                            (ts) => ts && new Date(ts).getTime() >= msgTime
                                        );
                                        return (
                                            <React.Fragment key={gi}>
                                                {group.showDate && (
                                                    <div className="flex items-center gap-3 my-4">
                                                        <div className="flex-1 h-px bg-border" />
                                                        <span className="text-[11px] font-medium text-muted-foreground bg-muted/50 px-2.5 py-0.5 rounded-full">
                                                            {formatDateDivider(firstMsg.created_at)}
                                                        </span>
                                                        <div className="flex-1 h-px bg-border" />
                                                    </div>
                                                )}
                                                <div className={cn('flex gap-2.5 mb-3', isMine ? 'flex-row-reverse' : 'flex-row')}>
                                                    {!isMine ? (
                                                        <div className="flex flex-col justify-end flex-shrink-0">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Avatar className="h-8 w-8">
                                                                        <AvatarImage src={firstMsg.sender.avatar ?? undefined} />
                                                                        <AvatarFallback className="text-xs font-semibold">{getInitials(firstMsg.sender.name)}</AvatarFallback>
                                                                    </Avatar>
                                                                </TooltipTrigger>
                                                                <TooltipContent>{firstMsg.sender.name}</TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                    ) : <div className="w-8 flex-shrink-0" />}
                                                    <div className={cn('flex flex-col gap-0.5 max-w-[65%]', isMine ? 'items-end' : 'items-start')}>
                                                        {!isMine && activeConversation.type !== 'direct' && (
                                                            <span className="ml-1 text-[11px] font-semibold text-muted-foreground mb-0.5">{firstMsg.sender.name}</span>
                                                        )}
                                                        {group.messages.map((msg, mi) => {
                                                            const isFirst = mi === 0;
                                                            const isLast = mi === group.messages.length - 1;
                                                            return (
                                                                <div key={msg.id} className={cn(
                                                                    'px-3.5 py-2 text-sm leading-relaxed shadow-sm break-words max-w-full',
                                                                    isMine ? 'bg-primary text-primary-foreground' : 'bg-background text-foreground',
                                                                    'rounded-2xl',
                                                                    isMine && isFirst && 'rounded-tr-md',
                                                                    isMine && !isFirst && !isLast && 'rounded-tr-sm rounded-br-sm',
                                                                    isMine && isLast && !isFirst && 'rounded-br-md',
                                                                    !isMine && isFirst && 'rounded-tl-md',
                                                                    !isMine && !isFirst && !isLast && 'rounded-tl-sm rounded-bl-sm',
                                                                    !isMine && isLast && !isFirst && 'rounded-bl-md',
                                                                )} style={{ wordBreak: 'break-word' }}>
                                                                    {msg.message}
                                                                </div>
                                                            );
                                                        })}
                                                        <div className={cn('flex items-center gap-1 px-1 mt-0.5', isMine && 'flex-row-reverse')}>
                                                            <span className="text-[10px] text-muted-foreground">{formatTime(lastMsg.created_at)}</span>
                                                            {isMine && (isSeen
                                                                ? <CheckCheck className="h-3.5 w-3.5 text-blue-500" />
                                                                : <Check className="h-3.5 w-3.5 text-muted-foreground/60" />
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </React.Fragment>
                                        );
                                    })}
                                    <div ref={messagesEndRef} />
                                </div>
                            </ScrollArea>

                            {/* Input */}
                            <div className="border-t bg-background px-4 py-3">
                                <div className="flex items-end gap-2 rounded-2xl border bg-muted/40 px-3 py-2 focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary/40 transition-all">
                                    <textarea
                                        ref={textareaRef}
                                        rows={1}
                                        className="flex-1 resize-none bg-transparent text-sm outline-none placeholder:text-muted-foreground min-h-[1.5rem] max-h-[7.5rem] py-0.5"
                                        placeholder={t('Type a message… (Shift+Enter for new line)')}
                                        value={messageInput}
                                        onChange={(e) => setMessageInput(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                    />
                                    <Button
                                        size="icon"
                                        className={cn(
                                            'h-8 w-8 rounded-full flex-shrink-0 transition-all',
                                            messageInput.trim() ? 'bg-primary text-primary-foreground shadow-md hover:bg-primary/90' : 'bg-muted text-muted-foreground'
                                        )}
                                        onClick={sendMessage}
                                        disabled={!messageInput.trim() || sending}
                                    >
                                        <Send className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                                <p className="mt-1.5 text-[11px] text-muted-foreground/60 pl-1">{t('Enter to send · Shift+Enter for new line')}</p>
                            </div>
                        </>
                    ) : (
                        <div className="flex flex-1 flex-col items-center justify-center gap-5 text-center p-8 bg-muted/10">
                            <div className="relative">
                                <div className="flex h-24 w-24 items-center justify-center rounded-3xl bg-primary/10 shadow-inner">
                                    <MessageSquare className="h-12 w-12 text-primary/60" />
                                </div>
                                <div className="absolute -right-2 -top-2 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-md">
                                    <Plus className="h-4 w-4" />
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <p className="text-xl font-semibold">{t('Your Messages')}</p>
                                <p className="text-sm text-muted-foreground max-w-xs">
                                    {t('Send private messages or create group chats with your workspace members.')}
                                </p>
                            </div>
                            <Button onClick={() => setNewChatOpen(true)} size="lg" className="rounded-full px-6">
                                <Edit3 className="mr-2 h-4 w-4" />
                                {t('New Conversation')}
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
