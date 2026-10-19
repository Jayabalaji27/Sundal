import { useState, type DragEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { CheckCircle2 } from 'lucide-react';
import { AgileTask, AssigneeAvatar, EpicChip, PointsBadge, PriorityIcon, SeverityBadge, TypeIcon, isPastDue, shortDate } from '@/components/tasks/agile-bits';
import { cn } from '@/lib/utils';

export interface KanbanStage {
    id: number;
    name: string;
    color: string;
    is_completed?: boolean;
}

/**
 * Task stages as columns with draggable cards (HTML5 drag and drop, like the
 * Tasks page). The caller owns the data: onMove fires when a card is dropped
 * on another column, onOpen when a card is clicked.
 *
 * "detail" cards show priority, severity, points and the due date; "plain"
 * cards only the type, title and owner, so more fit on screen. With
 * showProject (the Tasks page, many projects at once) detail cards also name
 * the project.
 */
export function KanbanBoard({ stages, tasks, canMove, onMove, onOpen, variant = 'detail', showProject = false }: {
    stages: KanbanStage[];
    tasks: AgileTask[];
    canMove: boolean;
    onMove: (taskId: number, stageId: number) => void;
    onOpen: (taskId: number) => void;
    variant?: 'detail' | 'plain';
    showProject?: boolean;
}) {
    const { t } = useTranslation();
    const [dragging, setDragging] = useState<number | null>(null);
    const [over, setOver] = useState<number | null>(null);

    const onDrop = (event: DragEvent, stageId: number) => {
        event.preventDefault();
        setOver(null);
        const taskId = Number(event.dataTransfer.getData('text/plain'));
        const task = tasks.find(item => item.id === taskId);
        if (task && task.task_stage_id !== stageId) onMove(taskId, stageId);
    };

    return (
        <div className="flex gap-3 overflow-x-auto pb-3" style={{ minHeight: 'calc(100vh - 340px)' }}>
            {stages.map(stage => {
                const columnTasks = tasks.filter(task => task.task_stage_id === stage.id);
                const points = columnTasks.reduce((sum, task) => sum + (task.story_points || 0), 0);

                return (
                    <section
                        key={stage.id}
                        aria-label={stage.name}
                        className={cn('flex w-72 shrink-0 flex-col rounded-lg bg-muted/40 transition-colors', over === stage.id && 'bg-primary/10 ring-2 ring-primary/40')}
                        onDragOver={canMove ? (event) => { event.preventDefault(); if (over !== stage.id) setOver(stage.id); } : undefined}
                        onDragLeave={canMove ? (event) => { if (!event.currentTarget.contains(event.relatedTarget as Node)) setOver(null); } : undefined}
                        onDrop={canMove ? (event) => onDrop(event, stage.id) : undefined}
                    >
                        <header className="flex items-center gap-2 px-3 pb-2 pt-3">
                            <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: stage.color || '#6366f1' }} />
                            <span className="truncate text-xs font-semibold uppercase tracking-wide text-muted-foreground">{stage.name}</span>
                            {stage.is_completed && <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-500" aria-label={t('Done column')} />}
                            <span className="ml-auto text-xs tabular-nums text-muted-foreground">
                                {columnTasks.length}
                                {points > 0 && <span title={t('Story points')}> · {points} {t('pts')}</span>}
                            </span>
                        </header>

                        <div className="flex flex-1 flex-col gap-2 px-2 pb-2">
                            {columnTasks.map(task => (
                                <article
                                    key={task.id}
                                    draggable={canMove}
                                    onDragStart={canMove ? (event) => { event.dataTransfer.setData('text/plain', String(task.id)); event.dataTransfer.effectAllowed = 'move'; setDragging(task.id); } : undefined}
                                    onDragEnd={() => setDragging(null)}
                                    onClick={() => onOpen(task.id)}
                                    className={cn(
                                        'cursor-pointer rounded-md border bg-card text-sm shadow-sm transition hover:border-primary/40 hover:shadow',
                                        variant === 'plain' ? 'px-2.5 py-2' : 'p-3',
                                        canMove && 'active:cursor-grabbing',
                                        dragging === task.id && 'opacity-50',
                                    )}
                                >
                                    {variant === 'plain' ? (
                                        <div className="flex items-start gap-2">
                                            <TypeIcon type={task.type} className="mt-0.5" />
                                            <p className={cn('line-clamp-2 flex-1 leading-snug', task.is_completed && 'text-muted-foreground line-through')}>{task.title}</p>
                                            <AssigneeAvatar assignee={task.assignee} size="h-5 w-5" />
                                        </div>
                                    ) : (<>
                                    {task.epic && <EpicChip epic={task.epic} className="mb-1.5" />}
                                    <p className={cn('line-clamp-3 leading-snug', task.is_completed && 'text-muted-foreground line-through')}>{task.title}</p>
                                    {showProject && task.project && <p className="mt-1 truncate text-xs text-muted-foreground">{task.project.title}</p>}
                                    <div className="mt-2.5 flex items-center gap-2">
                                        <TypeIcon type={task.type} />
                                        <PriorityIcon priority={task.priority} />
                                        {task.type === 'bug' && <SeverityBadge severity={task.severity} />}
                                        <PointsBadge points={task.story_points} />
                                        {task.end_date && (
                                            <span className={cn('text-xs tabular-nums', isPastDue(task) ? 'font-medium text-red-600' : 'text-muted-foreground')}>
                                                {shortDate(task.end_date)}
                                            </span>
                                        )}
                                        <span className="ml-auto"><AssigneeAvatar assignee={task.assignee} /></span>
                                    </div>
                                    </>)}
                                </article>
                            ))}
                            {columnTasks.length === 0 && (
                                <div className="rounded-md border border-dashed px-3 py-6 text-center text-xs text-muted-foreground">
                                    {canMove ? t('Drop a card here') : t('Nothing here')}
                                </div>
                            )}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}
