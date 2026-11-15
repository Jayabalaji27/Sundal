import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertCircle, ArrowRight, Bot, History, MessageSquare, Pencil, PlusCircle, RefreshCw, Rocket, Trash2, UserRound, Workflow } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type StageValue = { name: string; color: string | null };
type Change = { field: string; label: string; type: 'stage' | 'user' | 'date' | 'text' | 'long'; from: any; to: any };
type Entry = {
    id: number | string;
    kind: 'created' | 'stage' | 'assignment' | 'update' | 'comment' | 'sprint' | 'deleted';
    summary: string;
    at: string | null;
    actor: { name: string; avatar: string | null } | null;
    source: 'screen' | 'ai_assistant' | 'system' | null;
    changes: Change[];
    note: string | null;
    note_type: 'reason' | 'comment' | null;
};

const FILTERS = ['all', 'stage', 'comment', 'update'] as const;
type Filter = (typeof FILTERS)[number];

const KIND_STYLE: Record<Entry['kind'], { icon: typeof History; tone: string }> = {
    created: { icon: PlusCircle, tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' },
    stage: { icon: Workflow, tone: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' },
    assignment: { icon: UserRound, tone: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300' },
    update: { icon: Pencil, tone: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' },
    comment: { icon: MessageSquare, tone: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' },
    sprint: { icon: Rocket, tone: 'bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300' },
    deleted: { icon: Trash2, tone: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' },
};

function Avatar({ person }: { person: Entry['actor'] }) {
    const [failed, setFailed] = useState(false);
    if (!person) {
        return <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ds-surface-sunken text-ds-fg-muted"><Bot className="h-4 w-4" /></div>;
    }
    return (
        <div className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-xs font-semibold uppercase text-primary">
            {person.avatar && !failed
                ? <img src={person.avatar} alt={person.name} className="h-full w-full object-cover" onError={() => setFailed(true)} />
                : person.name.substring(0, 2)}
        </div>
    );
}

function StageChip({ stage }: { stage: StageValue | null }) {
    if (!stage) return <span className="text-ds-fg-muted">—</span>;
    const color = stage.color || '#64748b';
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium" style={{ borderColor: `${color}66`, backgroundColor: `${color}14`, color }}>
            <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: color }} />
            {stage.name}
        </span>
    );
}

function formatValue(change: Change, value: any, empty: string) {
    if (value === null || value === undefined || value === '') return <span className="italic text-ds-fg-muted">{empty}</span>;
    if (change.type === 'stage') return <StageChip stage={value} />;
    if (change.type === 'date') return <span className="font-medium text-ds-fg">{new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })}</span>;
    return <span className="font-medium text-ds-fg [overflow-wrap:anywhere]">{String(value)}</span>;
}

function ChangeRow({ change, isCreate }: { change: Change; isCreate: boolean }) {
    const { t } = useTranslation();
    if (change.type === 'long') {
        return <div className="text-xs text-ds-fg-secondary"><span className="font-medium text-ds-fg">{change.label}</span> {t('was edited')}</div>;
    }
    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ds-fg-secondary">
            <span className="min-w-20 text-ds-fg-muted">{change.label}</span>
            {!isCreate && (
                <>
                    {formatValue(change, change.from, t('None'))}
                    <ArrowRight className="h-3 w-3 shrink-0 text-ds-fg-muted" aria-label={t('to')} />
                </>
            )}
            {formatValue(change, change.to, t('None'))}
        </div>
    );
}

