import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, Columns3 } from 'lucide-react';
import { ProjectShell, daysLeftLabel } from '@/components/projects/project-shell';
import { StatStrip } from '@/components/stat-strip';
import { AgileSprint, AgileTask, AssigneeAvatar, PointsBadge, PriorityIcon, SeverityBadge, StageChip, TypeIcon, shortDate } from '@/components/tasks/agile-bits';
import { useTaskDetail } from '@/components/tasks/use-task-detail';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** Sprint report: totals, burndown and the sprint's tasks. Planning happens on the Backlog. */
export default function SprintShow() {
    const { t } = useTranslation();
    const { project, sprint, stages = [], burndown = [] } = usePage().props as unknown as {
        project: any; sprint: AgileSprint & { tasks: AgileTask[] }; stages: any[]; burndown: { date: string; remaining: number; ideal: number }[];
    };
    const { openTask, taskDialog } = useTaskDetail({ stages });

    const usesPoints = sprint.points > 0;
    const isCompleted = sprint.status === 'completed';
    const remaining = sprint.task_count - sprint.done_count;
    const completion = sprint.task_count > 0 ? Math.round((sprint.done_count / sprint.task_count) * 100) : 0;
    const left = daysLeftLabel(t, sprint.days_left);
    const statusLabel = { planning: t('Planned'), active: t('Active'), completed: t('Completed') }[sprint.status];

    const actions = [
        { label: t('All sprints'), icon: <ArrowLeft className="h-4 w-4" />, variant: 'outline' as const, onClick: () => router.get(route('sprints.index', project.id)) },
        ...(sprint.status === 'active'
            ? [{ label: t('Open board'), icon: <Columns3 className="h-4 w-4" />, variant: 'outline' as const, onClick: () => router.get(route('projects.board', project.id)) }]
            : []),
    ];

    return (
        <ProjectShell project={project} active="sprints" section={sprint.name} actions={actions}>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h2 className="text-lg font-semibold">{sprint.name}</h2>
                <span className={cn('rounded px-1.5 py-0.5 text-[11px] font-medium uppercase tracking-wide',
                    sprint.status === 'active' ? 'bg-emerald-100 text-emerald-700' : sprint.status === 'completed' ? 'bg-violet-100 text-violet-700' : 'bg-blue-50 text-blue-700')}>
                    {statusLabel}
                </span>
                {(sprint.start_date || sprint.end_date) && (
                    <span className="text-sm text-muted-foreground">{shortDate(sprint.start_date) || '…'} – {shortDate(sprint.end_date) || '…'}</span>
                )}
                {sprint.status === 'active' && left && <span className="text-sm text-muted-foreground">· {left}</span>}
                {sprint.goal && <p className="basis-full text-sm text-muted-foreground"><span className="font-medium text-foreground">{t('Goal')}:</span> {sprint.goal}</p>}
            </div>

            <StatStrip
                items={[
                    { key: 'tasks', label: isCompleted ? t('Committed') : t('Tasks'), value: sprint.task_count },
                    { key: 'done', label: isCompleted ? t('Completed') : t('Done'), value: sprint.done_count, tone: 'success' },
                    isCompleted
                        ? { key: 'carried', label: t('Carried over'), value: sprint.carried_over ?? remaining, tone: 'warning' }
                        : { key: 'remaining', label: t('Remaining'), value: remaining, tone: 'warning' },
                    ...(usesPoints ? [{ key: 'points', label: t('Points done'), value: `${sprint.done_points} / ${sprint.points}`, tone: 'violet' as const }] : []),
                    { key: 'completion', label: t('Completion'), value: sprint.task_count > 0 ? `${completion}%` : '—', tone: 'info' },
                ]}
            />

            <div className="rounded-lg border bg-card p-4">
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-semibold">{t('Burndown')}</h3>
                    <span className="text-xs text-muted-foreground">{usesPoints ? t('Story points remaining') : t('Tasks remaining')}</span>
                </div>
                <BurndownChart points={burndown} t={t} />
            </div>

            <div className="overflow-hidden rounded-lg border bg-card">
                <div className="border-b bg-muted/30 px-3 py-2.5 text-sm font-semibold">{isCompleted ? t('Finished in this sprint') : t('Tasks in this sprint')}</div>
                {sprint.tasks.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-muted-foreground">{t('No tasks in this sprint.')}</p>
                ) : (
                    [...sprint.tasks]
                        .sort((a, b) => stageOrder(stages, a) - stageOrder(stages, b))
                        .map(task => (
                            <div key={task.id} className="flex items-center gap-2 border-b px-3 py-2 text-sm last:border-b-0 hover:bg-muted/40">
                                <TypeIcon type={task.type} />
                                <PriorityIcon priority={task.priority} />
                                <button type="button" onClick={() => openTask(task.id)} className={cn('min-w-0 flex-1 truncate text-left hover:text-primary hover:underline', task.is_completed && 'text-muted-foreground line-through')}>
                                    {task.title}
                                </button>
                                {task.type === 'bug' && <SeverityBadge severity={task.severity} className="hidden sm:inline-flex" />}
                                <StageChip stage={task.stage} />
                                <PointsBadge points={task.story_points} />
                                <AssigneeAvatar assignee={task.assignee} />
                            </div>
                        ))
                )}
            </div>

            {taskDialog}
        </ProjectShell>
    );
}

