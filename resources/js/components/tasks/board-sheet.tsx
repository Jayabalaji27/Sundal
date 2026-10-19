import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { AgileTask, AssigneeAvatar, PriorityIcon, SeverityBadge, StageChip, TypeIcon, isPastDue, shortDate } from '@/components/tasks/agile-bits';
import type { KanbanStage } from '@/components/tasks/kanban-board';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

type SortKey = 'title' | 'stage' | 'priority' | 'severity' | 'assignee' | 'points' | 'due';

const PRIORITY_RANK: Record<string, number> = { critical: 0, high: 1, medium: 2, low: 3 };
const SEVERITY_RANK: Record<string, number> = { blocker: 0, critical: 1, major: 2, minor: 3 };

/**
 * The board as a table: every card on one row, sortable by any column. The
 * stage can be changed in place (the same move as dragging a card); the title
 * opens the item. The caller owns the data, as with KanbanBoard.
 */
export function BoardSheet({ stages, tasks, canMove, onMove, onOpen }: {
    stages: KanbanStage[];
    tasks: AgileTask[];
    canMove: boolean;
    onMove: (taskId: number, stageId: number) => void;
    onOpen: (taskId: number) => void;
}) {
    const { t } = useTranslation();
    // Board order to start with: by stage, as the columns read left to right.
    const [sort, setSort] = useState<{ key: SortKey; dir: 'asc' | 'desc' }>({ key: 'stage', dir: 'asc' });

    const rows = useMemo(() => {
        const stageOrder = (task: AgileTask) => {
            const index = stages.findIndex(stage => stage.id === task.task_stage_id);
            return index === -1 ? stages.length : index;
        };
        const value = (task: AgileTask): string | number => {
            switch (sort.key) {
                case 'title': return task.title.toLowerCase();
                case 'stage': return stageOrder(task);
                case 'priority': return PRIORITY_RANK[task.priority] ?? 9;
                // Tasks have no severity: they sort after every bug.
                case 'severity': return task.severity ? SEVERITY_RANK[task.severity] ?? 8 : 9;
                case 'assignee': return task.assignee?.name.toLowerCase() ?? '￿';
                case 'points': return task.story_points ?? -1;
                case 'due': return task.end_date ?? '9999-12-31';
            }
        };
        const direction = sort.dir === 'asc' ? 1 : -1;
        return [...tasks].sort((a, b) => {
            const [x, y] = [value(a), value(b)];
            return (x < y ? -1 : x > y ? 1 : a.id - b.id) * direction;
        });
    }, [tasks, stages, sort]);

    const header = (key: SortKey, label: string, className = '') => {
        const active = sort.key === key;
        const Icon = !active ? ChevronsUpDown : sort.dir === 'asc' ? ArrowUp : ArrowDown;
        return (
            <th scope="col" className={cn('px-3 py-2.5 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground', className)} aria-sort={active ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none'}>
                <button
                    type="button"
                    className={cn('inline-flex items-center gap-1 hover:text-foreground', active && 'text-foreground')}
                    onClick={() => setSort(prev => ({ key, dir: prev.key === key && prev.dir === 'asc' ? 'desc' : 'asc' }))}
                >
                    {label}
                    <Icon className={cn('h-3 w-3', !active && 'opacity-40')} />
                </button>
            </th>
        );
    };

    return (
        <div className="overflow-hidden rounded-lg border bg-card">
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y text-sm">
                    <thead className="bg-muted/40">
                        <tr>
                            {header('title', t('Work item'), 'min-w-[16rem]')}
                            {header('stage', t('Stage'))}
                            {header('priority', t('Priority'))}
                            {header('severity', t('Severity'))}
                            {header('assignee', t('Assignee'))}
                            {header('points', t('Points'), 'text-right')}
                            {header('due', t('Due'))}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.map(task => (
                            <tr key={task.id} className="hover:bg-muted/30">
                                <td className="px-3 py-2">
                                    <div className="flex items-center gap-2">
                                        <TypeIcon type={task.type} />
                                        <span className="shrink-0 text-xs tabular-nums text-muted-foreground">#{task.id}</span>
                                        <button
                                            type="button"
                                            onClick={() => onOpen(task.id)}
                                            className={cn('min-w-0 truncate text-left font-medium hover:text-primary hover:underline', task.is_completed && 'text-muted-foreground line-through')}
                                            title={task.title}
                                        >
                                            {task.title}
                                        </button>
                                    </div>
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    {canMove ? (
                                        <Select value={task.task_stage_id ? String(task.task_stage_id) : undefined} onValueChange={(value) => onMove(task.id, Number(value))}>
                                            <SelectTrigger className="h-8 w-40 text-xs" aria-label={t('Stage of :title').replace(':title', task.title)}>
                                                <SelectValue placeholder={t('No stage')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {stages.map(stage => (
                                                    <SelectItem key={stage.id} value={String(stage.id)}>
                                                        <span className="inline-flex items-center gap-2">
                                                            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: stage.color || '#6366f1' }} />
                                                            {stage.name}
                                                        </span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : <StageChip stage={task.stage} />}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    <span className="inline-flex items-center gap-1.5 capitalize">
                                        <PriorityIcon priority={task.priority} />
                                        {t(task.priority.charAt(0).toUpperCase() + task.priority.slice(1))}
                                    </span>
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    {task.type === 'bug' ? <SeverityBadge severity={task.severity} /> : <span className="text-muted-foreground">—</span>}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    <span className="inline-flex items-center gap-2">
                                        <AssigneeAvatar assignee={task.assignee} />
                                        <span className={cn('max-w-[10rem] truncate', !task.assignee && 'text-muted-foreground')}>{task.assignee?.name ?? t('Unassigned')}</span>
                                    </span>
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                    {task.story_points ?? <span className="text-muted-foreground">—</span>}
                                </td>
                                <td className={cn('px-3 py-2 whitespace-nowrap tabular-nums', isPastDue(task) ? 'font-medium text-red-600' : 'text-muted-foreground')}>
                                    {task.end_date ? shortDate(task.end_date) : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="border-t px-3 py-2 text-xs text-muted-foreground">
                {rows.length === 1 ? t('1 item') : t(':count items').replace(':count', String(rows.length))}
            </div>
        </div>
    );
}
