import { useState, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Bar, BarChart, CartesianGrid, LabelList, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { AlertTriangle, CalendarClock, CheckCircle2, FileSignature, Flag, Video, type LucideIcon } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatCurrency } from '@/utils/currency';
import { cn } from '@/lib/utils';

/*
 * Dashboard cards built on DashboardInsights (app/Services/DashboardInsights.php).
 * Charts follow the data-viz rules used across the app: one series in the brand
 * colour (--ds-chart-1), hairline grid, value-first tooltip, the current period
 * labelled, and a table view behind every chart.
 */

export interface MonthValue { key: string; label: string; full: string; value: number }
export interface DayHours { date: string; label: string; hours: number; today: boolean }
export interface StageCount { id: number; name: string; count: number }
export interface WorkloadPerson { id: number; name: string; avatar: string | null; open: number; overdue: number; hours: number }
export interface BudgetBurn { project: { id: number; title: string }; total: number; spent: number; used: number }
export interface DueTask { id: number; title: string; type: string; due: string; project: { id: number; title: string } | null; assignee: string | null }
export interface Meeting { key: string; provider: string; title: string; project: string | null; start_time: string; duration: number; join_url: string | null; show_url: string }
export interface Milestone { id: number; title: string; project: { id: number; title: string } | null; due: string | null; is_overdue: boolean; progress: number }
export interface ContractToSign { id: number; reference: string; subject: string; value: number | null; currency: string | null; sent: string | null }
export interface Activity { id: number; description: string; user: string; time: string; avatar?: string }
export interface Expiring { id: number; name: string; email: string; plan: string | null; kind: 'trial' | 'renewal'; date: string }

const ROW_LINK = 'rounded-lg border border-ds-border-subtle p-3 transition-colors duration-150 hover:border-ds-border-strong hover:bg-ds-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus';

const compact = (value: number) => new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const initials = (name: string) => name.split(/\s+/).filter(Boolean).slice(0, 2).map(word => word[0]).join('').toUpperCase();