const stageOrder = (stages: { id: number }[], task: AgileTask) => {
    const index = stages.findIndex(stage => stage.id === task.task_stage_id);
    return index === -1 ? stages.length : index;
};

/** Remaining work per day against the ideal straight line. Colours follow the theme. */
function BurndownChart({ points, t }: { points: { date: string; remaining: number; ideal: number }[]; t: (key: string) => string }) {
    if (points.length < 2) {
        return (
            <div className="flex h-40 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground">
                {t('The chart appears once the sprint has run for two days.')}
            </div>
        );
    }
    const maxVal = Math.max(1, ...points.map(p => p.remaining), ...points.map(p => p.ideal));
    const W = 560, H = 200, PAD_X = 36, PAD_Y = 18;
    const x = (i: number) => PAD_X + (i / (points.length - 1)) * (W - PAD_X * 2);
    const y = (v: number) => PAD_Y + (1 - v / maxVal) * (H - PAD_Y * 2 - 14);
    const path = (key: 'remaining' | 'ideal') => points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(p[key])}`).join(' ');
    const labelEvery = Math.ceil(points.length / 6);

    return (
        <div className="overflow-x-auto">
            <svg viewBox={`0 0 ${W} ${H}`} className="w-full max-w-3xl text-muted-foreground" style={{ minWidth: 320 }} role="img" aria-label={t('Burndown')}>
                {[0, 0.5, 1].map(v => (
                    <g key={v}>
                        <line x1={PAD_X} x2={W - PAD_X} y1={y(v * maxVal)} y2={y(v * maxVal)} stroke="currentColor" strokeOpacity="0.15" />
                        <text x={PAD_X - 6} y={y(v * maxVal) + 3} textAnchor="end" fontSize="10" fill="currentColor">{Math.round(v * maxVal)}</text>
                    </g>
                ))}
                <path d={path('ideal')} fill="none" stroke="currentColor" strokeOpacity="0.5" strokeWidth="1.5" strokeDasharray="5 4" />
                <path d={path('remaining')} fill="none" stroke="var(--primary)" strokeWidth="2.25" strokeLinejoin="round" strokeLinecap="round" />
                {points.map((p, i) => <circle key={i} cx={x(i)} cy={y(p.remaining)} r="2.5" fill="var(--primary)" />)}
                {points.map((p, i) => i % labelEvery === 0 && (
                    <text key={p.date} x={x(i)} y={H - 4} textAnchor="middle" fontSize="10" fill="currentColor">{p.date}</text>
                ))}
            </svg>
            <div className="mt-1 flex gap-4 text-xs text-muted-foreground">
                <span className="flex items-center gap-1.5"><span className="h-0.5 w-4 bg-primary" />{t('Actual')}</span>
                <span className="flex items-center gap-1.5"><span className="h-0 w-4 border-t border-dashed border-muted-foreground" />{t('Ideal')}</span>
            </div>
        </div>
    );
}
