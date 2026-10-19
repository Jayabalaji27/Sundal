import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Bug, CheckSquare, ChevronDown, Flag, MoreHorizontal, Plus, Rocket } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { FloatingChatGpt } from '@/components/FloatingChatGpt';
import { PageAction, PageActions } from '@/components/page-template';
import TaskFormModal from '@/components/tasks/TaskFormModal';
import type { WorkType } from '@/components/tasks/agile-bits';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { hasPermission } from '@/utils/authorization';
import { cn } from '@/lib/utils';

/**
 * Tabs of a project. Backlog (unplanned work), Sprints, Epics, Board and
 * Meetings are their own pages; the rest are panels of the project page
 * (projects/Show), addressed with ?tab=.
 */
export type ProjectTab =
    | 'overview' | 'backlog' | 'sprints' | 'epics' | 'board' | 'gantt' | 'milestones' | 'meetings'
    | 'tasks' | 'team' | 'attachments' | 'notes' | 'budget' | 'expense' | 'timesheet' | 'activity';

/** Tabs that are pages of their own rather than panels of the project page. */
const PAGE_TABS: ProjectTab[] = ['backlog', 'sprints', 'epics', 'board', 'meetings'];

export interface ProjectShellProject {
    id: number;
    title: string;
    status: string;
    members?: { id: number; name: string; avatar?: string | null }[];
    members_count?: number;
}

/** Data for creating a task in this project without leaving it (sent by the server when the user may). */
export interface ProjectTaskForm {
    projects: any[];
    members: any[];
}

interface TabDef {
    key: ProjectTab;
    label: string;
    /** One permission, or several of which any one will do. */
    permission: string | string[];
    /** Meetings: only while the plan includes the add-on modules (as in the sidebar). */
    needsAddOns?: boolean;
}

const allowed = (tab: TabDef, permissions: string[], addOnsUnlocked = true) =>
    (Array.isArray(tab.permission) ? tab.permission.some(p => hasPermission(permissions, p)) : hasPermission(permissions, tab.permission))
    && (!tab.needsAddOns || addOnsUnlocked);

/**
 * The order of Zoho Sprints' project tabs (Backlog, Epics, Release, Board,
 * Reports, Timesheet, Meetings, Expenses), using Sundal's equivalents, after
 * the Summary: Backlog, Sprints (Zoho plans sprints inside its Backlog),
 * Epics, Milestones (Zoho's Release), Board, Timesheet, Meetings, Expenses.
 * The rest sit under "More". A tab only shows when the role holds the
 * permission that guards its data. Clients get the Summary and Milestones
 * only: the activity log is internal.
 */
function projectTabs(t: (key: string) => string): { primary: TabDef[]; more: TabDef[] } {
    return {
        primary: [
            { key: 'overview', label: t('Summary'), permission: 'project_view' },
            { key: 'backlog', label: t('Backlog'), permission: 'sprint_view_any' },
            { key: 'sprints', label: t('Sprints'), permission: 'sprint_view_any' },
            { key: 'epics', label: t('Epics'), permission: 'sprint_view_any' },
            { key: 'milestones', label: t('Milestones'), permission: 'project_view' },
            // No Bugs tab: bugs are tasks of type bug, on the Board, Backlog and Sprints.
            { key: 'board', label: t('Board'), permission: 'task_view_any' },
            { key: 'timesheet', label: t('Timesheet'), permission: 'timesheet_view_any' },
            { key: 'meetings', label: t('Meetings'), permission: ['zoom_meeting_view_any', 'google_meeting_view_any'], needsAddOns: true },
            { key: 'expense', label: t('Expenses'), permission: 'expense_view_any' },
        ],
        more: [
            { key: 'gantt', label: t('Timeline'), permission: 'task_view_any' },
            { key: 'budget', label: t('Budget'), permission: 'project_manage_budget' },
            { key: 'team', label: t('Team'), permission: 'project_assign_members' },
            { key: 'attachments', label: t('Files'), permission: 'project_manage_attachments' },
            { key: 'activity', label: t('Activity'), permission: 'task_view_any' },
        ],
    };
}

/** Whether the user can open a project tab, so a hidden tab's URL falls back to the Summary. */
export function canOpenProjectTab(tab: string, permissions: string[]): boolean {
    const { primary, more } = projectTabs(key => key);
    const def = [...primary, ...more].find(item => item.key === tab);
    return !!def && allowed(def, permissions);
}

const STATUS_STYLES: Record<string, string> = {
    planning: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    active: 'bg-green-50 text-green-700 ring-green-600/20',
    on_hold: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    completed: 'bg-violet-50 text-violet-700 ring-violet-600/20',
    cancelled: 'bg-red-50 text-red-700 ring-red-600/20',
};