/** "Today", "Tomorrow", or the weekday and date. */
function dayLabel(date: string, t: (key: string) => string): string {
    const day = new Date(`${date.slice(0, 10)}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diff = Math.round((day.getTime() - today.getTime()) / 86_400_000);
    if (diff === 0) return t('Today');
    if (diff === 1) return t('Tomorrow');
    return day.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
}

const timeOf = (iso: string) => new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

// ── Card shell ───────────────────────────────────────────────────────────────

export function InsightCard({ title, icon: Icon, action, children, className }: { title: string; icon: LucideIcon; action?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <Card className={cn('flex flex-col', className)}>
            <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 pb-3">
                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                    <Icon className="h-4 w-4 text-ds-fg-muted" aria-hidden="true" />
                    {title}
                </CardTitle>
                {action}
            </CardHeader>
            <CardContent className="flex-1">{children}</CardContent>
        </Card>
    );
}

function Empty({ icon: Icon = CheckCircle2, children }: { icon?: LucideIcon; children: ReactNode }) {
    return (
        <div className="flex items-center gap-2 py-6 text-sm text-ds-fg-secondary">
            <Icon className="h-4 w-4 shrink-0 text-ds-fg-muted" aria-hidden="true" />
            {children}
        </div>
    );
}

function CardLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Link href={href} className="shrink-0 rounded-sm text-sm font-medium text-ds-fg-accent hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus">
            {children}
        </Link>
    );
}

// ── Column chart with a table view ───────────────────────────────────────────

interface ColumnDatum { label: string; full: string; value: number; current?: boolean }

/**
 * Single-series columns (bars <= 24px, 4px rounded tops), the current period
 * labelled at its cap, a value-first tooltip, and a "Table" view with every
 * value - the tooltip never gates a number.
 */
export function ColumnChartCard({ title, icon, data, format, summary, action, emptyText, valueLabel }: {
    title: string;
    icon: LucideIcon;
    data: ColumnDatum[];
    format: (value: number) => string;
    summary?: ReactNode;
    action?: ReactNode;
    emptyText: string;
    valueLabel: string;
}) {
    const { t } = useTranslation();
    const [view, setView] = useState<'chart' | 'table'>('chart');
    const currentPeriod = (data.find(d => d.current) ?? data[data.length - 1])?.full;
    const hasValues = data.some(d => d.value > 0);

    const toggle = (
        <div className="flex items-center gap-2">
            {action}
            <div className="flex rounded-md border border-ds-border p-0.5" role="group" aria-label={t('View')}>
                {(['chart', 'table'] as const).map(option => (
                    <button
                        key={option}
                        type="button"
                        aria-pressed={view === option}
                        onClick={() => setView(option)}
                        className={cn('rounded px-2 py-0.5 text-xs font-medium transition-colors', view === option ? 'bg-ds-surface-selected text-ds-fg-accent' : 'text-ds-fg-muted hover:text-ds-fg')}
                    >
                        {option === 'chart' ? t('Chart') : t('Table')}
                    </button>
                ))}
            </div>
        </div>
    );

    return (
        <InsightCard title={title} icon={icon} action={toggle}>
            {summary && <div className="mb-3">{summary}</div>}
            {!hasValues && view === 'chart' ? (
                <Empty>{emptyText}</Empty>
            ) : view === 'chart' ? (
                <div className="h-56">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data} margin={{ top: 20, right: 4, bottom: 0, left: 0 }} accessibilityLayer>
                            <CartesianGrid vertical={false} stroke="var(--ds-chart-grid)" />
                            <XAxis dataKey="label" tickLine={false} axisLine={{ stroke: 'var(--ds-chart-axis)' }} tick={{ fill: 'var(--ds-text-muted)', fontSize: 12 }} />
                            <YAxis width={44} tickLine={false} axisLine={false} allowDecimals={false} tick={{ fill: 'var(--ds-text-muted)', fontSize: 12 }} tickFormatter={compact} />
                            <Tooltip
                                cursor={{ fill: 'var(--ds-surface-hover)' }}
                                content={({ active, payload }) => {
                                    const datum = payload?.[0]?.payload as ColumnDatum | undefined;
                                    if (!active || !datum) return null;
                                    return (
                                        <div className="rounded-md border border-ds-border bg-ds-surface px-3 py-2 text-xs shadow-ds-sm">
                                            <p className="text-sm font-semibold text-ds-fg">{format(datum.value)}</p>
                                            <p className="flex items-center gap-1.5 text-ds-fg-muted">
                                                <span className="h-0.5 w-3 rounded-full bg-ds-chart-1" aria-hidden="true" />
                                                {datum.full}
                                            </p>
                                        </div>
                                    );
                                }}
                            />
                            <Bar dataKey="value" fill="var(--ds-chart-1)" radius={[4, 4, 0, 0]} maxBarSize={24} isAnimationActive={false}>
                                {/* Zero-value bars aren't drawn, so match the current period by its row, not by index. */}
                                <LabelList
                                    valueAccessor={(entry: any) => (entry.payload?.full === currentPeriod ? entry.value : undefined)}
                                    content={({ viewBox, value }: any) => Number(value) > 0 && viewBox ? (
                                        <text x={Number(viewBox.x) + Number(viewBox.width) / 2} y={Number(viewBox.y) - 6} textAnchor="middle" fontSize={12} fontWeight={600} fill="var(--ds-text-secondary)">
                                            {format(Number(value))}
                                        </text>
                                    ) : null}
                                />
                            </Bar>
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            ) : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-ds-border-subtle text-left text-xs text-ds-fg-muted">
                            <th className="py-1.5 font-medium">{t('Period')}</th>
                            <th className="py-1.5 text-right font-medium">{valueLabel}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map(datum => (
                            <tr key={datum.full} className="border-b border-ds-border-subtle last:border-0">
                                <td className="py-1.5 text-ds-fg-secondary">{datum.full}</td>
                                <td className="py-1.5 text-right tabular-nums text-ds-fg">{format(datum.value)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </InsightCard>
    );
}

// ── Horizontal bars (labelled, so no tooltip needed) ─────────────────────────

/** Magnitude per category: one brand-coloured bar each, value at the tip. */
export function BarList({ rows, format = String }: { rows: { key: string | number; label: string; value: number; href?: string }[]; format?: (value: number) => string }) {
    const max = Math.max(1, ...rows.map(row => row.value));
    return (
        <ul className="space-y-2.5">
            {rows.map(row => {
                const content = (
                    <>
                        <span className="w-28 shrink-0 truncate text-sm text-ds-fg-secondary" title={row.label}>{row.label}</span>
                        <span className="flex flex-1 items-center gap-2">
                            <span className="h-2.5 rounded-r bg-ds-chart-1" style={{ width: `${(row.value / max) * 100}%`, minWidth: row.value > 0 ? 4 : 0 }} aria-hidden="true" />
                            <span className="text-sm font-medium tabular-nums text-ds-fg">{format(row.value)}</span>
                        </span>
                    </>
                );
                return (
                    <li key={row.key}>
                        {row.href ? (
                            <Link href={row.href} className="flex items-center gap-3 rounded-md px-1 py-0.5 hover:bg-ds-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus">{content}</Link>
                        ) : (
                            <div className="flex items-center gap-3 px-1 py-0.5">{content}</div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

/** Open tasks per stage (workflow order) and how many were finished this week. */
export function TasksByStageCard({ stages, doneThisWeek }: { stages: StageCount[]; doneThisWeek: number }) {
    const { t } = useTranslation();
    const open = stages.reduce((sum, stage) => sum + stage.count, 0);
    return (
        <InsightCard title={t('Work in progress')} icon={CheckCircle2} action={<CardLink href={route('tasks.index')}>{t('All tasks')}</CardLink>}>
            <p className="mb-3 text-sm text-ds-fg-secondary">
                <span className="font-semibold text-ds-fg">{open}</span> {t('open tasks')}
                <span className="mx-1.5 text-ds-fg-muted">·</span>
                <span className="font-semibold text-ds-fg">{doneThisWeek}</span> {t('completed this week')}
            </p>
            {open === 0 ? (
                <Empty>{t('No open tasks.')}</Empty>
            ) : (
                <BarList rows={stages.map(stage => ({ key: stage.id, label: stage.name, value: stage.count, href: route('tasks.index', { stage_id: stage.id }) }))} />
            )}
        </InsightCard>
    );
}

/** Who carries the open work: open and overdue tasks and this week's hours, busiest first. */
export function TeamWorkloadCard({ people }: { people: WorkloadPerson[] }) {
    const { t } = useTranslation();
    const max = Math.max(1, ...people.map(person => person.open));
    return (
        <InsightCard title={t('Team workload')} icon={CalendarClock}>
            {people.length === 0 ? (
                <Empty>{t('Nobody has open tasks assigned.')}</Empty>
            ) : (
                <ul className="space-y-1">
                    {people.map(person => (
                        <li key={person.id}>
                            <Link href={route('tasks.index', { assigned_to: person.id })} className="flex items-center gap-3 rounded-md px-1 py-1.5 hover:bg-ds-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus">
                                <Avatar className="h-7 w-7 shrink-0">
                                    {person.avatar && <AvatarImage src={person.avatar} alt="" />}
                                    <AvatarFallback seed={person.name} className="text-[11px] font-semibold">{initials(person.name)}</AvatarFallback>
                                </Avatar>
                                <span className="w-20 shrink-0 truncate text-sm text-ds-fg sm:w-28" title={person.name}>{person.name}</span>
                                <span className="flex min-w-0 flex-1 items-center gap-2">
                                    <span className="h-2.5 rounded-r bg-ds-chart-1" style={{ width: `${(person.open / max) * 100}%`, minWidth: person.open > 0 ? 4 : 0 }} aria-hidden="true" />
                                    <span className="text-sm font-medium tabular-nums text-ds-fg">{person.open}</span>
                                </span>
                                {person.overdue > 0 && (
                                    <span className="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-ds-danger-fg">
                                        <AlertTriangle className="h-3 w-3" aria-hidden="true" />
                                        {t(':count overdue').replace(':count', String(person.overdue))}
                                    </span>
                                )}
                                <span className="hidden w-12 shrink-0 text-right text-xs tabular-nums text-ds-fg-muted sm:block" title={t('Hours logged this week')}>{person.hours}h</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
            {people.length > 0 && (
                <p className="mt-2 text-xs text-ds-fg-muted">{t('Bars: open tasks · right: hours logged this week')}</p>
            )}
        </InsightCard>
    );
}

/** Spent against budget for active projects; the meter turns amber past 80% and red past 100%. */
export function BudgetBurnCard({ budgets }: { budgets: BudgetBurn[] }) {
    const { t } = useTranslation();
    return (
        <InsightCard title={t('Budget used')} icon={Flag} action={<CardLink href={route('budgets.index')}>{t('Budgets')}</CardLink>}>
            {budgets.length === 0 ? (
                <Empty>{t('No active project has a budget yet.')}</Empty>
            ) : (
                <ul className="space-y-3">
                    {budgets.map(budget => {
                        const tone = budget.used > 100 ? 'bg-ds-danger' : budget.used >= 80 ? 'bg-ds-warning' : 'bg-ds-chart-1';
                        return (
                            <li key={budget.project.id}>
                                <div className="mb-1 flex items-center justify-between gap-3 text-sm">
                                    <Link href={route('projects.show', { project: budget.project.id, tab: 'budget' })} className="truncate font-medium text-ds-fg hover:text-ds-fg-accent">{budget.project.title}</Link>
                                    <span className="shrink-0 tabular-nums text-ds-fg-secondary">{budget.used}%</span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-ds-surface-sunken" role="meter" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.min(100, budget.used)} aria-label={t('{{project}} budget used', { project: budget.project.title })}>
                                    <div className={cn('h-full rounded-full', tone)} style={{ width: `${Math.min(100, budget.used)}%` }} />
                                </div>
                                <p className="mt-1 text-xs text-ds-fg-muted">{formatCurrency(budget.spent)} {t('of')} {formatCurrency(budget.total)}</p>
                            </li>
                        );
                    })}
                </ul>
            )}
        </InsightCard>
    );
}

// ── Coming up: tasks, milestones and meetings in one dated list ──────────────

type ComingItem = { key: string; when: string; sort: number; icon: LucideIcon; title: string; detail: string; href: string; tone?: 'danger'; join?: string | null; time?: string };

export function ComingUpCard({ tasks = [], meetings = [], milestones = [], days = 7 }: { tasks?: DueTask[]; meetings?: Meeting[]; milestones?: Milestone[]; days?: number }) {
    const { t } = useTranslation();
    const items: ComingItem[] = [
        ...tasks.map(task => ({
            key: `task-${task.id}`, when: task.due, sort: new Date(`${task.due}T23:59:00`).getTime(), icon: CheckCircle2, title: task.title,
            detail: [task.project?.title, task.assignee].filter(Boolean).join(' · '), href: route('tasks.index', { task_id: task.id }),
        })),
        ...milestones.map(milestone => ({
            key: `milestone-${milestone.id}`, when: milestone.due || '', sort: milestone.due ? new Date(`${milestone.due}T23:59:00`).getTime() : Infinity, icon: Flag,
            title: milestone.title, detail: [milestone.project?.title, `${milestone.progress}%`].filter(Boolean).join(' · '),
            href: milestone.project ? route('projects.show', { project: milestone.project.id, tab: 'milestones' }) : '#', tone: milestone.is_overdue ? 'danger' as const : undefined,
        })),
        ...meetings.map(meeting => ({
            key: meeting.key, when: meeting.start_time, sort: new Date(meeting.start_time).getTime(), icon: Video, title: meeting.title,
            detail: [meeting.project, t(':count min').replace(':count', String(meeting.duration))].filter(Boolean).join(' · '), href: meeting.show_url,
            join: meeting.join_url, time: timeOf(meeting.start_time),
        })),
    ].sort((a, b) => a.sort - b.sort);

    return (
        <InsightCard title={t('Coming up')} icon={CalendarClock}>
            {items.length === 0 ? (
                <Empty icon={CalendarClock}>{t('Nothing due or scheduled in the next :days days.').replace(':days', String(days))}</Empty>
            ) : (
                <ul className="space-y-2">
                    {items.map(item => (
                        <li key={item.key} className={cn('flex items-center gap-3 text-sm', ROW_LINK)}>
                            <item.icon className="h-4 w-4 shrink-0 text-ds-fg-muted" aria-hidden="true" />
                            <Link href={item.href} className="min-w-0 flex-1 focus-visible:outline-none">
                                <p className="truncate font-medium text-ds-fg">{item.title}</p>
                                {item.detail && <p className="truncate text-xs text-ds-fg-muted">{item.detail}</p>}
                            </Link>
                            <span className={cn('shrink-0 text-right text-xs', item.tone === 'danger' ? 'font-medium text-ds-danger-fg' : 'text-ds-fg-secondary')}>
                                {item.when ? dayLabel(item.when, t) : t('No date')}
                                {item.time && <span className="block text-ds-fg-muted">{item.time}</span>}
                            </span>
                            {item.join && (
                                <Button asChild size="sm" variant="outline" className="h-7 shrink-0 px-2 text-xs">
                                    <a href={item.join} target="_blank" rel="noopener noreferrer">{t('Join')}</a>
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </InsightCard>
    );
}

// ── Activity ─────────────────────────────────────────────────────────────────

export function RecentActivityCard({ activities }: { activities: Activity[] }) {
    const { t } = useTranslation();
    return (
        <InsightCard title={t('Recent activity')} icon={CalendarClock}>
            {activities.length === 0 ? (
                <Empty icon={CalendarClock}>{t('No activity yet.')}</Empty>
            ) : (
                <ul className="space-y-3">
                    {activities.slice(0, 6).map(activity => (
                        <li key={activity.id} className="flex items-start gap-3">
                            <Avatar className="h-7 w-7 shrink-0">
                                {activity.avatar && <AvatarImage src={activity.avatar} alt="" />}
                                <AvatarFallback seed={activity.user} className="text-[11px] font-semibold">{initials(activity.user)}</AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1 text-sm">
                                <p className="text-ds-fg"><span className="font-medium">{activity.user}</span> <span className="text-ds-fg-secondary">{activity.description}</span></p>
                                <p className="text-xs text-ds-fg-muted">{activity.time}</p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </InsightCard>
    );
}

// ── Client ───────────────────────────────────────────────────────────────────

export function ContractsToSignCard({ contracts }: { contracts: ContractToSign[] }) {
    const { t } = useTranslation();
    return (
        <InsightCard title={t('Contracts to review')} icon={FileSignature} action={<CardLink href={route('contracts.index')}>{t('All contracts')}</CardLink>}>
            {contracts.length === 0 ? (
                <Empty>{t('No contract is waiting for you.')}</Empty>
            ) : (
                <ul className="space-y-2">
                    {contracts.map(contract => (
                        <li key={contract.id}>
                            <Link href={route('contracts.show', contract.id)} className={cn('flex items-center gap-3 text-sm', ROW_LINK)}>
                                <FileSignature className="h-4 w-4 shrink-0 text-ds-fg-muted" aria-hidden="true" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium text-ds-fg">{contract.subject}</p>
                                    <p className="truncate text-xs text-ds-fg-muted">{contract.reference}{contract.sent ? ` · ${t('sent')} ${dayLabel(contract.sent, t)}` : ''}</p>
                                </div>
                                {contract.value !== null && <span className="shrink-0 text-sm tabular-nums text-ds-fg-secondary">{formatCurrency(contract.value)}</span>}
                                <StatusBadge tone="warning">{t('Review')}</StatusBadge>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </InsightCard>
    );
}

// ── Platform admin ───────────────────────────────────────────────────────────

export function ExpiringSoonCard({ companies }: { companies: Expiring[] }) {
    const { t } = useTranslation();
    return (
        <InsightCard title={t('Trials and renewals (14 days)')} icon={CalendarClock} action={<CardLink href={route('companies.index')}>{t('Companies')}</CardLink>}>
            {companies.length === 0 ? (
                <Empty>{t('No trial ends and no plan renews in the next 14 days.')}</Empty>
            ) : (
                <ul className="space-y-2">
                    {companies.map(company => (
                        <li key={`${company.kind}-${company.id}`}>
                            <Link href={route('companies.index', { search: company.email })} className={cn('flex items-center gap-3 text-sm', ROW_LINK)}>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium text-ds-fg">{company.name}</p>
                                    <p className="truncate text-xs text-ds-fg-muted">{company.plan || t('No plan')}</p>
                                </div>
                                <StatusBadge tone={company.kind === 'trial' ? 'info' : 'warning'}>{company.kind === 'trial' ? t('Trial ends') : t('Renews')}</StatusBadge>
                                <span className="w-24 shrink-0 text-right text-xs text-ds-fg-secondary">{dayLabel(company.date, t)}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </InsightCard>
    );
}

/** Month buckets from DashboardInsights as chart data, the current month marked. */
export const monthsToColumns = (months: MonthValue[] = []): ColumnDatum[] =>
    months.map((month, index) => ({ label: month.label, full: month.full, value: month.value, current: index === months.length - 1 }));

/** A week of hours as chart data, today marked. */
export const daysToColumns = (days: DayHours[] = []): ColumnDatum[] =>
    days.map(day => ({ label: day.label, full: new Date(`${day.date}T00:00:00`).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'short' }), value: day.hours, current: day.today }));
