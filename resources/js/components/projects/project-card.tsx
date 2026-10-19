import { useTranslation } from 'react-i18next';
import { CalendarDays, Edit, Eye, Flag, Globe, MoreHorizontal, Trash2 } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const STATUS_STYLES: Record<string, { pill: string; dot: string; bar: string }> = {
    planning: { pill: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300', dot: 'bg-blue-500', bar: 'bg-blue-500' },
    active: { pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300', dot: 'bg-emerald-500', bar: 'bg-emerald-500' },
    on_hold: { pill: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300', dot: 'bg-amber-500', bar: 'bg-amber-500' },
    completed: { pill: 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300', dot: 'bg-violet-500', bar: 'bg-violet-500' },
    cancelled: { pill: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300', dot: 'bg-gray-400', bar: 'bg-gray-400' },
};

// Stable colour per project / person, so the same name always looks the same.
const TILE_COLORS = [
    'bg-blue-100 text-blue-700', 'bg-violet-100 text-violet-700', 'bg-emerald-100 text-emerald-700',
    'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700', 'bg-cyan-100 text-cyan-700',
];
const colorFor = (key: string | number) => {
    const text = String(key);
    let hash = 0;
    for (let i = 0; i < text.length; i++) hash = (hash * 31 + text.charCodeAt(i)) >>> 0;
    return TILE_COLORS[hash % TILE_COLORS.length];
};
const initials = (name?: string) => (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(word => word[0]).join('').toUpperCase();

const startOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate());

interface Props {
    project: any;
    canView: boolean;
    canEdit: boolean;
    canDelete: boolean;
    /** Off for clients: their own name adds nothing, so they see the description. */
    showClient?: boolean;
    onAction: (action: 'view' | 'edit' | 'delete', project: any) => void;
}

/**
 * A project at a glance: who it's for, how far along it is in tasks, who's on it
 * and when it's due, in plain words ("Due in 5 days", "Overdue by 2 days").
 */
export function ProjectCard({ project, canView, canEdit, canDelete, showClient = true, onAction }: Props) {
    const { t } = useTranslation();
    const status = STATUS_STYLES[project.status] || STATUS_STYLES.planning;
    const statusLabel = t((project.status || '').replace('_', ' ').replace(/^\w/, (c: string) => c.toUpperCase()));
    const members: any[] = project.members || [];
    const clients: any[] = showClient ? project.clients || [] : [];
    const done = project.completed_tasks_count ?? 0;
    const total = project.tasks_count ?? 0;
    const progress = project.progress ?? 0;
    const isClosed = ['completed', 'cancelled'].includes(project.status);
    const showPriority = project.priority === 'high' || project.priority === 'urgent';

    // Due label and how loud it is.
    let due: { label: string; tone: 'muted' | 'warning' | 'danger' } = { label: t('No deadline'), tone: 'muted' };
    let fullDate = '';
    if (project.deadline) {
        const deadline = new Date(project.deadline);
        fullDate = deadline.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        const days = Math.round((startOfDay(deadline).getTime() - startOfDay(new Date()).getTime()) / 86400000);
        const short = deadline.toLocaleDateString(undefined, {
            day: 'numeric', month: 'short', ...(deadline.getFullYear() !== new Date().getFullYear() ? { year: 'numeric' } : {}),
        });
        if (isClosed) due = { label: t('Due {{date}}', { date: short }), tone: 'muted' };
        else if (days < 0) due = { label: days === -1 ? t('Overdue by 1 day') : t('Overdue by {{count}} days', { count: -days }), tone: 'danger' };
        else if (days === 0) due = { label: t('Due today'), tone: 'danger' };
        else if (days <= 7) due = { label: days === 1 ? t('Due tomorrow') : t('Due in {{count}} days', { count: days }), tone: 'warning' };
        else due = { label: t('Due {{date}}', { date: short }), tone: 'muted' };
    }
    const barColor = due.tone === 'danger' && !isClosed ? 'bg-red-500' : status.bar;

    const subtitle = clients.length > 0
        ? (clients.length === 1 ? clients[0].name : t('{{name}} and {{count}} more', { name: clients[0].name, count: clients.length - 1 }))
        : project.description;

    return (
        <Card
            className={cn(
                'group flex flex-col gap-4 p-5 transition-all hover:border-primary/30 hover:shadow-md',
                canView && 'cursor-pointer',
            )}
            onClick={canView ? () => onAction('view', project) : undefined}
        >
            {/* Who and what */}
            <div className="flex items-start gap-3">
                <div className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-sm font-semibold', colorFor(project.id))}>
                    {initials(project.title)}
                </div>
                <div className="min-w-0 flex-1">
                    <h3 className="truncate font-semibold leading-tight group-hover:text-primary" title={project.title}>{project.title}</h3>
                    <div className="mt-1.5 flex min-w-0 items-center gap-2 text-sm text-muted-foreground">
                        <span className={cn('inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium', status.pill)}>
                            <span className={cn('h-1.5 w-1.5 rounded-full', status.dot)} />
                            {statusLabel}
                        </span>
                        {subtitle && <span className="truncate" title={clients.length > 0 ? `${t('Client')}: ${subtitle}` : subtitle}>{subtitle}</span>}
                    </div>
                </div>
                <div className="-mr-1 -mt-1 shrink-0">
                    {(canView || canEdit || canDelete) && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild onClick={e => e.stopPropagation()}>
                                <Button variant="ghost" size="icon" className="h-7 w-7 text-muted-foreground" aria-label={t('Project actions')}>
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            {/* Menu clicks must not also open the card. */}
                            <DropdownMenuContent align="end" onClick={e => e.stopPropagation()}>
                                {canView && (
                                    <DropdownMenuItem onClick={() => onAction('view', project)}>
                                        <Eye className="mr-2 h-4 w-4" />{t('View')}
                                    </DropdownMenuItem>
                                )}
                                {canEdit && (
                                    <DropdownMenuItem onClick={() => onAction('edit', project)}>
                                        <Edit className="mr-2 h-4 w-4" />{t('Edit')}
                                    </DropdownMenuItem>
                                )}
                                {canDelete && (
                                    <>
                                        {(canView || canEdit) && <DropdownMenuSeparator />}
                                        <DropdownMenuItem onClick={() => onAction('delete', project)} className="text-red-600 focus:text-red-600">
                                            <Trash2 className="mr-2 h-4 w-4" />{t('Delete')}
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </div>

            {/* How far along, in tasks */}
            <div className="space-y-2">
                <div className="flex items-baseline justify-between text-sm">
                    <span className="text-muted-foreground">
                        {total > 0 ? t('{{done}} of {{total}} tasks done', { done, total }) : t('No tasks yet')}
                    </span>
                    <span className="font-semibold tabular-nums">{progress}%</span>
                </div>
                <Progress value={progress} className="h-2" indicatorClassName={barColor} />
            </div>

            {/* Who's on it, priority and due date */}
            <div className="mt-auto flex items-center justify-between gap-2 border-t pt-4">
                <div className="flex -space-x-2">
                    {members.slice(0, 4).map((member, index) => (
                        <Tooltip key={index}>
                            <TooltipTrigger asChild>
                                <Avatar className="h-7 w-7 border-2 border-background">
                                    {member.user?.avatar && <AvatarImage src={member.user.avatar} />}
                                    <AvatarFallback className={cn('text-[10px] font-semibold', colorFor(member.user?.name || index))}>
                                        {initials(member.user?.name)}
                                    </AvatarFallback>
                                </Avatar>
                            </TooltipTrigger>
                            <TooltipContent>{member.user?.name}</TooltipContent>
                        </Tooltip>
                    ))}
                    {members.length > 4 && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-background bg-muted text-[10px] font-medium">
                                    +{members.length - 4}
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>{members.slice(4).map(m => m.user?.name).join(', ')}</TooltipContent>
                        </Tooltip>
                    )}
                    {members.length === 0 && <span className="text-sm text-muted-foreground">{t('No team yet')}</span>}
                </div>

                <div className="flex min-w-0 items-center gap-3 text-sm">
                    {!!project.is_public && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Globe className="h-4 w-4 shrink-0 text-muted-foreground" aria-label={t('Public')} />
                            </TooltipTrigger>
                            <TooltipContent>{t('Public')}</TooltipContent>
                        </Tooltip>
                    )}
                    {showPriority && (
                        <span className={cn('inline-flex shrink-0 items-center gap-1 text-xs font-medium', project.priority === 'urgent' ? 'text-red-600' : 'text-orange-600')}>
                            <Flag className="h-3.5 w-3.5" />
                            {project.priority === 'urgent' ? t('Urgent') : t('High')}
                        </span>
                    )}
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 truncate',
                            due.tone === 'danger' ? 'font-medium text-red-600' : due.tone === 'warning' ? 'font-medium text-amber-600' : 'text-muted-foreground',
                        )}
                        title={fullDate || undefined}
                    >
                        <CalendarDays className="h-4 w-4 shrink-0" />
                        {due.label}
                    </span>
                </div>
            </div>
        </Card>
    );
}
