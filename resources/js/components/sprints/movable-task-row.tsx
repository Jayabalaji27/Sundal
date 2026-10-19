import { type DragEvent } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowRightLeft, CornerUpLeft, GripVertical, Inbox, Plus, Rocket } from 'lucide-react';
import { AgileTask, AssigneeAvatar, EpicChip, PointsBadge, PriorityIcon, SeverityBadge, StageChip, TypeIcon, isPastDue, shortDate } from '@/components/tasks/agile-bits';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/** Where a task sits: a sprint id or the backlog. */
export type Bucket = number | 'backlog';

const DRAG_TYPE = 'text/plain';

/** Moves a task between sprints and the backlog (a task sits in one open sprint at a time). */
export function moveTask(taskId: number, from: Bucket, to: Bucket) {
    if (from === to) return;
    // Keep the page's own state (the sprint being planned, search, filters).
    const options = { preserveScroll: true, preserveState: true };
    if (to === 'backlog') {
        router.delete(route('sprints.remove-task', from as number), { data: { task_id: taskId }, ...options });
    } else {
        // The server takes the task out of any other open sprint.
        router.post(route('sprints.add-task', to), { task_id: taskId }, options);
    }
}

/** Reads a dropped row; null when the drop came from somewhere else. */
export function readDroppedTask(event: DragEvent): { taskId: number; from: Bucket } | null {
    try {
        const { taskId, from } = JSON.parse(event.dataTransfer.getData(DRAG_TYPE));
        return taskId ? { taskId: Number(taskId), from } : null;
    } catch {
        return null;
    }
}

/**
 * One task on the Backlog and Sprints pages: drag it onto a sprint (or the
 * backlog), use its one-click button (quickMove: "Add to Sprint 3" or "Back
 * to backlog"), or pick another destination from its "Move to" menu.
 *
 * "line" fits everything on one row (the Sprints page); "stacked" puts the
 * title on top and the details under it, for the half-width Backlog panes.
 */
export function MovableTaskRow({ task, bucket, canMove, destinations, onOpen, quickMove, layout = 'line' }: {
    task: AgileTask;
    bucket: Bucket;
    canMove: boolean;
    destinations: { bucket: Bucket; label: string }[];
    onOpen: (taskId: number) => void;
    quickMove?: { to: Bucket; label: string };
    layout?: 'line' | 'stacked';
}) {
    const { t } = useTranslation();
    // The menu only lists places the one-click button doesn't already cover.
    const otherDestinations = destinations.filter(dest => dest.bucket !== quickMove?.to);
    const stacked = layout === 'stacked';

    const dragProps = {
        draggable: canMove,
        onDragStart: canMove ? (event: DragEvent) => {
            event.dataTransfer.setData(DRAG_TYPE, JSON.stringify({ taskId: task.id, from: bucket }));
            event.dataTransfer.effectAllowed = 'move';
        } : undefined,
    };

    const title = (
        <button
            type="button"
            onClick={() => onOpen(task.id)}
            title={task.title}
            className={cn(
                'min-w-0 flex-1 text-left hover:text-primary hover:underline',
                stacked ? 'line-clamp-2 leading-snug' : 'truncate',
                task.is_completed && 'text-muted-foreground line-through',
            )}
        >
            {task.title}
        </button>
    );

    const quickButton = canMove && quickMove && (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn('h-7 w-7 shrink-0', quickMove.to === 'backlog' ? 'text-muted-foreground' : 'text-primary hover:text-primary')}
                    aria-label={quickMove.label}
                    onClick={() => moveTask(task.id, bucket, quickMove.to)}
                >
                    {quickMove.to === 'backlog' ? <CornerUpLeft className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{quickMove.label}</TooltipContent>
        </Tooltip>
    );

    const moveMenu = canMove && otherDestinations.length > 0 && (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    // Stacked rows keep it out of sight until the row is hovered or focused.
                    className={cn('h-7 w-7 shrink-0 text-muted-foreground', stacked && 'opacity-0 focus-visible:opacity-100 group-hover:opacity-100 data-[state=open]:opacity-100')}
                    aria-label={t('Move task')}
                >
                    <ArrowRightLeft className="h-3.5 w-3.5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">{t('Move to')}</DropdownMenuLabel>
                {otherDestinations.map(dest => (
                    <DropdownMenuItem key={String(dest.bucket)} onClick={() => moveTask(task.id, bucket, dest.bucket)}>
                        {dest.bucket === 'backlog' ? <Inbox className="mr-2 h-4 w-4" /> : <Rocket className="mr-2 h-4 w-4" />}
                        {dest.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );

    const due = task.end_date && (
        <span className={cn('text-xs tabular-nums', isPastDue(task) ? 'font-medium text-red-600' : 'text-muted-foreground')}>{shortDate(task.end_date)}</span>
    );

    if (stacked) {
        return (
            <div {...dragProps} className={cn('group border-b bg-card px-3 py-2.5 text-sm last:border-b-0 hover:bg-muted/40', canMove && 'cursor-grab active:cursor-grabbing')}>
                <div className="flex items-start gap-2">
                    <TypeIcon type={task.type} className="mt-0.5" />
                    {title}
                    <div className="-my-1 flex shrink-0 items-center">
                        {moveMenu}
                        {quickButton}
                    </div>
                </div>
                <div className="mt-1.5 flex flex-wrap items-center gap-2 pl-6">
                    <PriorityIcon priority={task.priority} />
                    {task.type === 'bug' && <SeverityBadge severity={task.severity} />}
                    <EpicChip epic={task.epic} />
                    <StageChip stage={task.stage} />
                    {due}
                    <PointsBadge points={task.story_points} />
                    <span className="ml-auto"><AssigneeAvatar assignee={task.assignee} size="h-5 w-5" /></span>
                </div>
            </div>
        );
    }

    return (
        <div {...dragProps} className={cn('group flex items-center gap-2 border-b bg-card px-3 py-2 text-sm last:border-b-0 hover:bg-muted/40', canMove && 'cursor-grab active:cursor-grabbing')}>
            {canMove && <GripVertical className="h-4 w-4 shrink-0 text-muted-foreground/40 group-hover:text-muted-foreground" />}
            <TypeIcon type={task.type} />
            <PriorityIcon priority={task.priority} />
            {title}
            <div className="hidden items-center gap-2 sm:flex">
                {task.type === 'bug' && <SeverityBadge severity={task.severity} />}
                {due}
                <StageChip stage={task.stage} />
            </div>
            <PointsBadge points={task.story_points} />
            <AssigneeAvatar assignee={task.assignee} />
            {quickButton}
            {moveMenu}
        </div>
    );
}
