import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { ArrowLeft, CheckCircle2, Play, Columns, TrendingDown, ArrowUpRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const PRIORITY_COLOR: Record<string, string> = {
    urgent: 'bg-red-100 text-red-700',
    high:   'bg-orange-100 text-orange-700',
    medium: 'bg-blue-100 text-blue-700',
    low:    'bg-slate-100 text-slate-600',
};

export default function SprintShow() {
    const { t } = useTranslation();
    const { sprint, stages, burndown } = usePage().props as any;

    const tasksByStage = (stageId: number) =>
        sprint.tasks.filter((t: any) => t.task_stage_id === stageId);

    const totalTasks = sprint.tasks.length;
    const doneTasks  = sprint.tasks.filter((t: any) =>
        t.task_stage && (
            t.task_stage.name.toLowerCase().includes('done') ||
            t.task_stage.name.toLowerCase().includes('complete') ||
            t.task_stage.name.toLowerCase().includes('closed')
        )
    ).length;

    // Simple inline burndown chart using SVG
    const BurndownChart = () => {
        if (burndown.length < 2) return (
            <div className="flex h-40 items-center justify-center text-sm text-muted-foreground rounded-xl border">
                {t('Not enough data yet. Check back after the sprint starts.')}
            </div>
        );
        const maxVal = Math.max(...burndown.map((p: any) => p.remaining), ...burndown.map((p: any) => p.ideal));
        const W = 500, H = 160, PAD = 40;
        const x = (i: number) => PAD + (i / (burndown.length - 1)) * (W - PAD * 2);
        const y = (v: number) => PAD + (1 - v / maxVal) * (H - PAD * 2);
        const idealPath = burndown.map((p: any, i: number) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(p.ideal)}`).join(' ');
        const actualPath = burndown.map((p: any, i: number) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(p.remaining)}`).join(' ');

        return (
            <div className="overflow-x-auto">
                <svg viewBox={`0 0 ${W} ${H}`} className="w-full max-w-xl" style={{ minWidth: 300 }}>
                    {/* Grid lines */}
                    {[0, 0.25, 0.5, 0.75, 1].map(v => (
                        <line key={v} x1={PAD} x2={W - PAD} y1={y(v * maxVal)} y2={y(v * maxVal)}
                            stroke="#e2e8f0" strokeWidth="1" />
                    ))}
                    {/* Ideal line */}
                    <path d={idealPath} fill="none" stroke="#94a3b8" strokeWidth="1.5" strokeDasharray="6 3" />
                    {/* Actual line */}
                    <path d={actualPath} fill="none" stroke="#6366f1" strokeWidth="2" strokeLinecap="round" />
                    {/* Dots */}
                    {burndown.map((p: any, i: number) => (
                        <circle key={i} cx={x(i)} cy={y(p.remaining)} r="3" fill="#6366f1" />
                    ))}
                    {/* Date labels (every nth) */}
                    {burndown.filter((_: any, i: number) => i % Math.ceil(burndown.length / 5) === 0).map((p: any, i: number) => {
                        const origIdx = burndown.findIndex((b: any) => b.date === p.date);
                        return (
                            <text key={i} x={x(origIdx)} y={H - 8} textAnchor="middle" fontSize="10" fill="#94a3b8">{p.date}</text>
                        );
                    })}
                    {/* Legend */}
                    <line x1={W - 120} x2={W - 100} y1={15} y2={15} stroke="#94a3b8" strokeWidth="1.5" strokeDasharray="4 2" />
                    <text x={W - 96} y={19} fontSize="10" fill="#94a3b8">Ideal</text>
                    <line x1={W - 120} x2={W - 100} y1={28} y2={28} stroke="#6366f1" strokeWidth="2" />
                    <text x={W - 96} y={32} fontSize="10" fill="#6366f1">Actual</text>
                </svg>
            </div>
        );
    };

    return (
        <PageTemplate
            title={sprint.name}
            description={sprint.goal || sprint.project?.title || ''}
            url={route('sprints.show', sprint.id)}
            breadcrumbs={[
                { title: t('Projects'), href: route('projects.index') },
                { title: sprint.project?.title, href: route('projects.show', sprint.project?.id) },
                { title: t('Sprints'), href: route('sprints.index', sprint.project?.id) },
                { title: sprint.name, href: route('sprints.show', sprint.id) },
            ]}
            actions={[
                sprint.status === 'planning' && { label: t('Start Sprint'), icon: <Play className="mr-1.5 h-4 w-4" />, variant: 'default' as const, onClick: () => router.post(route('sprints.start', sprint.id)) },
                sprint.status === 'active'   && { label: t('Complete Sprint'), icon: <CheckCircle2 className="mr-1.5 h-4 w-4" />, variant: 'outline' as const, onClick: () => router.post(route('sprints.complete', sprint.id)) },
            ].filter(Boolean) as any}
        >
            {/* Stats row */}
            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                {[
                    { label: t('Total Tasks'), value: totalTasks, icon: <Columns className="h-4 w-4" /> },
                    { label: t('Done'), value: doneTasks, icon: <CheckCircle2 className="h-4 w-4 text-green-500" /> },
                    { label: t('Remaining'), value: totalTasks - doneTasks, icon: <TrendingDown className="h-4 w-4 text-orange-500" /> },
                    { label: t('Completion'), value: totalTasks > 0 ? `${Math.round(doneTasks / totalTasks * 100)}%` : '—', icon: <ArrowUpRight className="h-4 w-4 text-primary" /> },
                ].map((s, i) => (
                    <div key={i} className="rounded-xl border bg-card p-4">
                        <div className="flex items-center gap-2 text-muted-foreground mb-1">{s.icon}<span className="text-xs">{s.label}</span></div>
                        <p className="text-2xl font-bold">{s.value}</p>
                    </div>
                ))}
            </div>

            {/* Burndown chart */}
            {burndown.length > 0 && (
                <div className="mb-6 rounded-xl border p-4">
                    <h3 className="mb-3 font-semibold text-sm">{t('Burndown Chart')}</h3>
                    <BurndownChart />
                </div>
            )}

            {/* Kanban board */}
            <div className="flex gap-4 overflow-x-auto pb-4">
                {stages.map((stage: any) => {
                    const tasks = tasksByStage(stage.id);
                    return (
                        <div key={stage.id} className="shrink-0 w-64">
                            <div className="mb-3 flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: stage.color || '#6366f1' }} />
                                <span className="font-semibold text-sm">{stage.name}</span>
                                <span className="ml-auto rounded-full bg-muted px-2 py-0.5 text-xs">{tasks.length}</span>
                            </div>
                            <div className="space-y-2 min-h-[80px]">
                                {tasks.length === 0 && (
                                    <div className="rounded-lg border-2 border-dashed py-6 text-center text-xs text-muted-foreground">Empty</div>
                                )}
                                {tasks.map((task: any) => (
                                    <div key={task.id} className="rounded-lg border bg-card p-3 shadow-sm">
                                        <p className="text-sm font-medium leading-snug">{task.title}</p>
                                        <div className="mt-2 flex items-center justify-between">
                                            {task.priority && (
                                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${PRIORITY_COLOR[task.priority] || ''}`}>{task.priority}</span>
                                            )}
                                            <div className="flex items-center gap-1 ml-auto">
                                                {task.assigned_to && (
                                                    <Avatar className="h-5 w-5">
                                                        <AvatarImage src={task.assigned_to?.avatar} />
                                                        <AvatarFallback className="text-[9px]">{task.assigned_to?.name?.charAt(0)}</AvatarFallback>
                                                    </Avatar>
                                                )}
                                                <button onClick={() => router.delete(route('sprints.remove-task', sprint.id), { data: { task_id: task.id } })}
                                                    className="text-[10px] text-muted-foreground hover:text-destructive ml-1">
                                                    ✕
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>
        </PageTemplate>
    );
}
