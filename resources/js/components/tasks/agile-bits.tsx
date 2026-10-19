import { ArrowDown, ArrowUp, Bug, Check, ChevronsUp, Equal } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export type WorkType = 'task' | 'bug';
export type Severity = 'minor' | 'major' | 'critical' | 'blocker';

/** A task as the Backlog and Board pages receive it (PresentsAgileBoard::taskCard). */
export interface AgileTask {
    id: number;
    title: string;
    /** Bugs are tasks of type 'bug' and share the Board, Backlog and Sprints. */
    type?: WorkType;
    severity?: Severity | null;
    priority: 'low' | 'medium' | 'high' | 'critical' | string;
    story_points: number | null;
    end_date: string | null;
    is_completed: boolean;
    task_stage_id: number | null;
    stage: { id: number; name: string; color: string } | null;
    assignee: { id: number; name: string; avatar: string | null } | null;
    /** The epic it is filed under, if any. */
    epic?: { id: number; name: string; color: string } | null;
    /** Sent on the Tasks page, where one board shows every project. */
    project?: { id: number; title: string } | null;
}

/** A sprint summary (PresentsAgileBoard::sprintSummary). */
export interface AgileSprint {
    id: number;
    name: string;
    goal: string | null;
    status: 'planning' | 'active' | 'completed';
    start_date: string | null;
    end_date: string | null;
    days_left: number | null;
    task_count: number;
    done_count: number;
    points: number;
    done_points: number;
    /** Completed sprints: tasks that were unfinished and moved on. */
    carried_over?: number;
    tasks?: AgileTask[];
}

const PRIORITY = {
    critical: { icon: ChevronsUp, className: 'text-red-600', label: 'Critical' },
    high: { icon: ArrowUp, className: 'text-orange-500', label: 'High' },
    medium: { icon: Equal, className: 'text-amber-500', label: 'Medium' },
    low: { icon: ArrowDown, className: 'text-sky-500', label: 'Low' },
} as const;

/** Jira-style priority arrow with a tooltip. */
export function PriorityIcon({ priority }: { priority: string }) {
    const { t } = useTranslation();
    const def = PRIORITY[priority as keyof typeof PRIORITY] || PRIORITY.medium;
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <def.icon className={cn('h-4 w-4 shrink-0', def.className)} aria-label={t(def.label)} />
            </TooltipTrigger>
            <TooltipContent>{t('Priority')}: {t(def.label)}</TooltipContent>
        </Tooltip>
    );
}

/** Jira-style work type mark: a blue tick for a task, a red bug for a bug. */
export function TypeIcon({ type, className }: { type?: string | null; className?: string }) {
    const { t } = useTranslation();
    const isBug = type === 'bug';
    const Icon = isBug ? Bug : Check;
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    className={cn(
                        'inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-[4px] text-white',
                        isBug ? 'bg-red-500' : 'bg-sky-500',
                        className,
                    )}
                    aria-label={isBug ? t('Bug') : t('Task')}
                >
                    <Icon className="h-3 w-3" strokeWidth={isBug ? 2.25 : 3} />
                </span>
            </TooltipTrigger>
            <TooltipContent>{isBug ? t('Bug') : t('Task')}</TooltipContent>
        </Tooltip>
    );
}

export const SEVERITY_OPTIONS: { value: Severity; label: string; hint: string }[] = [
    { value: 'minor', label: 'Minor', hint: 'Cosmetic or easy to work around' },
    { value: 'major', label: 'Major', hint: 'A feature misbehaves' },
    { value: 'critical', label: 'Critical', hint: 'A key flow is broken' },
    { value: 'blocker', label: 'Blocker', hint: 'Stops work or a release' },
];

const SEVERITY_STYLES: Record<Severity, string> = {
    blocker: 'bg-red-600 text-white',
    critical: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-950 dark:text-red-300',
    major: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20 dark:bg-orange-950 dark:text-orange-300',
    minor: 'bg-muted text-muted-foreground',
};

/** A bug's severity; nothing for plain tasks. */
export function SeverityBadge({ severity, className }: { severity?: string | null; className?: string }) {
    const { t } = useTranslation();
    if (!severity || !(severity in SEVERITY_STYLES)) return null;
    const option = SEVERITY_OPTIONS.find(o => o.value === severity)!;
    return (
        <span
            className={cn('inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-[11px] font-medium leading-none', SEVERITY_STYLES[severity as Severity], className)}
            title={`${t('Severity')}: ${t(option.label)} · ${t(option.hint)}`}
        >
            {t(option.label)}
        </span>
    );
}

export function PointsBadge({ points }: { points: number | null }) {
    const { t } = useTranslation();
    if (points === null || points === undefined) return null;
    return (
        <span
            className="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-muted px-1.5 text-[11px] font-medium tabular-nums text-muted-foreground"
            title={t('Story points')}
        >
            {points}
        </span>
    );
}

export function StageChip({ stage }: { stage: AgileTask['stage'] }) {
    if (!stage) return null;
    return (
        <span className="inline-flex max-w-[9rem] shrink-0 items-center gap-1.5 rounded-md border px-1.5 py-0.5 text-[11px] text-muted-foreground">
            <span className="h-1.5 w-1.5 shrink-0 rounded-full" style={{ backgroundColor: stage.color || '#6366f1' }} />
            <span className="truncate">{stage.name}</span>
        </span>
    );
}

/** The epic an item belongs to: a small tag in the epic's colour. */
export function EpicChip({ epic, className }: { epic?: AgileTask['epic']; className?: string }) {
    const { t } = useTranslation();
    if (!epic) return null;
    return (
        <span
            className={cn('inline-flex max-w-[10rem] shrink-0 items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium leading-none', className)}
            style={{ backgroundColor: `${epic.color}1f`, color: epic.color }}
            title={`${t('Epic')}: ${epic.name}`}
        >
            <span className="truncate">{epic.name}</span>
        </span>
    );
}

export function AssigneeAvatar({ assignee, size = 'h-6 w-6' }: { assignee: AgileTask['assignee']; size?: string }) {
    const { t } = useTranslation();
    if (!assignee) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className={cn('inline-block shrink-0 rounded-full border border-dashed border-muted-foreground/40', size)} aria-label={t('Unassigned')} />
                </TooltipTrigger>
                <TooltipContent>{t('Unassigned')}</TooltipContent>
            </Tooltip>
        );
    }
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Avatar className={cn('shrink-0', size)}>
                    {assignee.avatar && <AvatarImage src={assignee.avatar} />}
                    <AvatarFallback className="text-[10px]">{assignee.name?.charAt(0)}</AvatarFallback>
                </Avatar>
            </TooltipTrigger>
            <TooltipContent>{assignee.name}</TooltipContent>
        </Tooltip>
    );
}

/** "14 Oct" style date, or "" when missing. */
export function shortDate(value: string | null | undefined): string {
    if (!value) return '';
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

export function isPastDue(task: Pick<AgileTask, 'end_date' | 'is_completed'>): boolean {
    if (!task.end_date || task.is_completed) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return new Date(`${task.end_date.slice(0, 10)}T00:00:00`) < today;
}
