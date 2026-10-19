import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Building2, Bug, CheckSquare, CreditCard, FolderPlus, Plus, Rocket, Search, Ticket } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { hasPermission } from '@/utils/authorization';

// Project pages bring their own "Create" (scoped to that project), so the header one stays out of the way there.
const PROJECT_PAGES = ['projects/Show', 'projects/Board', 'projects/Backlog', 'projects/Epics', 'projects/Meetings', 'sprints/Index', 'sprints/Show'];

/**
 * The header's "Create" button (like Jira's): whatever this role may create,
 * one click from any page. Sprints first ask which project they belong to.
 * The platform admin creates companies, plans and coupons.
 */
export function GlobalCreateMenu() {
    const { t } = useTranslation();
    const page = usePage();
    const { auth } = page.props as any;
    const permissions: string[] = auth?.permissions || [];
    const can = (permission: string) => hasPermission(permissions, permission);
    const [pickingProject, setPickingProject] = useState(false);

    if (PROJECT_PAGES.includes(page.component)) return null;

    const isSuperAdmin = auth?.user?.type === 'superadmin';
    const exists = (name: string) => route().has(name);
    const items = (isSuperAdmin ? [
        can('company_create') && exists('companies.index') && { key: 'company', label: t('Company'), icon: Building2, href: route('companies.index', { action: 'create' }) },
        can('plan_create') && exists('plans.create') && { key: 'plan', label: t('Plan'), icon: CreditCard, href: route('plans.create') },
        can('coupon_create') && exists('coupons.index') && { key: 'coupon', label: t('Coupon'), icon: Ticket, href: route('coupons.index', { action: 'create' }) },
    ] : [
        can('task_create') && { key: 'task', label: t('Task'), icon: CheckSquare, href: route('tasks.index', { action: 'create' }) },
        // A bug is a task of type bug: the task form, opened as "Report bug".
        can('task_create') && can('bug_create') && { key: 'bug', label: t('Bug'), icon: Bug, href: route('tasks.index', { type: 'bug', action: 'create' }) },
        can('sprint_create') && can('project_view_any') && { key: 'sprint', label: t('Sprint'), icon: Rocket },
        can('project_create') && { key: 'project', label: t('Project'), icon: FolderPlus, href: route('projects.index', { action: 'create' }) },
    ]).filter(Boolean) as { key: string; label: string; icon: typeof Plus; href?: string }[];

    if (items.length === 0) return null;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button size="sm" className="h-9 gap-1.5 px-2.5 sm:px-3" aria-label={t('Create')}>
                        <Plus className="h-4 w-4" />
                        <span className="hidden sm:inline">{t('Create')}</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="min-w-44">
                    {items.map(item => (
                        <DropdownMenuItem key={item.key} onClick={() => item.href ? router.get(item.href) : setPickingProject(true)}>
                            <item.icon className="mr-2 h-4 w-4" />
                            {item.label}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>

            <SprintProjectPicker open={pickingProject} onClose={() => setPickingProject(false)} />
        </>
    );
}

/** "Which project is this sprint for?" — then opens that project's Backlog with the create form. */
function SprintProjectPicker({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [projects, setProjects] = useState<{ id: number; title: string; status: string }[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            setLoading(true);
            try {
                const response = await fetch(route('search', { scope: 'projects', q: query }), { signal: controller.signal, headers: { Accept: 'application/json' } });
                const data = await response.json();
                setProjects(data.projects || []);
            } catch { /* aborted or offline: keep the last list */ }
            setLoading(false);
        }, query ? 250 : 0);
        return () => { clearTimeout(timeout); controller.abort(); };
    }, [open, query]);

    return (
        <Dialog open={open} onOpenChange={(isOpen) => { if (!isOpen) { onClose(); setQuery(''); } }}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Create sprint')}</DialogTitle>
                    <DialogDescription>{t('Choose the project the sprint belongs to.')}</DialogDescription>
                </DialogHeader>
                <div className="relative">
                    <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input autoFocus value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t('Search projects...')} className="pl-9" />
                </div>
                <div className="max-h-72 space-y-1 overflow-y-auto">
                    {projects.map(project => (
                        <button
                            key={project.id}
                            type="button"
                            onClick={() => router.get(route('sprints.index', { project: project.id, create: 1 }))}
                            className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm hover:bg-muted"
                        >
                            <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-xs font-semibold text-primary">
                                {project.title.charAt(0).toUpperCase()}
                            </span>
                            <span className="min-w-0 flex-1 truncate">{project.title}</span>
                            <span className="text-xs capitalize text-muted-foreground">{t(project.status.replace('_', ' '))}</span>
                        </button>
                    ))}
                    {!loading && projects.length === 0 && (
                        <p className="px-3 py-6 text-center text-sm text-muted-foreground">{t('No projects found.')}</p>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
