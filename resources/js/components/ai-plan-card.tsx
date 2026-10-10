import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, ChevronRight, FileText, Flag, Loader2 } from 'lucide-react';

interface Option {
    value: string;
    label: string;
}

export interface PlanRow {
    n: number;
    include: boolean;
    title: string;
    priority: string;
    milestone: string | null;
    description: string;
    source: string;
}

/** The 'plan' card view (App\Services\Ai\Tools\PlanTasksFromDocument). */
export interface PlanView {
    type: 'plan';
    file: string;
    project: Option | null;
    projects: Option[];
    new_project: string | null;
    can_create_project: boolean;
    uses_milestones: boolean;
    priorities: string[];
    rows: PlanRow[];
    included: number;
    sections_read: number;
    sections_total: number;
    skipped: string[];
    tokens: number;
    problem: string | null;
}

export type PlanChanges = {
    project?: number | null;
    new_project?: string | null;
    rows?: { n: number; include?: boolean; title?: string; priority?: string }[];
    all?: boolean;
};

const select = 'h-7 rounded-md border border-input bg-background px-1.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-60';
const NEW = '__new__';

/**
 * Milestones and tasks Sundal planned from a document. Every change goes to
 * the server at once (no AI call; the document is not read again).
 */
export function PlanList({ view, busy, onEdit }: { view: PlanView; busy: boolean; onEdit: (changes: PlanChanges) => void }) {
    const { t } = useTranslation();
    const [naming, setNaming] = useState(view.new_project !== null);
    const [name, setName] = useState(view.new_project ?? '');
    const [open, setOpen] = useState<number | null>(null);
    useEffect(() => { setName(view.new_project ?? ''); setNaming(view.new_project !== null); }, [view.new_project]);

    // Rows grouped by milestone, in the plan's order.
    const groups: { milestone: string | null; rows: PlanRow[] }[] = [];
    for (const row of view.rows) {
        const key = view.uses_milestones ? row.milestone : null;
        const last = groups[groups.length - 1];
        if (last && last.milestone === key) last.rows.push(row);
        else groups.push({ milestone: key, rows: [row] });
    }
    const allOn = view.rows.filter(r => r.title !== '').every(r => r.include);

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="inline-flex items-center gap-1.5 rounded-md bg-violet-50 px-2 py-1 text-violet-800 dark:bg-violet-950/40 dark:text-violet-300">
                    <FileText className="h-3.5 w-3.5" />
                    {view.file}
                </span>
                <span className="text-muted-foreground">→</span>
                <select
                    className={`${select} min-w-44 ${view.project || view.new_project ? '' : 'border-amber-400'}`}
                    value={naming ? NEW : view.project?.value ?? ''}
                    disabled={busy}
                    aria-label={t('Project')}
                    onChange={e => {
                        if (e.target.value === NEW) { setNaming(true); return; }
                        setNaming(false);
                        onEdit({ project: e.target.value ? Number(e.target.value) : null });
                    }}
                >
                    <option value="">{t('Choose the project…')}</option>
                    {view.projects.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}
                    {view.can_create_project && <option value={NEW}>{t('New project…')}</option>}
                </select>
                {naming && (
                    <form className="flex gap-1.5" onSubmit={e => { e.preventDefault(); if (name.trim()) onEdit({ new_project: name.trim() }); }}>
                        <input
                            value={name}
                            onChange={e => setName(e.target.value)}
                            onBlur={() => name.trim() && name.trim() !== view.new_project && onEdit({ new_project: name.trim() })}
                            placeholder={t('New project name')}
                            maxLength={120}
                            className={`${select} w-48 px-2`}
                            aria-label={t('New project name')}
                            autoFocus={view.new_project === null}
                        />
                    </form>
                )}
                {busy && <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />}
            </div>

            <p className="text-[11px] text-muted-foreground">
                {t('Read {{read}} of {{total}} sections, about {{tokens}} tokens.', { read: view.sections_read, total: view.sections_total, tokens: view.tokens.toLocaleString() })}
                {view.sections_read < view.sections_total && ` ${t('The rest of the document was not read; ask for a part of it to plan that.')}`}
                {view.skipped.length > 0 && <span className="text-amber-700 dark:text-amber-400"> {t('Could not read: {{list}}.', { list: view.skipped.join(', ') })}</span>}
            </p>

            <div className="max-h-[380px] overflow-auto rounded-lg border">
                <div className="sticky top-0 z-10 flex items-center gap-2 border-b bg-muted/90 px-2 py-1.5 text-[11px] uppercase tracking-wide text-muted-foreground backdrop-blur">
                    <input type="checkbox" checked={allOn} disabled={busy} onChange={e => onEdit({ all: e.target.checked })} aria-label={t('Tick every task')} />
                    <span className="flex-1">{t('Task')}</span>
                    <span className="w-24">{t('Priority')}</span>
                </div>
                {groups.map((group, g) => (
                    <div key={g}>
                        {view.uses_milestones && (
                            <div className="flex items-center gap-1.5 bg-muted/40 px-2 py-1 text-xs font-medium">
                                <Flag className="h-3.5 w-3.5 text-violet-600" />
                                {group.milestone ?? t('No milestone')}
                                <span className="font-normal text-muted-foreground">· {group.rows.filter(r => r.include).length}/{group.rows.length}</span>
                            </div>
                        )}
                        {group.rows.map(row => (
                            <PlanItem key={row.n} row={row} priorities={view.priorities} busy={busy} open={open === row.n}
                                onToggle={() => setOpen(open === row.n ? null : row.n)} onEdit={values => onEdit({ rows: [{ n: row.n, ...values }] })} />
                        ))}
                    </div>
                ))}
            </div>

            <p className={`text-xs ${view.problem ? 'font-medium text-amber-700 dark:text-amber-400' : 'text-muted-foreground'}`}>
                {view.problem ?? t('{{included}} of {{total}} tasks will be created. Open a task to read its acceptance criteria.', { included: view.included, total: view.rows.length })}
            </p>
        </div>
    );
}

