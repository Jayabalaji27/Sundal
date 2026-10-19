import { Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Activity, AlertTriangle, CheckCircle2, Clock, Receipt, Rocket } from 'lucide-react';
import { StatStrip, StatItem } from '@/components/stat-strip';
import { daysLeftLabel } from '@/components/projects/project-shell';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { formatCurrency } from '@/utils/currency';
import { cn } from '@/lib/utils';

/** Dashboard role as DashboardController sends it: the owner arrives as "company". */
export type DashboardRole = 'company' | 'manager' | 'member' | 'client' | string;

interface ActiveSprint {
    id: number;
    name: string;
    project: { id: number; title: string } | null;
    days_left: number | null;
    task_count: number;
    done_count: number;
    points: number;
    done_points: number;
    my_open: number;
}

interface Attention {
    overdue_tasks?: number;
    timesheet_approvals?: number;
    expense_approvals?: number;
}

const greetingFor = (hour: number) => (hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening');

/** One line that says what this home page is for, per role. */
export function RoleGreeting({ role, name }: { role: DashboardRole; name?: string }) {
    const { t } = useTranslation();
    const subtitle = {
        company: t("Here's how your workspace is doing."),
        manager: t("Here's what your team needs today."),
        member: t("Here's your work for today."),
        client: t("Here's the progress on your projects."),
        superadmin: t("Here's how the platform is doing."),
    }[role] || '';

    return (
        <div>
            <p className="text-lg font-semibold">{t(greetingFor(new Date().getHours()))}{name ? `, ${name.split(' ')[0]}` : ''}</p>
            {subtitle && <p className="text-sm text-muted-foreground">{subtitle}</p>}
        </div>
    );
}

/** The few numbers each role acts on, as one strip. Each one opens the list behind it. */
export function RoleStats({ role, data }: { role: DashboardRole; data: any }) {
    const { t } = useTranslation();
    const cards: any[] = data?.cards || [];
    const card = (key: string) => cards.find(item => item.key === key);
    const sprints: ActiveSprint[] = data?.activeSprints || [];
    const attention: Attention = data?.attention || {};
    const approvals = (attention.timesheet_approvals || 0) + (attention.expense_approvals || 0);
    const go = (href?: string | null) => (href ? () => router.get(href) : undefined);

    const projects = card('projects');
    const deadlines = card('deadlines');
    const received = card('received');
    const overdueLink = route('tasks.index', { sort_field: 'end_date', sort_direction: 'asc', view: 'list' });
    const approvalsLink = (attention.timesheet_approvals ?? 0) > 0 || attention.expense_approvals === undefined
        ? route('timesheet-approvals.index')
        : route('expense-approvals.index');

    const stats: (StatItem | false | undefined)[] = [];
    const sprintStat: StatItem = { key: 'sprints', label: t('Active sprints'), value: sprints.length, tone: 'success', onClick: go(route('projects.index', { status: 'active' })) };
    const overdueStat: StatItem | false = attention.overdue_tasks !== undefined && {
        key: 'overdue', label: role === 'member' ? t('My overdue tasks') : t('Overdue tasks'), value: attention.overdue_tasks, tone: 'danger', onClick: go(overdueLink),
    };
    const approvalStat: StatItem | false = (attention.timesheet_approvals !== undefined || attention.expense_approvals !== undefined) && {
        key: 'approvals', label: t('Waiting for approval'), value: approvals, tone: 'warning', onClick: go(approvalsLink),
    };
    const projectStat: StatItem | false = !!projects && { key: 'projects', label: t('Active projects'), value: projects.value, tone: 'info', onClick: go(projects.link) };

    if (role === 'company') {
        // The owner's strip is the money; approvals, sprints and risks have their own cards below.
        const invoices = data?.invoices;
        stats.push(
            received && { key: 'received', label: t('Received'), value: formatCurrency(received.value), tone: 'success', onClick: go(received.link) },
            !!invoices && { key: 'outstanding', label: t('Outstanding'), value: formatCurrency(invoices.outstanding_amount || 0), tone: 'warning', onClick: go(route('invoices.index', { status: 'sent' })) },
            !!invoices && { key: 'overdue-invoices', label: t('Overdue invoices'), value: formatCurrency(invoices.overdue_amount || 0), tone: 'danger', onClick: go(route('invoices.index', { status: 'overdue' })) },
        );
    } else if (role === 'manager') {
        stats.push(sprintStat, overdueStat, approvalStat, projectStat);
    } else if (role === 'member') {
        const open = (data?.myTasks || []).length;
        stats.push(
            data?.myTasks && { key: 'mine', label: t('My open tasks'), value: open >= 15 ? '15+' : open, tone: 'info', onClick: go(route('tasks.index')) },
            overdueStat,
            data?.weekHours !== null && data?.weekHours !== undefined && { key: 'hours', label: t('Hours this week'), value: data.weekHours, tone: 'violet', onClick: go(route('timesheets.index')) },
            sprints.length > 0 && sprintStat,
        );
    } else if (role === 'client') {
        const invoices = data?.invoices;
        stats.push(
            projectStat,
            !!deadlines && { key: 'deadlines', label: t('Upcoming deadlines'), value: deadlines.value, tone: 'warning', onClick: go(deadlines.link) },
            !!invoices && { key: 'invoices', label: t('Invoices to pay'), value: (invoices.pending || 0) + (invoices.overdue || 0), tone: 'danger', onClick: go(route('invoices.index')) },
        );
    }

    const items = stats.filter(Boolean) as StatItem[];
    return items.length > 0 ? <StatStrip items={items} /> : null;
}

/** Running sprints with progress; members also see how many of their own items are left. */
export function ActiveSprintsCard({ sprints, role }: { sprints: ActiveSprint[]; role: DashboardRole }) {
    const { t } = useTranslation();
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                    <Rocket className="h-4 w-4" />
                    {t('Active sprints')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {sprints.length === 0 ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">{t('No sprint is running. Plan one from a project\'s Sprints tab.')}</p>
                ) : sprints.map(sprint => {
                    const pct = sprint.task_count > 0 ? Math.round((sprint.done_count / sprint.task_count) * 100) : 0;
                    const left = daysLeftLabel(t, sprint.days_left);
                    return (
                        <Link
                            key={sprint.id}
                            href={sprint.project ? route('projects.board', sprint.project.id) : '#'}
                            className="block rounded-lg border p-3 transition-colors hover:border-primary/40 hover:bg-muted/30"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{sprint.name}</p>
                                    <p className="truncate text-xs text-muted-foreground">{sprint.project?.title}</p>
                                </div>
                                <span className={cn('shrink-0 text-xs', (sprint.days_left ?? 0) < 0 ? 'font-medium text-red-600' : 'text-muted-foreground')}>{left}</span>
                            </div>
                            <div className="mt-2 flex items-center gap-2">
                                <Progress value={pct} className="h-1.5" />
                                <span className="shrink-0 text-xs tabular-nums text-muted-foreground">{sprint.done_count}/{sprint.task_count}</span>
                            </div>
                            {role === 'member' && sprint.my_open > 0 && (
                                <p className="mt-1.5 text-xs text-primary">{t(':count of your tasks still open').replace(':count', String(sprint.my_open))}</p>
                            )}
                        </Link>
                    );
                })}
            </CardContent>
        </Card>
    );
}

