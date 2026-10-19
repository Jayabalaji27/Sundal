import { useEffect, useState, type DragEvent } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BarChart3, CheckCircle2, ChevronDown, ChevronRight, Columns3, Inbox, MoreHorizontal, Pencil, Play, Plus, Rocket, Trash2 } from 'lucide-react';
import { ProjectShell, daysLeftLabel } from '@/components/projects/project-shell';
import { Bucket, MovableTaskRow, moveTask, readDroppedTask } from '@/components/sprints/movable-task-row';
import { SprintFields, SprintFormDialog, SprintFormValues, addDays, newSprintValues, sprintDateRange, toIsoDate } from '@/components/sprints/sprint-form';
import { AgileSprint, AgileTask } from '@/components/tasks/agile-bits';
import { useTaskDetail } from '@/components/tasks/use-task-detail';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { hasPermission } from '@/utils/authorization';
import { cn } from '@/lib/utils';

type OpenSprint = AgileSprint & { tasks: AgileTask[] };

/**
 * Project Sprints: the running sprint, the planned ones and the finished ones.
 * Managers create, start and complete sprints here; the work itself is picked
 * on the Backlog page.
 */
export default function SprintsIndex() {
    const { t } = useTranslation();
    const { auth, project, sprints = [], backlogSummary = { tasks: 0, points: 0 }, completedSprints = [], velocity, nextSprintName, stages = [], taskForm = null } = usePage().props as any;
    const permissions: string[] = auth?.permissions || [];
    const can = (permission: string) => hasPermission(permissions, permission);
    const canCreate = can('sprint_create');
    const canUpdate = can('sprint_update');
    const canDelete = can('sprint_delete');
    const canMove = can('sprint_manage_tasks');

    const openSprints = sprints as OpenSprint[];
    const activeSprint = openSprints.find(sprint => sprint.status === 'active');
    const plannedSprints = openSprints.filter(sprint => sprint.status === 'planning');

    const { openTask, taskDialog } = useTaskDetail({ members: taskForm?.members || [], stages, milestones: taskForm?.projects?.[0]?.milestones || [] });

    // Create / edit (the dialog keeps its own fields; a new key opens it fresh).
    const [sprintForm, setSprintForm] = useState<{ key: number; sprint: AgileSprint | null; initial: SprintFormValues } | null>(null);
    const openCreate = () => setSprintForm({ key: Date.now(), sprint: null, initial: newSprintValues(openSprints, nextSprintName || t('Sprint')) });
    const openEdit = (sprint: AgileSprint) => setSprintForm({
        key: Date.now(), sprint, initial: { name: sprint.name, goal: sprint.goal || '', start_date: sprint.start_date || '', end_date: sprint.end_date || '' },
    });

    const [starting, setStarting] = useState<AgileSprint | null>(null);
    const [startForm, setStartForm] = useState<SprintFormValues>({ name: '', goal: '', start_date: '', end_date: '' });
    const [completing, setCompleting] = useState<OpenSprint | null>(null);
    const [moveTo, setMoveTo] = useState<string>('backlog');
    const [deleting, setDeleting] = useState<AgileSprint | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [collapsed, setCollapsed] = useState<Record<number, boolean>>({});
    const [dropTarget, setDropTarget] = useState<number | null>(null);

    const openStart = (sprint: AgileSprint) => {
        const start = sprint.start_date || toIsoDate(new Date());
        setStartForm({ name: sprint.name, goal: sprint.goal || '', start_date: start, end_date: sprint.end_date || addDays(start, 13) });
        setErrors({});
        setStarting(sprint);
    };
    const openComplete = (sprint: OpenSprint) => {
        setMoveTo(plannedSprints[0] ? String(plannedSprints[0].id) : 'backlog');
        setCompleting(sprint);
    };

    // "Create > Sprint" from anywhere lands here with ?create=1.
    useEffect(() => {
        if (canCreate && new URLSearchParams(window.location.search).get('create')) {
            openCreate();
            window.history.replaceState(window.history.state, '', route('sprints.index', project.id));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const submitOptions = (onDone: () => void) => ({
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
        onSuccess: onDone,
        onError: (e: Record<string, string>) => setErrors(e),
    });
    const startSprint = () => starting && router.post(route('sprints.start', starting.id),
        { start_date: startForm.start_date, end_date: startForm.end_date, goal: startForm.goal }, submitOptions(() => setStarting(null)));
    const completeSprint = () => completing && router.post(route('sprints.complete', completing.id), { move_to: moveTo }, submitOptions(() => setCompleting(null)));
    const deleteSprint = () => deleting && router.delete(route('sprints.destroy', deleting.id), submitOptions(() => setDeleting(null)));

    // Tasks can be dragged between the open sprints (and sent back with "Move to > Backlog").
    const dropProps = (sprintId: number) => canMove ? {
        onDragOver: (event: DragEvent) => { event.preventDefault(); if (dropTarget !== sprintId) setDropTarget(sprintId); },
        onDragLeave: (event: DragEvent) => { if (!event.currentTarget.contains(event.relatedTarget as Node)) setDropTarget(null); },
        onDrop: (event: DragEvent) => {
            event.preventDefault();
            setDropTarget(null);
            const dropped = readDroppedTask(event);
            if (dropped) moveTask(dropped.taskId, dropped.from, sprintId);
        },
    } : {};

    const destinationsFor = (bucket: Bucket) => [
        ...openSprints.filter(sprint => sprint.id !== bucket).map(sprint => ({ bucket: sprint.id as Bucket, label: sprint.name })),
        { bucket: 'backlog' as Bucket, label: t('Backlog') },
    ];

    const renderSprint = (sprint: OpenSprint) => {
        const isActive = sprint.status === 'active';
        const left = daysLeftLabel(t, sprint.days_left);
        const donePct = sprint.task_count > 0 ? Math.round((sprint.done_count / sprint.task_count) * 100) : 0;
        const isCollapsed = !!collapsed[sprint.id];

        return (
            <section
                key={sprint.id}
                id={`sprint-${sprint.id}`}
                {...dropProps(sprint.id)}
                className={cn('overflow-hidden rounded-lg border bg-card transition-shadow', isActive && 'border-emerald-300', dropTarget === sprint.id && 'ring-2 ring-primary/50')}
                aria-label={sprint.name}
            >
                <header className={cn('flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5', isActive ? 'bg-emerald-50/60 dark:bg-emerald-950/20' : 'bg-muted/30')}>
                    <button type="button" onClick={() => setCollapsed(prev => ({ ...prev, [sprint.id]: !isCollapsed }))} className="flex min-w-0 items-center gap-1.5 text-left" aria-expanded={!isCollapsed}>
                        {isCollapsed ? <ChevronRight className="h-4 w-4 shrink-0" /> : <ChevronDown className="h-4 w-4 shrink-0" />}
                        <span className="truncate font-semibold">{sprint.name}</span>
                    </button>
                    <span className={cn('rounded px-1.5 py-0.5 text-[11px] font-medium uppercase tracking-wide', isActive ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-100' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200')}>
                        {isActive ? t('Active') : t('Planned')}
                    </span>
                    {sprintDateRange(sprint) && <span className="text-xs text-muted-foreground">{sprintDateRange(sprint)}</span>}
                    {isActive && left && <span className={cn('text-xs', (sprint.days_left ?? 0) < 0 ? 'font-medium text-red-600' : 'text-muted-foreground')}>· {left}</span>}
                    <span className="text-xs text-muted-foreground">
                        · {t(':count tasks').replace(':count', String(sprint.task_count))}
                        {sprint.points > 0 && <> · {t(':count pts').replace(':count', String(sprint.points))}</>}
                    </span>

                    <div className="ml-auto flex items-center gap-1.5">
                        {isActive && sprint.task_count > 0 && (
                            <div className="mr-1 hidden w-28 items-center gap-2 md:flex" title={t(':done of :total done').replace(':done', String(sprint.done_count)).replace(':total', String(sprint.task_count))}>
                                <Progress value={donePct} className="h-1.5" />
                                <span className="text-xs tabular-nums text-muted-foreground">{donePct}%</span>
                            </div>
                        )}
                        {canUpdate && isActive && (
                            <Button size="sm" variant="outline" className="h-8" onClick={() => openComplete(sprint)}>
                                <CheckCircle2 className="h-4 w-4" />
                                {t('Complete sprint')}
                            </Button>
                        )}
                        {canUpdate && !isActive && (
                            activeSprint ? (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        {/* span keeps the tooltip working on a disabled button */}
                                        <span tabIndex={0}>
                                            <Button size="sm" variant="outline" className="h-8" disabled>
                                                <Play className="h-4 w-4" />
                                                {t('Start sprint')}
                                            </Button>
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>{t('Complete :name first').replace(':name', activeSprint.name)}</TooltipContent>
                                </Tooltip>
                            ) : (
                                <Button size="sm" className="h-8" onClick={() => openStart(sprint)}>
                                    <Play className="h-4 w-4" />
                                    {t('Start sprint')}
                                </Button>
                            )
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" className="h-8 w-8" aria-label={t('Sprint actions')}>
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => router.get(route('sprints.show', sprint.id))}>
                                    <BarChart3 className="mr-2 h-4 w-4" />{t('Sprint report')}
                                </DropdownMenuItem>
                                {isActive && (
                                    <DropdownMenuItem onClick={() => router.get(route('projects.board', project.id))}>
                                        <Columns3 className="mr-2 h-4 w-4" />{t('Open board')}
                                    </DropdownMenuItem>
                                )}
                                {canUpdate && (
                                    <DropdownMenuItem onClick={() => openEdit(sprint)}>
                                        <Pencil className="mr-2 h-4 w-4" />{t('Edit sprint')}
                                    </DropdownMenuItem>
                                )}
                                {canDelete && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem onClick={() => setDeleting(sprint)} className="text-red-600 focus:text-red-600">
                                            <Trash2 className="mr-2 h-4 w-4" />{t('Delete sprint')}
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    {sprint.goal && !isCollapsed && (
                        <p className="basis-full pl-6 text-xs text-muted-foreground">
                            <span className="font-medium text-foreground">{t('Goal')}:</span> {sprint.goal}
                        </p>
                    )}
                </header>

                {!isCollapsed && (
                    sprint.tasks.length > 0 ? (
                        <div className="border-t">
                            {sprint.tasks.map(task => (
                                <MovableTaskRow key={task.id} task={task} bucket={sprint.id} canMove={canMove} destinations={destinationsFor(sprint.id)} onOpen={openTask} />
                            ))}
                        </div>
                    ) : (
                        <div className="m-3 rounded-md border-2 border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                            {t('No tasks in this sprint yet.')}{' '}
                            {canMove && (
                                <Link href={route('projects.backlog', project.id)} className="font-medium text-primary hover:underline">
                                    {t('Add them from the backlog')}
                                </Link>
                            )}
                        </div>
                    )
                )}
            </section>
        );
    };

    const unfinished = completing ? completing.tasks.filter(task => !task.is_completed) : [];

    return (
        <ProjectShell project={project} active="sprints" taskForm={taskForm} onCreateSprint={canCreate ? openCreate : undefined}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {activeSprint
                        ? t(':name is running.').replace(':name', activeSprint.name)
                        : plannedSprints.length > 0 ? t('No sprint is running. Start the next one when the team is ready.') : t('Create a sprint, fill it from the backlog, then start it.')}
                    {velocity !== null && velocity !== undefined && (
                        <span className="ml-2 inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs" title={t('Average story points finished per sprint (last 3 sprints)')}>
                            {t('Velocity')}: {velocity} {t('pts')}
                        </span>
                    )}
                </p>
                <div className="flex items-center gap-2">
                    <Button asChild size="sm" variant="outline">
                        <Link href={route('projects.backlog', project.id)}>
                            <Inbox className="h-4 w-4" />
                            {t('Backlog')}
                            <span className="rounded-full bg-muted px-1.5 text-xs tabular-nums">{backlogSummary.tasks}</span>
                        </Link>
                    </Button>
                    {canCreate && (
                        <Button size="sm" onClick={openCreate}>
                            <Plus className="h-4 w-4" />
                            {t('Create sprint')}
                        </Button>
                    )}
                </div>
            </div>

            {openSprints.length === 0 && (
                <div className="flex flex-col items-center rounded-lg border-2 border-dashed px-6 py-10 text-center">
                    <Rocket className="mb-3 h-9 w-9 text-muted-foreground" />
                    <p className="font-medium">{t('No sprint planned yet')}</p>
                    <p className="mt-1 max-w-md text-sm text-muted-foreground">
                        {canCreate
                            ? t('A sprint is a short, fixed period (usually 2 weeks) where the team finishes a chosen set of tasks.')
                            : t('Your project manager plans sprints here.')}
                    </p>
                </div>
            )}

            {openSprints.map(sprint => renderSprint(sprint))}

            {/* Finished sprints: what each one committed and delivered */}
            {completedSprints.length > 0 && (
                <section className="overflow-hidden rounded-lg border bg-card" aria-label={t('Completed sprints')}>
                    <header className="flex items-center gap-2 border-b bg-muted/30 px-3 py-2.5">
                        <CheckCircle2 className="h-4 w-4 text-violet-500" />
                        <span className="font-semibold">{t('Completed sprints')}</span>
                        <span className="text-xs text-muted-foreground">({completedSprints.length})</span>
                    </header>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs text-muted-foreground">
                                <tr className="border-b">
                                    <th className="px-4 py-2 font-medium">{t('Sprint')}</th>
                                    <th className="px-4 py-2 font-medium">{t('Dates')}</th>
                                    <th className="px-4 py-2 text-right font-medium">{t('Committed')}</th>
                                    <th className="px-4 py-2 text-right font-medium">{t('Completed')}</th>
                                    <th className="px-4 py-2 text-right font-medium">{t('Points done')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(completedSprints as AgileSprint[]).map(sprint => (
                                    <tr key={sprint.id} className="cursor-pointer border-b last:border-b-0 hover:bg-muted/40" onClick={() => router.get(route('sprints.show', sprint.id))}>
                                        <td className="px-4 py-2 font-medium">
                                            <Link href={route('sprints.show', sprint.id)} className="hover:text-primary hover:underline" onClick={(e) => e.stopPropagation()}>{sprint.name}</Link>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2 text-muted-foreground">{sprintDateRange(sprint)}</td>
                                        <td className="px-4 py-2 text-right tabular-nums">{sprint.task_count}</td>
                                        <td className="px-4 py-2 text-right tabular-nums">{sprint.done_count}</td>
                                        <td className="px-4 py-2 text-right tabular-nums">{sprint.points > 0 ? `${sprint.done_points} / ${sprint.points}` : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {sprintForm && (
                <SprintFormDialog
                    key={sprintForm.key}
                    open
                    onClose={() => setSprintForm(null)}
                    projectId={project.id}
                    sprint={sprintForm.sprint}
                    initial={sprintForm.initial}
                />
            )}

            {/* Start sprint */}
            <Dialog open={!!starting} onOpenChange={(open) => !open && setStarting(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('Start :name').replace(':name', starting?.name || '')}</DialogTitle>
                        <DialogDescription>
                            {t(':count tasks will be on the board. Confirm the dates and goal.').replace(':count', String(starting?.task_count ?? 0))}
                        </DialogDescription>
                    </DialogHeader>
                    <SprintFields form={startForm} setForm={setStartForm} errors={errors} hideName />
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setStarting(null)}>{t('Cancel')}</Button>
                        <Button onClick={startSprint} disabled={busy}>
                            <Play className="h-4 w-4" />
                            {t('Start sprint')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Complete sprint */}
            <Dialog open={!!completing} onOpenChange={(open) => !open && setCompleting(null)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('Complete :name').replace(':name', completing?.name || '')}</DialogTitle>
                        <DialogDescription>
                            {t(':done of :total tasks are done.').replace(':done', String(completing?.done_count ?? 0)).replace(':total', String(completing?.task_count ?? 0))}
                            {(completing?.points ?? 0) > 0 && ' ' + t(':done of :total points.').replace(':done', String(completing?.done_points ?? 0)).replace(':total', String(completing?.points ?? 0))}
                        </DialogDescription>
                    </DialogHeader>
                    {unfinished.length > 0 ? (
                        <div className="space-y-2">
                            <Label>{t('Move the :count unfinished tasks to').replace(':count', String(unfinished.length))}</Label>
                            <div className="space-y-1.5">
                                {[
                                    ...plannedSprints.map(sprint => ({ value: String(sprint.id), label: sprint.name })),
                                    { value: 'new', label: t('A new sprint') },
                                    { value: 'backlog', label: t('The backlog') },
                                ].map(option => (
                                    <label key={option.value} className={cn('flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm', moveTo === option.value && 'border-primary bg-primary/5')}>
                                        <input type="radio" name="move_to" value={option.value} checked={moveTo === option.value} onChange={() => setMoveTo(option.value)} className="accent-[var(--primary)]" />
                                        {option.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">{t('Every task is done. Nice work!')}</p>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCompleting(null)}>{t('Cancel')}</Button>
                        <Button onClick={completeSprint} disabled={busy}>
                            <CheckCircle2 className="h-4 w-4" />
                            {t('Complete sprint')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EnhancedDeleteModal
                isOpen={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={deleteSprint}
                itemName={deleting?.name || ''}
                entityName={t('sprint')}
                warningMessage={t('Its tasks go back to the backlog. The tasks themselves are not deleted.')}
                isSubmitting={busy}
            />

            {taskDialog}
        </ProjectShell>
    );
}