function PlanItem({ row, priorities, busy, open, onToggle, onEdit }: {
    row: PlanRow;
    priorities: string[];
    busy: boolean;
    open: boolean;
    onToggle: () => void;
    onEdit: (values: { include?: boolean; title?: string; priority?: string }) => void;
}) {
    const { t } = useTranslation();
    const [title, setTitle] = useState(row.title);
    useEffect(() => setTitle(row.title), [row.title]);

    return (
        <div className={`border-t ${row.include ? '' : 'bg-muted/30 text-muted-foreground'}`}>
            <div className="flex items-center gap-2 px-2 py-1">
                <input type="checkbox" checked={row.include} disabled={busy || row.title === ''} onChange={e => onEdit({ include: e.target.checked })} aria-label={t('Include task {{n}}', { n: row.n })} />
                <button type="button" onClick={onToggle} className="rounded p-0.5 text-muted-foreground hover:bg-muted" aria-expanded={open} aria-label={t('Details of task {{n}}', { n: row.n })}>
                    {open ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
                </button>
                <input
                    value={title}
                    onChange={e => setTitle(e.target.value)}
                    onBlur={() => title.trim() && title.trim() !== row.title && onEdit({ title: title.trim() })}
                    onKeyDown={e => { if (e.key === 'Enter') (e.target as HTMLInputElement).blur(); }}
                    maxLength={255}
                    disabled={busy}
                    className="h-7 min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-1.5 text-xs hover:border-input focus:border-input focus:outline-none"
                    aria-label={t('Title of task {{n}}', { n: row.n })}
                />
                <select className={`${select} w-24`} value={row.priority} disabled={busy} onChange={e => onEdit({ priority: e.target.value })} aria-label={t('Priority of task {{n}}', { n: row.n })}>
                    {priorities.map(p => <option key={p} value={p}>{t(p.charAt(0).toUpperCase() + p.slice(1))}</option>)}
                </select>
            </div>
            {open && (
                <div className="space-y-1 px-9 pb-2 text-xs text-muted-foreground">
                    {row.description ? <p className="whitespace-pre-wrap">{row.description}</p> : <p>{t('No acceptance criteria in the document.')}</p>}
                    <p className="text-[11px]">{row.source}</p>
                </div>
            )}
        </div>
    );
}
