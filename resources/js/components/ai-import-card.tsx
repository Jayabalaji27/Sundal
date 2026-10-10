import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, ChevronDown, ChevronRight, Copy, FileSpreadsheet, Loader2 } from 'lucide-react';

interface Option {
    value: string;
    label: string;
}

export interface ImportRow {
    n: number;
    include: boolean;
    title: string;
    priority: string;
    severity: string | null;
    assignee: string | null;
    assignee_name: string | null;
    due_date: string | null;
    problems: string[];
    duplicate: boolean;
}

/** The 'import' card view (App\Services\Ai\Tools\ImportFromSheet). */
export interface ImportView {
    type: 'import';
    kind: 'bugs' | 'tasks';
    file: string;
    sheet: string;
    sheets: string[];
    project: Option | null;
    projects: Option[];
    headers: string[];
    fields: { name: string; label: string; column: string | null }[];
    members: Option[];
    can_assign: boolean;
    priorities: string[];
    severities: string[];
    rows: ImportRow[];
    total_rows: number;
    included: number;
    problem: string | null;
}

export type ImportChanges = {
    project?: number | null;
    sheet?: string;
    mapping?: Record<string, string | null>;
    rows?: { n: number; include?: boolean; assignee?: string | null; severity?: string; priority?: string }[];
    all?: boolean;
};

const select = 'h-7 rounded-md border border-input bg-background px-1.5 text-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-60';
const label = (value: string) => value.charAt(0).toUpperCase() + value.slice(1);

/**
 * The rows of a sheet about to become bugs or tasks. Every change goes to
 * the server at once (no AI call) and comes back as the new card.
 */
