import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Check, CheckCircle2, ChevronDown, Columns3, LayoutGrid, ListTodo, Rocket, Rows3, Search, Sheet as SheetIcon, UserRound } from 'lucide-react';
import { ProjectShell, daysLeftLabel } from '@/components/projects/project-shell';
import { AgileSprint, AgileTask, TypeIcon } from '@/components/tasks/agile-bits';
import { KanbanBoard, KanbanStage } from '@/components/tasks/kanban-board';
import { BoardSheet } from '@/components/tasks/board-sheet';
import { useTaskDetail } from '@/components/tasks/use-task-detail';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { hasPermission } from '@/utils/authorization';
import { cn } from '@/lib/utils';

type BoardView = 'detail' | 'plain' | 'sheet';
const BOARD_VIEWS: BoardView[] = ['detail', 'plain', 'sheet'];
const VIEW_KEY = 'sundal.board.view';

/** The view each person last picked (a per-browser convenience; the board works without it). */
const savedView = (): BoardView => {
    try {
        const value = window.localStorage.getItem(VIEW_KEY) as BoardView | null;
        return value && BOARD_VIEWS.includes(value) ? value : 'detail';
    } catch {
        return 'detail';
    }
};

/**
 * Project Board: the active sprint's tasks and bugs by stage (or every item
 * of the project). Dragging a card changes its stage. Three views: Detail
 * cards, Plain cards (title and owner only) and Sheet (a sortable table).
 */
