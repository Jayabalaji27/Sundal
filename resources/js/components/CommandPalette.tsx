import { useEffect, useState, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import {
    Search, FolderKanban, CheckSquare, Bug, Clock, FileText,
    MessageSquare, BookOpen, Users, Settings, LayoutDashboard,
    Plus, Activity, AlertTriangle, Radio, ArrowRight, History
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';

interface SearchResults {
    projects: { id: number; title: string }[];
    tasks: { id: number; title: string }[];
    bugs: { id: number; title: string }[];
    totals?: { projects: number; tasks: number; bugs: number };
}

interface CommandItem {
    id: string;
    label: string;
    description?: string;
    icon: React.ReactNode;
    shortcut?: string;
    action: () => void;
    group: string;
    keywords?: string;
}

export function CommandPalette() {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);

    // ── Open on ⌘K / Ctrl+K ────────────────────────────────────────────────
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setOpen(prev => !prev);
            }
            if (e.key === 'Escape') setOpen(false);
        };
        const openHandler = () => setOpen(prev => !prev);
        window.addEventListener('keydown', handler);
        window.addEventListener('open-command-palette', openHandler);
        return () => {
            window.removeEventListener('keydown', handler);
            window.removeEventListener('open-command-palette', openHandler);
        };
    }, []);

    useEffect(() => {
        if (open) {
            setQuery('');
            setSelected(0);
            setResults({ projects: [], tasks: [], bugs: [] });
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    const navigate = (path: string) => { setOpen(false); router.visit(path); };

    // ── Live search across projects/tasks/bugs ──────────────────────────────
    const [results, setResults] = useState<SearchResults>({ projects: [], tasks: [], bugs: [] });
    const totals = results.totals;
    const moreCount = totals
        ? Math.max(0, totals.projects - results.projects.length)
            + Math.max(0, totals.tasks - results.tasks.length)
            + Math.max(0, totals.bugs - results.bugs.length)
        : 0;
    useEffect(() => {
        if (!open || query.trim().length < 2) {
            setResults({ projects: [], tasks: [], bugs: [] });
            return;
        }
        const handle = setTimeout(() => {
            fetch(route('search', { q: query.trim() }))
                .then(res => res.ok ? res.json() : null)
                .then(data => data && setResults(data))
                .catch(() => {});
        }, 250);
        return () => clearTimeout(handle);
    }, [query, open]);

    // ── Command registry ────────────────────────────────────────────────────
    const commands: CommandItem[] = [
        // Navigation
        { id: 'dashboard', label: t('Dashboard'), icon: <LayoutDashboard className="h-4 w-4" />, group: t('Navigate'), action: () => navigate(route('dashboard')) },
        ...(hasPermission(permissions, 'project_view_any') ? [
            { id: 'projects', label: t('Projects'), icon: <FolderKanban className="h-4 w-4" />, group: t('Navigate'), shortcut: 'P', action: () => navigate(route('projects.index')) },
        ] : []),
        ...(hasPermission(permissions, 'task_view_any') ? [
            { id: 'tasks', label: t('Tasks'), icon: <CheckSquare className="h-4 w-4" />, group: t('Navigate'), shortcut: 'T', action: () => navigate(route('tasks.index')) },
        ] : []),
        ...(hasPermission(permissions, 'bug_view_any') ? [
            { id: 'bugs', label: t('Bugs'), icon: <Bug className="h-4 w-4" />, group: t('Navigate'), action: () => navigate(route('bugs.index')) },
        ] : []),
        ...(hasPermission(permissions, 'timesheet_view_any') ? [
            { id: 'timesheets', label: t('Timesheets'), icon: <Clock className="h-4 w-4" />, group: t('Navigate'), action: () => navigate(route('timesheets.index')) },
        ] : []),
        ...(hasPermission(permissions, 'chat_view') ? [
            { id: 'chat', label: t('Chat'), icon: <MessageSquare className="h-4 w-4" />, group: t('Navigate'), action: () => navigate(route('chat.index')) },
        ] : []),
        ...(hasPermission(permissions, 'invoice_view_any') ? [
            { id: 'invoices', label: t('Invoices'), icon: <FileText className="h-4 w-4" />, group: t('Navigate'), action: () => navigate(route('invoices.index')) },
        ] : []),
        ...(hasPermission(permissions, 'kb_view_any') ? [
            { id: 'kb', label: t('Knowledge Base'), icon: <BookOpen className="h-4 w-4" />, group: t('Navigate'), action: () => navigate(route('kb.index')) },
        ] : []),

        // AI Tools
        ...((hasPermission(permissions, 'agent_use') || hasPermission(permissions, 'agent_view_any')) ? [
            { id: 'standup', label: t('Standup Bot'), description: t('Daily team standup'), icon: <Activity className="h-4 w-4" />, group: t('AI Tools'), action: () => navigate(route('standup.index')) },
        ] : []),
        ...(hasPermission(permissions, 'agent_advanced_insights') ? [
            { id: 'risk-radar', label: t('Risk Radar'), description: t('Project health overview'), icon: <Radio className="h-4 w-4" />, group: t('AI Tools'), action: () => navigate(route('risk-radar.index')) },
            { id: 'conflicts', label: t('Resource Conflicts'), description: t('Workload analysis'), icon: <AlertTriangle className="h-4 w-4" />, group: t('AI Tools'), action: () => navigate(route('resource-conflicts.index')) },
        ] : []),

        // Quick create
        ...(hasPermission(permissions, 'project_create') ? [
            { id: 'new-project', label: t('New Project'), icon: <Plus className="h-4 w-4 text-primary" />, group: t('Create'), shortcut: '+P', keywords: 'add create project', action: () => navigate(route('projects.index') + '?action=create') },
        ] : []),
        ...(hasPermission(permissions, 'task_create') ? [
            { id: 'new-task', label: t('New Task'), icon: <Plus className="h-4 w-4 text-primary" />, group: t('Create'), shortcut: '+T', keywords: 'add create task', action: () => navigate(route('tasks.index') + '?action=create') },
        ] : []),

        // Settings
        ...(hasPermission(permissions, 'settings_view') ? [
            { id: 'settings', label: t('Settings'), icon: <Settings className="h-4 w-4" />, group: t('Settings'), action: () => navigate(route('settings')) },
        ] : []),
        ...(hasPermission(permissions, 'user_view_logs') ? [
            { id: 'login-history', label: t('Login History'), icon: <History className="h-4 w-4" />, group: t('Settings'), keywords: 'login logout sign in audit log history', action: () => navigate(route('users.all-logs')) },
        ] : []),
        ...(hasPermission(permissions, 'workspace_view_any') ? [
            { id: 'workspace', label: t('Workspaces'), icon: <Users className="h-4 w-4" />, group: t('Settings'), action: () => navigate(route('workspaces.index')) },
        ] : []),

        // Live results from the server (projects/tasks/bugs matching the query)
        ...results.projects.map(p => ({
            id: `project-${p.id}`, label: p.title, icon: <FolderKanban className="h-4 w-4" />,
            group: t('Projects'), action: () => navigate(route('projects.show', p.id)),
        })),
        ...results.tasks.map(tItem => ({
            id: `task-${tItem.id}`, label: tItem.title, icon: <CheckSquare className="h-4 w-4" />,
            group: t('Tasks'), action: () => navigate(route('tasks.show', tItem.id)),
        })),
        ...results.bugs.map(b => ({
            id: `bug-${b.id}`, label: b.title, icon: <Bug className="h-4 w-4" />,
            group: t('Bugs'), action: () => navigate(route('bugs.show', b.id)),
        })),
    ];

    const filtered = query.trim()
        ? commands.filter(c =>
            c.label.toLowerCase().includes(query.toLowerCase()) ||
            c.description?.toLowerCase().includes(query.toLowerCase()) ||
            c.keywords?.toLowerCase().includes(query.toLowerCase()) ||
            c.group.toLowerCase().includes(query.toLowerCase())
        )
        : commands;

    const grouped = filtered.reduce<Record<string, CommandItem[]>>((acc, item) => {
        if (!acc[item.group]) acc[item.group] = [];
        acc[item.group].push(item);
        return acc;
    }, {});

    const flat = Object.values(grouped).flat();

    // ── Keyboard navigation ─────────────────────────────────────────────────
    const handleKey = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setSelected(s => Math.min(s + 1, flat.length - 1)); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); setSelected(s => Math.max(s - 1, 0)); }
        if (e.key === 'Enter' && flat[selected]) { flat[selected].action(); }
    };

    useEffect(() => {
        // Scroll selected item into view
        const el = listRef.current?.querySelector(`[data-idx="${selected}"]`);
        el?.scrollIntoView({ block: 'nearest' });
    }, [selected]);

    let globalIdx = 0;

    if (!open) return null;

    return (
        <>
            {/* Backdrop */}
            <div
                className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm"
                onClick={() => setOpen(false)}
            />
            {/* Palette */}
            <div className="fixed left-1/2 top-[18%] z-50 w-full max-w-lg -translate-x-1/2 rounded-xl border bg-background shadow-2xl overflow-hidden">
                {/* Search input */}
                <div className="flex items-center gap-3 px-4 py-3 border-b">
                    <Search className="h-4 w-4 text-muted-foreground shrink-0" />
                    <Input
                        ref={inputRef}
                        value={query}
                        onChange={e => { setQuery(e.target.value); setSelected(0); }}
                        onKeyDown={handleKey}
                        placeholder={t('Search commands, pages, actions...')}
                        className="border-0 shadow-none focus-visible:ring-0 p-0 h-auto text-sm bg-transparent"
                    />
                    <kbd className="hidden sm:inline-flex h-5 items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] text-muted-foreground">
                        ESC
                    </kbd>
                </div>

                {/* Results */}
                <div ref={listRef} className="max-h-[28rem] overflow-y-auto py-2">
                    {flat.length === 0 && (
                        <p className="text-center text-sm text-muted-foreground py-8">{t('No results for')} &ldquo;{query}&rdquo;</p>
                    )}
                    {Object.entries(grouped).map(([group, items]) => (
                        <div key={group}>
                            <p className="px-4 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{group}</p>
                            {items.map(item => {
                                const idx = globalIdx++;
                                const isSelected = idx === selected;
                                return (
                                    <button
                                        key={item.id}
                                        data-idx={idx}
                                        onClick={() => item.action()}
                                        onMouseEnter={() => setSelected(idx)}
                                        className={`w-full flex items-center gap-3 px-4 py-2 text-left transition-colors text-sm ${
                                            isSelected ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'
                                        }`}
                                    >
                                        <span className={isSelected ? 'text-primary-foreground' : 'text-muted-foreground'}>{item.icon}</span>
                                        <span className="flex-1">
                                            <span className="font-medium">{item.label}</span>
                                            {item.description && <span className={`ml-2 text-xs ${isSelected ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>{item.description}</span>}
                                        </span>
                                        {item.shortcut && (
                                            <kbd className={`text-[10px] px-1.5 py-0.5 rounded border font-mono ${isSelected ? 'border-primary-foreground/30 text-primary-foreground/70' : 'border-border text-muted-foreground bg-muted'}`}>
                                                {item.shortcut}
                                            </kbd>
                                        )}
                                        <ArrowRight className={`h-3.5 w-3.5 ${isSelected ? 'opacity-70' : 'opacity-0'}`} />
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                    {moreCount > 0 && (
                        <p className="px-4 py-2 text-center text-[11px] text-muted-foreground">
                            {t('{{count}} more results — refine your search to narrow down', { count: moreCount })}
                        </p>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center gap-4 px-4 py-2 border-t bg-muted/30 text-[10px] text-muted-foreground">
                    <span><kbd className="font-mono">↑↓</kbd> navigate</span>
                    <span><kbd className="font-mono">↵</kbd> open</span>
                    <span><kbd className="font-mono">esc</kbd> close</span>
                    <span className="ml-auto"><kbd className="font-mono">Ctrl+K</kbd></span>
                </div>
            </div>
        </>
    );
}
