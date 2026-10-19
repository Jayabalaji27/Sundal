import { useEffect, useMemo, useState, type DragEvent } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Inbox, Plus, Rocket, Search } from 'lucide-react';
import { ProjectShell, daysLeftLabel, openProjectTaskForm } from '@/components/projects/project-shell';
import { Bucket, MovableTaskRow, moveTask, readDroppedTask } from '@/components/sprints/movable-task-row';
import { SprintFormDialog, SprintFormValues, newSprintValues, sprintDateRange } from '@/components/sprints/sprint-form';
import { AgileSprint, AgileTask } from '@/components/tasks/agile-bits';
import { useTaskDetail } from '@/components/tasks/use-task-detail';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { hasPermission } from '@/utils/authorization';
import { cn } from '@/lib/utils';

/** Both panes fill the screen and scroll on their own, so a long backlog never pushes the sprint away. */
const PANE = 'flex min-h-[24rem] flex-col overflow-hidden rounded-lg border bg-card transition-colors lg:h-[calc(100vh-15rem)]';

/**
 * Project Backlog, side by side with a sprint (like Zoho Sprints): unplanned
 * work on the left, most urgent first; the chosen sprint and its items on the
 * right. Plan by dragging an item across, or with its one-click button.
 */
export default function ProjectBacklog() {
    const { t } = useTranslation();
    const { auth, project, backlog = [], sprints = [], nextSprintName, stages = [], taskForm = null } = usePage().props as any;
    const permissions: string[] = auth?.permissions || [];
    const canCreateSprint = hasPermission(permissions, 'sprint_create');
    const canMove = hasPermission(permissions, 'sprint_manage_tasks');

    const tasks = backlog as AgileTask[];
    const openSprints = sprints as AgileSprint[];
    const { openTask, taskDialog } = useTaskDetail({ members: taskForm?.members || [], stages, milestones: taskForm?.projects?.[0]?.milestones || [] });

    const [search, setSearch] = useState('');
    const [priority, setPriority] = useState('all');
    const [dropTarget, setDropTarget] = useState<Bucket | null>(null);
    const [sprintForm, setSprintForm] = useState<{ key: number; initial: SprintFormValues } | null>(null);
    const openCreateSprint = () => setSprintForm({ key: Date.now(), initial: newSprintValues(openSprints, nextSprintName || t('Sprint')) });

    // The sprint on the right: the running one first (the server lists it first).
    const [sprintId, setSprintId] = useState<number | null>(openSprints[0]?.id ?? null);
    useEffect(() => {
        if (!openSprints.some(sprint => sprint.id === sprintId)) setSprintId(openSprints[0]?.id ?? null);
    }, [openSprints, sprintId]);
    const sprint = openSprints.find(item => item.id === sprintId) || null;
    const sprintTasks = sprint?.tasks || [];

    const visible = useMemo(() => tasks.filter(task =>
        (priority === 'all' || task.priority === priority)
        && (!search || task.title.toLowerCase().includes(search.toLowerCase()))
    ), [tasks, priority, search]);
    const totalPoints = tasks.reduce((sum, task) => sum + (task.story_points || 0), 0);

    const sprintDestinations = openSprints.map(item => ({ bucket: item.id as Bucket, label: item.name }));
    const destinationsFor = (from: Bucket) => [
        ...(from !== 'backlog' ? [{ bucket: 'backlog' as Bucket, label: t('Backlog') }] : []),
        ...sprintDestinations.filter(dest => dest.bucket !== from),
    ];

    const dropProps = (to: Bucket) => canMove ? {
        onDragOver: (event: DragEvent) => { event.preventDefault(); if (dropTarget !== to) setDropTarget(to); },
        onDragLeave: (event: DragEvent) => { if (!event.currentTarget.contains(event.relatedTarget as Node)) setDropTarget(null); },
        onDrop: (event: DragEvent) => {
            event.preventDefault();
            setDropTarget(null);
            const dropped = readDroppedTask(event);
            if (dropped) moveTask(dropped.taskId, dropped.from, to);
        },
    } : {};

    const count = (n: number, points: number) => (
        <span className="text-xs tabular-nums text-muted-foreground">
            {n === 1 ? t('1 item') : t(':count items').replace(':count', String(n))}
            {points > 0 && <> · {t(':count pts').replace(':count', String(points))}</>}
        </span>
    );

    return (
        <ProjectShell project={project} active="backlog" taskForm={taskForm} onCreateSprint={canCreateSprint ? openCreateSprint : undefined}>
            <div className="grid gap-4 lg:grid-cols-2">
                {/* ── Backlog: work nobody has planned yet ── */}
                <section
                    {...dropProps('backlog')}
                    aria-label={t('Backlog')}
                    className={cn(PANE, dropTarget === 'backlog' && 'border-primary ring-2 ring-primary/40')}
                >
                    <header className="space-y-2 border-b bg-muted/30 px-3 py-2.5">
                        <div className="flex items-center gap-2">
                            <Inbox className="h-4 w-4 text-muted-foreground" />
                            <h2 className="font-semibold">{t('Backlog')}</h2>
                            {count(tasks.length, totalPoints)}
                            <span className="ml-auto hidden text-xs text-muted-foreground sm:inline">{t('Most urgent first')}</span>
                        </div>
                        <div className="flex gap-2">
                            <div className="relative min-w-0 flex-1">
                                <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('Search the backlog...')} className="h-8 bg-background pl-9" />
                            </div>
                            <Select value={priority} onValueChange={setPriority}>
                                <SelectTrigger className="h-8 w-36 bg-background" aria-label={t('Priority')}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('All priorities')}</SelectItem>
                                    <SelectItem value="critical">{t('Critical')}</SelectItem>
                                    <SelectItem value="high">{t('High')}</SelectItem>
                                    <SelectItem value="medium">{t('Medium')}</SelectItem>
                                    <SelectItem value="low">{t('Low')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </header>
                    <div className="flex-1 overflow-y-auto">
                        {visible.map(task => (
                            <MovableTaskRow
                                key={task.id}
                                task={task}
                                bucket="backlog"
                                canMove={canMove}
                                destinations={destinationsFor('backlog')}
                                onOpen={openTask}
                                quickMove={sprint ? { to: sprint.id, label: t('Add to :sprint').replace(':sprint', sprint.name) } : undefined}
                                layout="stacked"
                            />
                        ))}
                        {visible.length === 0 && (
                            <div className="m-3 rounded-md border-2 border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                                {tasks.length > 0 ? t('No items match.') : (
                                    <>
                                        <p>{t('Nothing waiting: every open item is planned into a sprint.')}</p>
                                        {taskForm && (
                                            <button type="button" onClick={() => openProjectTaskForm()} className="mt-1 font-medium text-primary hover:underline">
                                                {t('Create a task')}
                                            </button>
                                        )}
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                </section>

                {/* ── The sprint being planned ── */}
                {sprint ? (
                    <section
                        {...dropProps(sprint.id)}
                        aria-label={sprint.name}
                        className={cn(PANE, sprint.status === 'active' && 'border-emerald-300 dark:border-emerald-800', dropTarget === sprint.id && 'border-primary ring-2 ring-primary/40')}
                    >
                        <header className="space-y-1 border-b bg-muted/30 px-3 py-2.5">
                            <div className="flex items-center gap-2">
                                <Rocket className={cn('h-4 w-4 shrink-0', sprint.status === 'active' ? 'text-emerald-600' : 'text-muted-foreground')} />
                                {openSprints.length > 1 ? (
                                    <Select value={String(sprint.id)} onValueChange={(value) => setSprintId(Number(value))}>
                                        <SelectTrigger className="h-8 w-auto max-w-[16rem] gap-2 border-none bg-transparent px-1 font-semibold shadow-none" aria-label={t('Sprint')}>
                                            {/* Just the name: the badge beside it says Active or Planned. */}
                                            <SelectValue>{sprint.name}</SelectValue>
                                        </SelectTrigger>
                                        <SelectContent>
                                            {openSprints.map(item => (
                                                <SelectItem key={item.id} value={String(item.id)}>
                                                    {item.name}{item.status === 'active' ? ` · ${t('Active')}` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : <h2 className="truncate font-semibold">{sprint.name}</h2>}
                                <span className={cn('shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide', sprint.status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-100' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200')}>
                                    {sprint.status === 'active' ? t('Active') : t('Planned')}
                                </span>
                                <div className="ml-auto flex shrink-0 items-center gap-1">
                                    {canCreateSprint && (
                                        <Button size="sm" variant="ghost" className="h-7 px-2" onClick={openCreateSprint}>
                                            <Plus className="h-3.5 w-3.5" />
                                            {t('New sprint')}
                                        </Button>
                                    )}
                                    <Button asChild size="sm" variant="ghost" className="h-7 px-2">
                                        <Link href={`${route('sprints.index', project.id)}#sprint-${sprint.id}`}>
                                            {t('Open')}
                                            <ArrowRight className="h-3.5 w-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-2 pl-6 text-xs text-muted-foreground">
                                <span>{sprintDateRange(sprint)}</span>
                                {sprint.status === 'active' && sprint.days_left !== null && <span>· {daysLeftLabel(t, sprint.days_left)}</span>}
                                <span>·</span>
                                {count(sprint.task_count, sprint.points)}
                            </div>
                        </header>
                        <div className="flex-1 overflow-y-auto">
                            {sprintTasks.map(task => (
                                <MovableTaskRow
                                    key={task.id}
                                    task={task}
                                    bucket={sprint.id}
                                    canMove={canMove}
                                    destinations={destinationsFor(sprint.id)}
                                    onOpen={openTask}
                                    quickMove={{ to: 'backlog', label: t('Back to backlog') }}
                                    layout="stacked"
                                />
                            ))}
                            {canMove ? (
                                <div className={cn('m-3 rounded-md border-2 border-dashed px-4 py-6 text-center text-sm text-muted-foreground', dropTarget === sprint.id && 'border-primary text-primary')}>
                                    {sprintTasks.length === 0
                                        ? t('Drag items here from the backlog, or use the + on an item.')
                                        : t('Drop more items here.')}
                                </div>
                            ) : sprintTasks.length === 0 && (
                                <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('Nothing planned into this sprint yet.')}</p>
                            )}
                        </div>
                    </section>
                ) : (
                    <section className={cn(PANE, 'items-center justify-center border-dashed p-6 text-center text-sm text-muted-foreground')}>
                        <Rocket className="mb-2 h-6 w-6" />
                        {canCreateSprint ? (
                            <>
                                <p>{t('No open sprint to plan into.')}</p>
                                <Button size="sm" className="mt-3" onClick={openCreateSprint}>
                                    <Plus className="h-4 w-4" />
                                    {t('Create sprint')}
                                </Button>
                            </>
                        ) : (
                            <p>{t('No sprint is planned yet. Your project manager creates sprints.')}</p>
                        )}
                    </section>
                )}
            </div>

            {sprintForm && (
                <SprintFormDialog key={sprintForm.key} open onClose={() => setSprintForm(null)} projectId={project.id} initial={sprintForm.initial} />
            )}
            {taskDialog}
        </ProjectShell>
    );
}