const CREATE_TASK_EVENT = 'project-shell:create-task';

/** Opens the project's "Create task" (or "Report bug") form from anywhere on a project page. */
export function openProjectTaskForm(type: WorkType = 'task') {
    window.dispatchEvent(new CustomEvent(CREATE_TASK_EVENT, { detail: { type } }));
}

/** Where a tab lives; panels of the project page get ?tab=. */
export function projectTabHref(projectId: number, tab: ProjectTab): string {
    if (tab === 'backlog') return route('projects.backlog', projectId);
    if (tab === 'sprints') return route('sprints.index', projectId);
    if (tab === 'epics') return route('epics.index', projectId);
    if (tab === 'meetings') return route('projects.meetings', projectId);
    if (tab === 'board') return route('projects.board', projectId);
    if (tab === 'overview') return route('projects.show', projectId);
    return route('projects.show', { project: projectId, tab });
}

export function ProjectShell({
    project,
    active,
    onTabChange,
    actions = [],
    taskForm,
    onCreateSprint,
    onCreateMilestone,
    section,
    children,
}: {
    project: ProjectShellProject;
    active: ProjectTab;
    /** Given by the project page so its own panels switch without a reload. */
    onTabChange?: (tab: ProjectTab) => void;
    actions?: PageAction[];
    taskForm?: ProjectTaskForm | null;
    onCreateSprint?: () => void;
    onCreateMilestone?: () => void;
    /** Overrides the active tab's label in the breadcrumb and window title (e.g. a sprint's name). */
    section?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();
    const { auth, globalSettings, name: appName, features } = usePage().props as any;
    const permissions: string[] = auth?.permissions || [];
    // As in the sidebar: Meetings while switched on (config/features.php) and the plan includes the add-on modules.
    const addOnsUnlocked = features?.meetings !== false && !auth?.modulesLocked;
    const can = (permission: string) => hasPermission(permissions, permission);
    const [isTaskFormOpen, setIsTaskFormOpen] = useState(false);
    const [taskFormType, setTaskFormType] = useState<WorkType>('task');
    const openTaskForm = (type: WorkType) => {
        setTaskFormType(type);
        setIsTaskFormOpen(true);
    };

    useEffect(() => {
        if (!taskForm) return;
        const open = (event: Event) => openTaskForm((event as CustomEvent).detail?.type === 'bug' ? 'bug' : 'task');
        window.addEventListener(CREATE_TASK_EVENT, open);
        return () => window.removeEventListener(CREATE_TASK_EVENT, open);
    }, [taskForm]);

    const { primary: primaryTabs, more: moreTabs } = projectTabs(t);
    const visiblePrimary = primaryTabs.filter(tab => allowed(tab, permissions, addOnsUnlocked) || tab.key === active);
    const visibleMore = moreTabs.filter(tab => allowed(tab, permissions, addOnsUnlocked));
    const activeMore = visibleMore.find(tab => tab.key === active);
    const activeLabel = [...primaryTabs, ...moreTabs].find(tab => tab.key === active)?.label;
    const sectionLabel = section || (active !== 'overview' ? activeLabel : undefined);

    const isPanel = (tab: ProjectTab) => !PAGE_TABS.includes(tab);
    const selectTab = (tab: ProjectTab) => {
        if (onTabChange && isPanel(tab)) onTabChange(tab);
        else router.get(projectTabHref(project.id, tab));
    };

    // "Create" offers what this role may add to the project.
    const createItems = [
        can('task_create') && {
            key: 'task', label: t('Task'), icon: CheckSquare,
            onSelect: () => taskForm
                ? openTaskForm('task')
                : router.get(route('tasks.index', { project_id: project.id, action: 'create' })),
        },
        // A bug is a task of type bug: the same form, opened as "Report bug".
        can('task_create') && can('bug_create') && {
            key: 'bug', label: t('Bug'), icon: Bug,
            onSelect: () => taskForm
                ? openTaskForm('bug')
                : router.get(route('tasks.index', { project_id: project.id, type: 'bug', action: 'create' })),
        },
        can('sprint_create') && {
            key: 'sprint', label: t('Sprint'), icon: Rocket,
            onSelect: () => onCreateSprint ? onCreateSprint() : router.get(route('sprints.index', { project: project.id, create: 1 })),
        },
        can('project_manage_milestones') && {
            key: 'milestone', label: t('Milestone'), icon: Flag,
            onSelect: () => onCreateMilestone ? onCreateMilestone() : router.get(route('projects.show', { project: project.id, tab: 'milestones', create: 'milestone' })),
        },
    ].filter(Boolean) as { key: string; label: string; icon: typeof Plus; onSelect: () => void }[];

    const breadcrumbs = [
        { title: t('Projects'), href: route('projects.index') },
        { title: project.title, href: route('projects.show', project.id) },
        ...(sectionLabel ? [{ title: sectionLabel }] : []),
    ];
    const members = project.members || [];
    const extraMembers = Math.max((project.members_count ?? members.length) - members.length, 0);

    const tabClass = (isActive: boolean) => cn(
        'inline-flex shrink-0 items-center gap-1 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm transition-colors',
        isActive ? 'border-primary font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${sectionLabel ? `${sectionLabel} · ` : ''}${project.title} - ${globalSettings?.titleText || appName}`} />

            <div className="flex flex-1 flex-col gap-4 p-4 px-4 pb-[50px] sm:px-6 lg:px-[50px]">
                <div>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex min-w-0 items-center gap-2.5">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary">
                                {project.title?.charAt(0)?.toUpperCase()}
                            </div>
                            <h1 className="truncate text-xl font-semibold" title={project.title}>{project.title}</h1>
                            <span className={cn('inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize ring-1 ring-inset', STATUS_STYLES[project.status] || 'bg-muted text-muted-foreground ring-border')}>
                                {t(project.status?.replace('_', ' ') || '')}
                            </span>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {members.length > 0 && (
                                <div className="mr-1 hidden -space-x-1.5 sm:flex">
                                    {members.map(member => (
                                        <Tooltip key={member.id}>
                                            <TooltipTrigger asChild>
                                                <Avatar className="h-7 w-7 border-2 border-background">
                                                    {member.avatar && <AvatarImage src={member.avatar} />}
                                                    <AvatarFallback className="text-[10px]">{member.name?.charAt(0)}</AvatarFallback>
                                                </Avatar>
                                            </TooltipTrigger>
                                            <TooltipContent>{member.name}</TooltipContent>
                                        </Tooltip>
                                    ))}
                                    {extraMembers > 0 && (
                                        <div className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-background bg-muted text-[10px]">
                                            +{extraMembers}
                                        </div>
                                    )}
                                </div>
                            )}
                            <PageActions actions={actions} />
                            {createItems.length > 0 && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button size="sm">
                                            <Plus className="h-4 w-4" />
                                            {t('Create')}
                                            <ChevronDown className="h-3.5 w-3.5 opacity-70" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="min-w-40">
                                        {createItems.map(item => (
                                            <DropdownMenuItem key={item.key} onClick={item.onSelect}>
                                                <item.icon className="mr-2 h-4 w-4" />
                                                {item.label}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                    </div>

                    <nav className="mt-3 flex items-end overflow-x-auto border-b" aria-label={t('Project sections')}>
                        {visiblePrimary.map(tab => (
                            onTabChange && isPanel(tab.key) ? (
                                <button key={tab.key} type="button" className={tabClass(active === tab.key)} onClick={() => selectTab(tab.key)} aria-current={active === tab.key ? 'page' : undefined}>
                                    {tab.label}
                                </button>
                            ) : (
                                <Link key={tab.key} href={projectTabHref(project.id, tab.key)} className={tabClass(active === tab.key)} aria-current={active === tab.key ? 'page' : undefined}>
                                    {tab.label}
                                </Link>
                            )
                        ))}
                        {visibleMore.length > 0 && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button type="button" className={tabClass(!!activeMore)}>
                                        {activeMore ? activeMore.label : (
                                            <>
                                                <MoreHorizontal className="h-4 w-4" />
                                                {t('More')}
                                            </>
                                        )}
                                        <ChevronDown className="h-3.5 w-3.5 opacity-70" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" className="min-w-44">
                                    {visibleMore.map(tab => (
                                        <DropdownMenuItem key={tab.key} onClick={() => selectTab(tab.key)} className={active === tab.key ? 'font-medium' : ''}>
                                            {tab.label}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </nav>
                </div>

                {children}
            </div>

            {taskForm && (
                <TaskFormModal
                    isOpen={isTaskFormOpen}
                    onClose={() => setIsTaskFormOpen(false)}
                    projects={taskForm.projects}
                    members={taskForm.members}
                    initialProjectId={String(project.id)}
                    initialType={taskFormType}
                />
            )}
            <FloatingChatGpt />
        </AppLayout>
    );
}

/** A compact "Sprint 3 · 5 days left" style line used by Backlog and Board. */
export function daysLeftLabel(t: (key: string) => string, daysLeft: number | null | undefined): string | null {
    if (daysLeft === null || daysLeft === undefined) return null;
    if (daysLeft < 0) return t('Overdue by :count days').replace(':count', String(Math.abs(daysLeft)));
    if (daysLeft === 0) return t('Ends today');
    if (daysLeft === 1) return t('1 day left');
    return t(':count days left').replace(':count', String(daysLeft));
}