function dayLabel(date: Date, t: (s: string) => string) {
    const today = new Date();
    const yesterday = new Date(); yesterday.setDate(today.getDate() - 1);
    if (date.toDateString() === today.toDateString()) return t('Today');
    if (date.toDateString() === yesterday.toDateString()) return t('Yesterday');
    return date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

/**
 * The task window's History tab: who changed what and when, read from the
 * history log. Grouped by day, newest first, filterable by kind.
 */
export default function TaskHistory({ taskId, refreshKey }: { taskId: number; refreshKey?: string | number }) {
    const { t } = useTranslation();
    const [entries, setEntries] = useState<Entry[] | null>(null);
    const [truncated, setTruncated] = useState(false);
    const [error, setError] = useState(false);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState<Filter>('all');

    const load = useCallback(async () => {
        setLoading(true);
        setError(false);
        try {
            const response = await fetch(route('tasks.history', taskId), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error(String(response.status));
            const data = await response.json();
            setEntries(data.entries || []);
            setTruncated(!!data.truncated);
        } catch {
            setError(true);
        } finally {
            setLoading(false);
        }
    }, [taskId]);

    useEffect(() => { load(); }, [load, refreshKey]);

    const visible = useMemo(() => (entries || []).filter((e) => {
        if (filter === 'all') return true;
        if (filter === 'stage') return e.kind === 'stage';
        if (filter === 'comment') return e.kind === 'comment';
        return ['update', 'assignment', 'sprint', 'created', 'deleted'].includes(e.kind);
    }), [entries, filter]);

    const groups = useMemo(() => {
        const out: { label: string; items: Entry[] }[] = [];
        for (const entry of visible) {
            const label = entry.at ? dayLabel(new Date(entry.at), t) : t('Unknown date');
            const last = out[out.length - 1];
            if (last && last.label === label) last.items.push(entry); else out.push({ label, items: [entry] });
        }
        return out;
    }, [visible, t]);

    const filterLabel: Record<Filter, string> = { all: t('All'), stage: t('Stage changes'), comment: t('Comments'), update: t('Edits') };

    return (
        <div className="flex flex-1 flex-col overflow-hidden pt-4">
            <div className="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-2">
                <div className="inline-flex rounded-lg border border-ds-border bg-ds-surface p-0.5" role="radiogroup" aria-label={t('Filter history')}>
                    {FILTERS.map((f) => (
                        <button
                            key={f}
                            type="button"
                            role="radio"
                            aria-checked={filter === f}
                            onClick={() => setFilter(f)}
                            className={cn(
                                'h-7 cursor-pointer rounded-md px-3 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus',
                                filter === f ? 'bg-primary text-primary-foreground shadow-ds-xs' : 'text-ds-fg-secondary hover:bg-ds-surface-hover hover:text-ds-fg',
                            )}
                        >
                            {filterLabel[f]}
                        </button>
                    ))}
                </div>
                <Button variant="ghost" size="sm" className="h-7 gap-1.5 text-xs" onClick={load} disabled={loading}>
                    <RefreshCw className={cn('h-3.5 w-3.5', loading && 'animate-spin')} />
                    {t('Refresh')}
                </Button>
            </div>

            <div className="flex-1 overflow-y-auto pr-2">
                {loading && entries === null ? (
                    <div className="space-y-4" aria-busy="true">
                        {[0, 1, 2].map((i) => (
                            <div key={i} className="flex gap-3">
                                <div className="h-8 w-8 animate-pulse rounded-full bg-ds-surface-sunken" />
                                <div className="flex-1 space-y-2 pt-1">
                                    <div className="h-3 w-1/2 animate-pulse rounded bg-ds-surface-sunken" />
                                    <div className="h-3 w-1/3 animate-pulse rounded bg-ds-surface-sunken" />
                                </div>
                            </div>
                        ))}
                    </div>
                ) : error ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                        <AlertCircle className="h-8 w-8 text-ds-danger-fg" />
                        <p className="text-sm text-ds-fg-secondary">{t('Could not load the history.')}</p>
                        <Button variant="outline" size="sm" onClick={load}>{t('Try again')}</Button>
                    </div>
                ) : groups.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
                        <History className="h-8 w-8 text-ds-fg-muted" />
                        <p className="text-sm font-medium text-ds-fg">{filter === 'all' ? t('No history yet') : t('Nothing of this kind yet')}</p>
                        <p className="text-xs text-ds-fg-muted">{t('Stage moves, edits and comments will appear here.')}</p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {groups.map((group) => (
                            <section key={group.label}>
                                <h4 className="sticky top-0 z-10 mb-3 bg-ds-surface py-1 text-[11px] font-semibold uppercase tracking-wide text-ds-fg-muted">{group.label}</h4>
                                <ol className="relative space-y-4 before:absolute before:bottom-2 before:left-4 before:top-2 before:w-px before:bg-ds-border-subtle">
                                    {group.items.map((entry) => {
                                        const style = KIND_STYLE[entry.kind] ?? KIND_STYLE.update;
                                        const Icon = style.icon;
                                        const when = entry.at ? new Date(entry.at) : null;
                                        return (
                                            <li key={entry.id} className="relative flex gap-3">
                                                <div className="relative">
                                                    <Avatar person={entry.actor} />
                                                    <span className={cn('absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-ds-surface', style.tone)}>
                                                        <Icon className="h-2.5 w-2.5" />
                                                    </span>
                                                </div>
                                                <div className="min-w-0 flex-1 pb-0.5">
                                                    <div className="flex flex-wrap items-baseline gap-x-1.5 text-sm">
                                                        <span className="font-semibold text-ds-fg">{entry.actor?.name ?? t('System')}</span>
                                                        <span className="text-ds-fg-secondary">{entry.summary}</span>
                                                        {entry.source === 'ai_assistant' && (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-violet-100 px-1.5 py-px text-[10px] font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                                                <Bot className="h-3 w-3" />{t('via AI assistant')}
                                                            </span>
                                                        )}
                                                        {when && (
                                                            <time dateTime={entry.at!} title={when.toLocaleString()} className="ml-auto text-xs tabular-nums text-ds-fg-muted">
                                                                {when.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}
                                                            </time>
                                                        )}
                                                    </div>
                                                    {entry.changes.length > 0 && (
                                                        <div className="mt-1.5 space-y-1 rounded-lg border border-ds-border-subtle bg-ds-surface-subtle px-3 py-2">
                                                            {entry.changes.map((change) => <ChangeRow key={change.field} change={change} isCreate={entry.kind === 'created'} />)}
                                                        </div>
                                                    )}
                                                    {entry.note && (
                                                        <div className={cn(
                                                            'mt-1.5 rounded-r-lg border-l-2 px-3 py-1.5 text-xs whitespace-pre-line [overflow-wrap:anywhere]',
                                                            entry.note_type === 'reason'
                                                                ? 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                                                                : 'border-ds-border-strong bg-ds-surface-subtle text-ds-fg-secondary',
                                                        )}>
                                                            {entry.note_type === 'reason' && <span className="mr-1 font-semibold">{t('Reason')}:</span>}
                                                            {entry.note}
                                                        </div>
                                                    )}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ol>
                            </section>
                        ))}
                        {truncated && <p className="pb-2 text-center text-xs text-ds-fg-muted">{t('Showing the latest 100 changes.')}</p>}
                    </div>
                )}
            </div>
        </div>
    );
}
