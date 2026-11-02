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
                        className={cn('flex w-72 shrink-0 flex-col rounded-xl bg-ds-neutral-bg/70 transition-colors', over === stage.id && 'bg-ds-surface-selected ring-2 ring-brand-500/40')}
                        onDragOver={canMove ? (event) => { event.preventDefault(); if (over !== stage.id) setOver(stage.id); } : undefined}
                        onDragLeave={canMove ? (event) => { if (!event.currentTarget.contains(event.relatedTarget as Node)) setOver(null); } : undefined}
                        onDrop={canMove ? (event) => onDrop(event, stage.id) : undefined}
                    >
                        <header className="flex items-center gap-2 px-3 pb-2 pt-3">
                            <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: stage.color || '#6366f1' }} />
                            <span className="truncate text-xs font-semibold uppercase tracking-wide text-ds-fg-secondary">{stage.name}</span>
                            {stage.is_completed && <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-ds-success" aria-label={t('Done column')} />}
                            <span className="ml-auto text-xs tabular-nums text-ds-fg-muted">
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
                                    // Cards open from the keyboard too (Enter or Space).
                                    role="button"
                                    tabIndex={0}
                                    aria-label={task.title}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); onOpen(task.id); }
                                    }}
                                    className={cn(
                                        'cursor-pointer rounded-lg border border-ds-border bg-ds-surface text-sm text-ds-fg shadow-ds-xs transition-[border-color,box-shadow] duration-150 hover:border-ds-border-strong hover:shadow-ds-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus',
                                        variant === 'plain' ? 'px-2.5 py-2' : 'p-3',
                                        canMove && 'active:cursor-grabbing',
                                        dragging === task.id && 'opacity-50',
                                    )}
                                >
                                    {variant === 'plain' ? (
                                        <div className="flex items-start gap-2">
                                            <TypeIcon type={task.type} className="mt-0.5" />
                                            <p className={cn('line-clamp-2 flex-1 leading-snug', task.is_completed && 'text-ds-fg-muted line-through')}>{task.title}</p>
                                            <AssigneeAvatar assignee={task.assignee} size="h-5 w-5" />
                                        </div>
                                    ) : (<>
                                    {task.epic && <EpicChip epic={task.epic} className="mb-1.5" />}
                                    <p className={cn('line-clamp-3 leading-snug', task.is_completed && 'text-ds-fg-muted line-through')}>{task.title}</p>
                                    {showProject && task.project && <p className="mt-1 truncate text-xs text-ds-fg-muted">{task.project.title}</p>}
                                    <div className="mt-2.5 flex items-center gap-2">
                                        <TypeIcon type={task.type} />
                                        <PriorityIcon priority={task.priority} />
                                        {task.type === 'bug' && <SeverityBadge severity={task.severity} />}
                                        <PointsBadge points={task.story_points} />
                                        {task.end_date && (
                                            <span className={cn('text-xs tabular-nums', isPastDue(task) ? 'font-medium text-ds-danger-fg' : 'text-ds-fg-muted')}>
                                                {shortDate(task.end_date)}
                                                {isPastDue(task) && <span className="sr-only"> ({t('Overdue')})</span>}
                                            </span>
                                        )}
                                        <span className="ml-auto"><AssigneeAvatar assignee={task.assignee} /></span>
                                    </div>
                                    </>)}
                                </article>
                            ))}
                            {columnTasks.length === 0 && (
                                <div className="rounded-lg border border-dashed border-ds-border-strong px-3 py-6 text-center text-xs text-ds-fg-muted">
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