export function ImportTable({ view, busy, onEdit }: { view: ImportView; busy: boolean; onEdit: (changes: ImportChanges) => void }) {
    const { t } = useTranslation();
    const [showColumns, setShowColumns] = useState(view.fields.find(f => f.name === 'title')?.column == null);
    const allOn = view.rows.filter(r => r.title !== '').every(r => r.include);
    const changeRow = (n: number, values: Omit<NonNullable<ImportChanges['rows']>[number], 'n'>) => onEdit({ rows: [{ n, ...values }] });

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <FileSpreadsheet className="h-3.5 w-3.5" />
                    {view.file}
                </span>
                {view.sheets.length > 1 && (
                    <select className={select} value={view.sheet} disabled={busy} onChange={e => onEdit({ sheet: e.target.value })} aria-label={t('Sheet')}>
                        {view.sheets.map(name => <option key={name} value={name}>{name}</option>)}
                    </select>
                )}
                <span className="text-muted-foreground">→</span>
                <select
                    className={`${select} min-w-40 ${view.project ? '' : 'border-amber-400'}`}
                    value={view.project?.value ?? ''}
                    disabled={busy}
                    onChange={e => onEdit({ project: e.target.value ? Number(e.target.value) : null })}
                    aria-label={t('Project')}
                >
                    <option value="">{t('Choose the project…')}</option>
                    {view.projects.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}
                </select>
                {busy && <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />}
            </div>

            <div>
                <button type="button" className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground" onClick={() => setShowColumns(v => !v)} aria-expanded={showColumns}>
                    {showColumns ? <ChevronDown className="h-3.5 w-3.5" /> : <ChevronRight className="h-3.5 w-3.5" />}
                    {t('Columns')}
                    <span className="font-normal">· {view.fields.filter(f => f.column).map(f => `${f.label} ← ${f.column}`).slice(0, 3).join(', ')}{view.fields.filter(f => f.column).length > 3 ? '…' : ''}</span>
                </button>
                {showColumns && (
                    <div className="mt-2 grid gap-2 rounded-lg bg-muted/40 p-2 sm:grid-cols-2">
                        {view.fields.map(field => (
                            <label key={field.name} className="flex items-center justify-between gap-2 text-xs">
                                <span className={field.name === 'title' ? 'font-medium' : 'text-muted-foreground'}>{field.label}</span>
                                <select
                                    className={`${select} w-40 ${field.name === 'title' && !field.column ? 'border-amber-400' : ''}`}
                                    value={field.column ?? ''}
                                    disabled={busy}
                                    onChange={e => onEdit({ mapping: { [field.name]: e.target.value || null } })}
                                >
                                    <option value="">{t('— not used —')}</option>
                                    {view.headers.map(h => <option key={h} value={h}>{h}</option>)}
                                </select>
                            </label>
                        ))}
                    </div>
                )}
            </div>

            <div className="max-h-[360px] overflow-auto rounded-lg border">
                <table className="w-full text-xs">
                    <thead className="sticky top-0 z-10 bg-muted/90 text-left text-[11px] uppercase tracking-wide text-muted-foreground backdrop-blur">
                        <tr>
                            <th className="w-8 px-2 py-1.5">
                                <input type="checkbox" checked={allOn} disabled={busy} onChange={e => onEdit({ all: e.target.checked })} aria-label={t('Tick every row')} />
                            </th>
                            <th className="w-10 px-1 py-1.5">{t('Row')}</th>
                            <th className="px-2 py-1.5">{t('Title')}</th>
                            {view.severities.length > 0 && <th className="px-2 py-1.5">{t('Severity')}</th>}
                            <th className="px-2 py-1.5">{t('Priority')}</th>
                            {view.can_assign && <th className="px-2 py-1.5">{t('Assignee')}</th>}
                            <th className="w-8 px-2 py-1.5"><span className="sr-only">{t('Notes')}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {view.rows.map(row => (
                            <tr key={row.n} className={`border-t align-middle ${row.include ? '' : 'bg-muted/30 text-muted-foreground'}`}>
                                <td className="px-2 py-1">
                                    <input type="checkbox" checked={row.include} disabled={busy || row.title === ''} onChange={e => changeRow(row.n, { include: e.target.checked })} aria-label={t('Include row {{n}}', { n: row.n })} />
                                </td>
                                <td className="px-1 py-1 tabular-nums text-muted-foreground">{row.n}</td>
                                <td className="max-w-[260px] px-2 py-1">
                                    <span className="block truncate" title={row.title}>{row.title || <em className="text-muted-foreground">{t('No title')}</em>}</span>
                                    {row.duplicate && <span className="mt-0.5 inline-flex items-center gap-1 text-[10px] text-amber-700 dark:text-amber-400"><Copy className="h-3 w-3" />{t('Possible duplicate')}</span>}
                                </td>
                                {view.severities.length > 0 && (
                                    <td className="px-2 py-1">
                                        <select className={select} value={row.severity ?? ''} disabled={busy} onChange={e => changeRow(row.n, { severity: e.target.value })} aria-label={t('Severity of row {{n}}', { n: row.n })}>
                                            {view.severities.map(s => <option key={s} value={s}>{t(label(s))}</option>)}
                                        </select>
                                    </td>
                                )}
                                <td className="px-2 py-1">
                                    <select className={select} value={row.priority} disabled={busy} onChange={e => changeRow(row.n, { priority: e.target.value })} aria-label={t('Priority of row {{n}}', { n: row.n })}>
                                        {view.priorities.map(p => <option key={p} value={p}>{t(label(p))}</option>)}
                                    </select>
                                </td>
                                {view.can_assign && (
                                    <td className="px-2 py-1">
                                        <select className={`${select} max-w-40`} value={row.assignee ?? 'none'} disabled={busy} onChange={e => changeRow(row.n, { assignee: e.target.value })} aria-label={t('Assignee of row {{n}}', { n: row.n })}>
                                            <option value="none">{t('Unassigned')}</option>
                                            {view.members.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                                        </select>
                                    </td>
                                )}
                                <td className="px-2 py-1">
                                    {row.problems.length > 0 && (
                                        <span title={row.problems.join('\n')} aria-label={row.problems.join('. ')} className="inline-flex text-amber-600">
                                            <AlertTriangle className="h-3.5 w-3.5" />
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className={`text-xs ${view.problem ? 'font-medium text-amber-700 dark:text-amber-400' : 'text-muted-foreground'}`}>
                {view.problem ?? t('{{included}} of {{shown}} rows will be created. Rows with ⚠ have notes; hover to read them.', { included: view.included, shown: view.rows.length })}
                {view.total_rows > view.rows.length && ` ${t('Only the first {{count}} rows are imported.', { count: view.rows.length })}`}
            </p>
        </div>
    );
}