export default function ProjectBoard() {
    const { t } = useTranslation();
    const { auth, project, stages = [], tasks = [], activeSprint, hasPlannedSprints, filters = {}, taskForm = null } = usePage().props as unknown as {
        auth: any; project: any; stages: KanbanStage[]; tasks: AgileTask[]; activeSprint: AgileSprint | null;
        hasPlannedSprints: boolean; filters: { scope?: string; mine?: boolean; search?: string; type?: string }; taskForm: any;
    };
    const permissions: string[] = auth?.permissions || [];
    const canMove = hasPermission(permissions, 'task_change_status');

    // Cards move at once; the server's answer (or a refusal) resets them from props.
    const [cards, setCards] = useState<AgileTask[]>(tasks);
    useEffect(() => setCards(tasks), [tasks]);

    const [search, setSearch] = useState(filters.search || '');
    const [view, setView] = useState<BoardView>(savedView);
    const chooseView = (next: BoardView) => {
        setView(next);
        try { window.localStorage.setItem(VIEW_KEY, next); } catch { /* private window: keep it for this visit */ }
    };
    const views: Record<BoardView, { label: string; hint: string; icon: typeof Rows3 }> = {
        detail: { label: t('Detail view'), hint: t('Cards with priority, severity, points and due date'), icon: LayoutGrid },
        plain: { label: t('Plain view'), hint: t('Compact cards: title and owner'), icon: Rows3 },
        sheet: { label: t('Sheet view'), hint: t('A table you can sort, and change stages in'), icon: SheetIcon },
    };
    const CurrentViewIcon = views[view].icon;
    const { openTask, taskDialog } = useTaskDetail({ members: taskForm?.members || [], stages, milestones: taskForm?.projects?.[0]?.milestones || [] });

    const reload = (changes: Record<string, unknown>) => {
        const params: Record<string, unknown> = {
            scope: filters.scope, mine: filters.mine ? 1 : undefined, search: search || undefined,
            type: filters.type && filters.type !== 'all' ? filters.type : undefined, ...changes,
        };
        Object.keys(params).forEach(key => (params[key] === undefined || params[key] === '' || params[key] === false) && delete params[key]);
        router.get(route('projects.board', project.id), params as any, { preserveState: true, preserveScroll: true, replace: true });
    };

    // Search as you type.
    const firstRender = useRef(true);
    useEffect(() => {
        if (firstRender.current) { firstRender.current = false; return; }
        if (search === (filters.search || '')) return;
        const timeout = setTimeout(() => reload({ search: search || undefined }), 350);
        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const moveCard = (taskId: number, stageId: number) => {
        const stage = stages.find(item => item.id === stageId);
        setCards(prev => prev.map(card => card.id === taskId
            ? { ...card, task_stage_id: stageId, stage: stage ? { id: stage.id, name: stage.name, color: stage.color } : card.stage, is_completed: !!stage?.is_completed }
            : card));
        router.put(route('tasks.change-stage', taskId), { task_stage_id: stageId }, { preserveScroll: true, preserveState: true });
    };

    const showingSprint = filters.scope === 'sprint' && activeSprint;
    // Tasks and bugs share the board; this narrows it to one kind.
    const type = filters.type === 'task' || filters.type === 'bug' ? filters.type : 'all';
    const left = activeSprint ? daysLeftLabel(t, activeSprint.days_left) : null;
    const donePct = activeSprint && activeSprint.task_count > 0 ? Math.round((activeSprint.done_count / activeSprint.task_count) * 100) : 0;

    const segment = (active: boolean) => cn('inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-sm transition-colors', active ? 'bg-background font-medium shadow-sm' : 'text-muted-foreground hover:text-foreground');

    return (
        <ProjectShell project={project} active="board" taskForm={taskForm}>
            {/* Sprint banner */}
            {activeSprint ? (
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/20">
                    <Rocket className="h-5 w-5 text-emerald-600" />
                    <div className="min-w-0">
                        <p className="font-semibold">{activeSprint.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {left}
                            {activeSprint.goal && <> · {activeSprint.goal}</>}
                        </p>
                    </div>
                    <div className="flex min-w-40 flex-1 items-center gap-2 sm:max-w-xs">
                        <Progress value={donePct} className="h-1.5" />
                        <span className="whitespace-nowrap text-xs tabular-nums text-muted-foreground">
                            {activeSprint.done_count}/{activeSprint.task_count} {t('done')}
                        </span>
                    </div>
                    {hasPermission(permissions, 'sprint_view_any') && (
                        <Button asChild size="sm" variant="outline" className="ml-auto">
                            <Link href={route('sprints.index', project.id)}>
                                <CheckCircle2 className="h-4 w-4" />
                                {hasPermission(permissions, 'sprint_update') ? t('Complete sprint') : t('View sprints')}
                            </Link>
                        </Button>
                    )}
                </div>
            ) : hasPermission(permissions, 'sprint_view_any') && (
                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-dashed px-4 py-3 text-sm text-muted-foreground">
                    <Rocket className="h-4 w-4" />
                    <span>
                        {hasPlannedSprints ? t('No sprint is running. Start the next one from Sprints to focus this board.') : t('No sprint is running. The board shows every task of the project.')}
                    </span>
                    <Link
                        href={hasPlannedSprints || !hasPermission(permissions, 'sprint_create') ? route('sprints.index', project.id) : route('sprints.index', { project: project.id, create: 1 })}
                        className="ml-auto font-medium text-primary hover:underline"
                    >
                        {hasPlannedSprints ? t('Go to sprints') : hasPermission(permissions, 'sprint_create') ? t('Plan a sprint') : t('View sprints')}
                    </Link>
                </div>
            )}

            {/* Toolbar */}
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative w-full sm:w-64">
                    <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('Search this board...')} className="h-9 pl-9" />
                </div>
                <Button
                    size="sm"
                    variant={filters.mine ? 'secondary' : 'outline'}
                    className="h-9"
                    onClick={() => reload({ mine: filters.mine ? undefined : 1 })}
                    aria-pressed={!!filters.mine}
                >
                    <UserRound className="h-4 w-4" />
                    {t('Only my tasks')}
                </Button>
                <div role="radiogroup" aria-label={t('Type')} className="inline-flex rounded-lg bg-muted p-0.5">
                    {([['all', t('All')], ['task', t('Tasks')], ['bug', t('Bugs')]] as const).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            role="radio"
                            aria-checked={type === value}
                            className={segment(type === value)}
                            onClick={() => reload({ type: value === 'all' ? undefined : value })}
                        >
                            {value !== 'all' && <TypeIcon type={value} className="h-3.5 w-3.5" />}
                            {label}
                        </button>
                    ))}
                </div>
                {activeSprint && (
                    <div className="inline-flex rounded-lg bg-muted p-0.5 sm:ml-auto">
                        <button type="button" className={segment(!!showingSprint)} onClick={() => reload({ scope: 'sprint' })}>
                            <Rocket className="h-3.5 w-3.5" />
                            {t('Active sprint')}
                        </button>
                        <button type="button" className={segment(!showingSprint)} onClick={() => reload({ scope: 'all' })}>
                            <ListTodo className="h-3.5 w-3.5" />
                            {t('Whole project')}
                        </button>
                    </div>
                )}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="sm" variant="outline" className={cn('h-9', !activeSprint && 'sm:ml-auto')} aria-label={t('Board view')}>
                            <CurrentViewIcon className="h-4 w-4" />
                            {views[view].label}
                            <ChevronDown className="h-3.5 w-3.5 opacity-70" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-72">
                        {BOARD_VIEWS.map(key => {
                            const option = views[key];
                            return (
                                <DropdownMenuItem key={key} onClick={() => chooseView(key)} className="items-start gap-3 py-2">
                                    <option.icon className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-medium">{option.label}</span>
                                        <span className="block text-xs text-muted-foreground">{option.hint}</span>
                                    </span>
                                    {view === key && <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />}
                                </DropdownMenuItem>
                            );
                        })}
                        {/* The columns are the workspace's stages (for tasks and bugs alike). */}
                        {hasPermission(permissions, 'task_manage_stages') && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild className="gap-3 py-2">
                                    <Link href={route('task-stages.index')}>
                                        <Columns3 className="h-4 w-4 shrink-0" />
                                        <span className="font-medium">{t('Manage stages')}</span>
                                    </Link>
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {stages.length === 0 ? (
                <div className="rounded-lg border-2 border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                    {t('This workspace has no task stages yet.')}
                </div>
            ) : (
                <>
                    {cards.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {showingSprint && type === 'all' && !filters.search && !filters.mine
                                ? t('This sprint has no tasks yet. Add some from the backlog.')
                                : showingSprint
                                ? <>{t('Nothing in this sprint matches.')} <button type="button" className="font-medium text-primary hover:underline" onClick={() => reload({ scope: 'all' })}>{t('Show the whole project')}</button></>
                                : type === 'bug' && !filters.search && !filters.mine ? t('No bugs here. Report one from Create.')
                                : filters.search || filters.mine || type !== 'all' ? t('No tasks match.') : t('No tasks in this project yet.')}
                        </p>
                    )}
                    {view === 'sheet' ? (
                        cards.length > 0 && <BoardSheet stages={stages} tasks={cards} canMove={canMove} onMove={moveCard} onOpen={openTask} />
                    ) : (
                        <KanbanBoard stages={stages} tasks={cards} canMove={canMove} onMove={moveCard} onOpen={openTask} variant={view} />
                    )}
                </>
            )}

            {taskDialog}
        </ProjectShell>
    );
}