export interface AttentionRow {
    key: string;
    icon: typeof Clock;
    count: number;
    label: string;
    href: string;
}

/** A "Needs attention" card: each row a queue with its count. Rows with nothing waiting are left out. */
export function AttentionList({ rows }: { rows: AttentionRow[] }) {
    const { t } = useTranslation();
    const pending = rows.filter(row => row.count > 0);

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                    <AlertTriangle className="h-4 w-4" />
                    {t('Needs attention')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {pending.length === 0 ? (
                    <div className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                        <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        {t('Nothing is waiting on you.')}
                    </div>
                ) : pending.map(row => (
                    <Link key={row.key} href={row.href} className="flex items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:border-primary/40 hover:bg-muted/30">
                        <row.icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                        <span className="flex-1">{row.label}</span>
                        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-amber-800 dark:bg-amber-900 dark:text-amber-100">{row.count}</span>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}

/** What waits on this person: approvals and overdue work, each a link to the queue. */
export function AttentionCard({ attention }: { attention: Attention }) {
    const { t } = useTranslation();
    const rows = [
        attention.timesheet_approvals !== undefined && {
            key: 'timesheets', icon: Clock, count: attention.timesheet_approvals, label: t('Timesheets waiting for your approval'), href: route('timesheet-approvals.index'),
        },
        attention.expense_approvals !== undefined && {
            key: 'expenses', icon: Receipt, count: attention.expense_approvals, label: t('Expenses waiting for your approval'), href: route('expense-approvals.index'),
        },
        attention.overdue_tasks !== undefined && {
            key: 'overdue', icon: AlertTriangle, count: attention.overdue_tasks, label: t('Tasks past their due date'), href: route('tasks.index', { sort_field: 'end_date', sort_direction: 'asc', view: 'list' }),
        },
    ].filter(Boolean) as AttentionRow[];

    return <AttentionList rows={rows} />;
}

interface ProjectAtRisk {
    id: number;
    title: string;
    score: number;
    status: 'at_risk' | 'critical';
    reason: string | null;
}

/** Owner home: projects whose health score isn't healthy, worst first, with the main reason. */
export function ProjectsAtRiskCard({ projects }: { projects: ProjectAtRisk[] }) {
    const { t } = useTranslation();
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                    <Activity className="h-4 w-4" />
                    {t('Projects at risk')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {projects.map(project => (
                    <Link
                        key={project.id}
                        href={route('projects.show', project.id)}
                        className="flex items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:border-primary/40 hover:bg-muted/30"
                    >
                        <span className={cn('w-10 shrink-0 text-center font-semibold tabular-nums', project.status === 'critical' ? 'text-red-600' : 'text-amber-600')}>
                            {project.score}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-medium">{project.title}</p>
                            {project.reason && <p className="truncate text-xs text-muted-foreground">{t(project.reason)}</p>}
                        </div>
                        <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-xs font-medium', project.status === 'critical' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700')}>
                            {project.status === 'critical' ? t('Critical') : t('At Risk')}
                        </span>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}

/** Splits "my tasks" by when they're due, for the member's "My work" list. */
export function groupByDue<T extends { end_date?: string | null; is_overdue?: boolean }>(tasks: T[]) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const weekEnd = new Date(today);
    weekEnd.setDate(today.getDate() + (7 - ((today.getDay() + 6) % 7)) - 1);
    weekEnd.setHours(23, 59, 59, 999);

    const groups: { key: string; label: string; tasks: T[] }[] = [
        { key: 'overdue', label: 'Overdue', tasks: [] },
        { key: 'week', label: 'Due this week', tasks: [] },
        { key: 'later', label: 'Later', tasks: [] },
        { key: 'none', label: 'No due date', tasks: [] },
    ];
    for (const task of tasks) {
        if (!task.end_date) groups[3].tasks.push(task);
        else if (task.is_overdue) groups[0].tasks.push(task);
        else if (new Date(task.end_date) <= weekEnd) groups[1].tasks.push(task);
        else groups[2].tasks.push(task);
    }
    return groups.filter(group => group.tasks.length > 0);
}
