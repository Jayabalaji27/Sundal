import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { CalendarClock, ChevronDown, ExternalLink, Plus, Video } from 'lucide-react';
import { ProjectShell } from '@/components/projects/project-shell';
import { MeetingSetupNotice } from '@/components/meetings/setup-notice';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface Meeting {
    key: string;
    id: number;
    provider: 'zoom' | 'google';
    title: string;
    description: string | null;
    start_time: string | null;
    duration: number;
    status: string | null;
    join_url: string | null;
    show_url: string;
    attendees: { id: number; name: string; avatar: string | null }[];
}

const PROVIDER = {
    zoom: { label: 'Zoom', className: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200' },
    google: { label: 'Google Meet', className: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200' },
} as const;

/**
 * The project's meetings, Zoom and Google Meet together (Zoho Sprints'
 * project Meetings tab): what's coming up, with Join, and what has been held.
 * Scheduling opens the Meetings page with this project already chosen.
 */
export default function ProjectMeetings() {
    const { t } = useTranslation();
    const { project, meetings = [], canSchedule = { zoom: false, google: false }, connected = { zoom: false, google: false }, canConnect = false, taskForm = null } = usePage().props as any;

    const now = Date.now();
    const endOf = (m: Meeting) => (m.start_time ? new Date(m.start_time).getTime() + (m.duration || 60) * 60000 : 0);
    const all = meetings as Meeting[];
    const upcoming = all.filter(m => m.status !== 'cancelled' && endOf(m) >= now);
    const past = all.filter(m => !upcoming.includes(m)).reverse();

    const schedule = (provider: 'zoom' | 'google') =>
        router.get(route(provider === 'zoom' ? 'zoom-meetings.index' : 'google-meetings.index', { project_id: project.id, action: 'create' }));
    const providers = (['zoom', 'google'] as const).filter(p => canSchedule[p]);

    const when = (m: Meeting) => {
        if (!m.start_time) return t('No time set');
        const start = new Date(m.start_time);
        const day = start.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
        const time = start.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        return `${day} · ${time} · ${t(':count min').replace(':count', String(m.duration || 60))}`;
    };

    const row = (m: Meeting, isUpcoming: boolean) => {
        const live = isUpcoming && m.start_time && new Date(m.start_time).getTime() <= now;
        return (
            <div key={m.key} className="flex flex-wrap items-center gap-3 border-b px-4 py-3 text-sm last:border-b-0">
                <span className={cn('inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg', isUpcoming ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground')}>
                    <Video className="h-4 w-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link href={m.show_url} className="truncate font-medium hover:text-primary hover:underline">{m.title}</Link>
                        <span className={cn('rounded px-1.5 py-0.5 text-[10px] font-medium', PROVIDER[m.provider].className)}>{PROVIDER[m.provider].label}</span>
                        {live && <span className="rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-700">{t('Now')}</span>}
                        {m.status === 'cancelled' && <span className="text-xs text-muted-foreground">{t('Cancelled')}</span>}
                    </div>
                    <p className="text-xs text-muted-foreground">{when(m)}</p>
                </div>
                {m.attendees.length > 0 && (
                    <div className="flex -space-x-1.5">
                        {m.attendees.slice(0, 4).map(person => (
                            <Tooltip key={person.id}>
                                <TooltipTrigger asChild>
                                    <Avatar className="h-6 w-6 border-2 border-background">
                                        {person.avatar && <AvatarImage src={person.avatar} />}
                                        <AvatarFallback className="text-[10px]">{person.name?.charAt(0)}</AvatarFallback>
                                    </Avatar>
                                </TooltipTrigger>
                                <TooltipContent>{person.name}</TooltipContent>
                            </Tooltip>
                        ))}
                        {m.attendees.length > 4 && (
                            <span className="flex h-6 w-6 items-center justify-center rounded-full border-2 border-background bg-muted text-[10px]">+{m.attendees.length - 4}</span>
                        )}
                    </div>
                )}
                {isUpcoming && m.join_url ? (
                    <Button asChild size="sm" variant={live ? 'default' : 'outline'} className="h-8">
                        <a href={m.join_url} target="_blank" rel="noreferrer">
                            {t('Join')}
                            <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                    </Button>
                ) : (
                    <Button asChild size="sm" variant="ghost" className="h-8">
                        <Link href={m.show_url}>{t('View')}</Link>
                    </Button>
                )}
            </div>
        );
    };

    const section = (title: string, items: Meeting[], isUpcoming: boolean, empty: string) => (
        <section className="overflow-hidden rounded-lg border bg-card" aria-label={title}>
            <header className="flex items-center gap-2 border-b bg-muted/30 px-4 py-2.5">
                <h2 className="font-semibold">{title}</h2>
                <span className="text-xs tabular-nums text-muted-foreground">{items.length}</span>
            </header>
            {items.length > 0 ? items.map(m => row(m, isUpcoming)) : <p className="px-4 py-6 text-center text-sm text-muted-foreground">{empty}</p>}
        </section>
    );

    return (
        <ProjectShell project={project} active="meetings" taskForm={taskForm}>
            {/* Neither provider connected yet: say so (one connected is enough to schedule). */}
            {!connected.zoom && !connected.google && <div className="-mb-4"><MeetingSetupNotice providers={['zoom', 'google']} canConnect={canConnect} /></div>}
            {providers.length > 0 && (
                <div className="flex justify-end">
                    {providers.length === 1 ? (
                        <Button size="sm" className="h-9" onClick={() => schedule(providers[0])}>
                            <Plus className="h-4 w-4" />
                            {t('Schedule meeting')}
                        </Button>
                    ) : (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button size="sm" className="h-9">
                                    <Plus className="h-4 w-4" />
                                    {t('Schedule meeting')}
                                    <ChevronDown className="h-3.5 w-3.5 opacity-70" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {providers.map(p => (
                                    <DropdownMenuItem key={p} onClick={() => schedule(p)}>
                                        <Video className="mr-2 h-4 w-4" />
                                        {PROVIDER[p].label}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            )}

            {all.length === 0 ? (
                <div className="rounded-lg border-2 border-dashed px-6 py-12 text-center">
                    <CalendarClock className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
                    <p className="font-medium">{t('No meetings for this project yet')}</p>
                    <p className="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                        {t('Stand-ups, sprint reviews and client calls scheduled for this project show here, with a Join button.')}
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    {section(t('Upcoming'), upcoming, true, t('Nothing scheduled.'))}
                    {past.length > 0 && section(t('Past'), past, false, '')}
                </div>
            )}
        </ProjectShell>
    );
}
