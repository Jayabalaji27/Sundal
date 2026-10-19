import { useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, Layers, MoreHorizontal, Pencil, Plus, RotateCcw, CheckCircle2, Trash2, X } from 'lucide-react';
import { ProjectShell } from '@/components/projects/project-shell';
import { sprintDateRange } from '@/components/sprints/sprint-form';
import { AgileTask, AssigneeAvatar, StageChip, TypeIcon } from '@/components/tasks/agile-bits';
import { useTaskDetail } from '@/components/tasks/use-task-detail';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { hasPermission } from '@/utils/authorization';
import { cn } from '@/lib/utils';

interface Epic {
    id: number;
    name: string;
    description: string | null;
    color: string;
    status: 'open' | 'done';
    start_date: string | null;
    end_date: string | null;
    owner: { id: number; name: string; avatar: string | null } | null;
    item_count: number;
    done_count: number;
    bug_count: number;
    points: number;
    done_points: number;
    tasks: AgileTask[];
}

interface EpicForm {
    name: string;
    description: string;
    color: string;
    owner_id: string;
    start_date: string;
    end_date: string;
    status: 'open' | 'done';
}

/**
 * Project Epics (as in Zoho Sprints and Jira): the big pieces of work, such as
 * a feature or a module, each grouping tasks and bugs across sprints, with its
 * progress. Open one to see its items, add items or take them out.
 */
export default function ProjectEpics() {
    const { t } = useTranslation();
    const { auth, project, epics = [], unfiled = [], colors = [], team = [], stages = [], taskForm = null } = usePage().props as any;
    const permissions: string[] = auth?.permissions || [];
    const canCreate = hasPermission(permissions, 'sprint_create');
    const canEdit = hasPermission(permissions, 'sprint_update');
    const canDelete = hasPermission(permissions, 'sprint_delete');
    const canFile = hasPermission(permissions, 'sprint_manage_tasks');
    const { openTask, taskDialog } = useTaskDetail({ members: taskForm?.members || [], stages, milestones: taskForm?.projects?.[0]?.milestones || [] });

    const list = epics as Epic[];
    const [filter, setFilter] = useState<'all' | 'open' | 'done'>('all');
    const [expanded, setExpanded] = useState<Record<number, boolean>>({});
    const [form, setForm] = useState<{ epic: Epic | null; values: EpicForm } | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [deleting, setDeleting] = useState<Epic | null>(null);

    const visible = useMemo(() => list.filter(epic => filter === 'all' || epic.status === filter), [list, filter]);
    const counts = { all: list.length, open: list.filter(e => e.status === 'open').length, done: list.filter(e => e.status === 'done').length };
    const options = { preserveScroll: true, preserveState: true };

    const openForm = (epic: Epic | null) => {
        setErrors({});
        setForm({
            epic,
            values: {
                name: epic?.name || '',
                description: epic?.description || '',
                color: epic?.color || colors[list.length % Math.max(colors.length, 1)] || '#8b5cf6',
                owner_id: epic?.owner ? String(epic.owner.id) : 'none',
                start_date: epic?.start_date || '',
                end_date: epic?.end_date || '',
                status: epic?.status || 'open',
            },
        });
    };

    const saveForm = () => {
        if (!form) return;
        const data = { ...form.values, owner_id: form.values.owner_id === 'none' ? '' : form.values.owner_id };
        const handlers = { ...options, onSuccess: () => setForm(null), onError: (e: Record<string, string>) => setErrors(e) };
        if (form.epic) router.put(route('epics.update', form.epic.id), data, handlers);
        else router.post(route('epics.store', project.id), data, handlers);
    };

    const setStatus = (epic: Epic, status: 'open' | 'done') => router.put(route('epics.update', epic.id), {
        name: epic.name, description: epic.description || '', color: epic.color, status,
        owner_id: epic.owner?.id ?? '', start_date: epic.start_date || '', end_date: epic.end_date || '',
    }, options);

    const addItem = (epic: Epic, taskId: string) => router.post(route('epics.add-task', epic.id), { task_id: taskId }, options);
    const removeItem = (epic: Epic, taskId: number) => router.delete(route('epics.remove-task', epic.id), { data: { task_id: taskId }, ...options });

    const segment = (active: boolean) => cn('inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-sm transition-colors', active ? 'bg-background font-medium shadow-sm' : 'text-muted-foreground hover:text-foreground');

    return (
        <ProjectShell project={project} active="epics" taskForm={taskForm}>
            <div className="flex flex-wrap items-center gap-2">
                <div role="radiogroup" aria-label={t('Status')} className="inline-flex rounded-lg bg-muted p-0.5">
                    {([['all', t('All')], ['open', t('Open')], ['done', t('Done')]] as const).map(([value, label]) => (
                        <button key={value} type="button" role="radio" aria-checked={filter === value} className={segment(filter === value)} onClick={() => setFilter(value)}>
                            {label}
                            <span className="text-xs tabular-nums text-muted-foreground">{counts[value]}</span>
                        </button>
                    ))}
                </div>
                {canCreate && list.length > 0 && (
                    <Button size="sm" className="ml-auto h-9" onClick={() => openForm(null)}>
                        <Plus className="h-4 w-4" />
                        {t('New epic')}
                    </Button>
                )}
            </div>

            {list.length === 0 ? (
                <div className="rounded-lg border-2 border-dashed px-6 py-12 text-center">
                    <Layers className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
                    <p className="font-medium">{t('No epics yet')}</p>
                    <p className="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                        {t('An epic is a big piece of work, such as a feature or a module. Group its tasks and bugs under it to follow its progress across sprints.')}
                    </p>
                    {canCreate && (
                        <Button size="sm" className="mt-4" onClick={() => openForm(null)}>
                            <Plus className="h-4 w-4" />
                            {t('New epic')}
                        </Button>
                    )}
                </div>
            ) : visible.length === 0 ? (
                <p className="text-sm text-muted-foreground">{filter === 'done' ? t('No epic is done yet.') : t('Every epic is done.')}</p>
            ) : (
                <div className="space-y-3">
                    {visible.map(epic => {
                        const pct = epic.item_count > 0 ? Math.round((epic.done_count / epic.item_count) * 100) : 0;
                        const isOpen = !!expanded[epic.id];
                        const dates = sprintDateRange(epic);
                        return (
                            <article key={epic.id} className="overflow-hidden rounded-lg border bg-card" style={{ borderLeft: `4px solid ${epic.color}` }}>
                                <div className="p-4">
                                    <div className="flex items-start gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className={cn('font-semibold', epic.status === 'done' && 'text-muted-foreground')}>{epic.name}</h3>
                                                <span className={cn('rounded px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide', epic.status === 'done' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-100' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200')}>
                                                    {epic.status === 'done' ? t('Done') : t('Open')}
                                                </span>
                                                {dates && <span className="text-xs text-muted-foreground">{dates}</span>}
                                            </div>
                                            {epic.description && <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{epic.description}</p>}
                                        </div>
                                        {epic.owner && <AssigneeAvatar assignee={epic.owner} />}
                                        {(canEdit || canDelete) && (
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="-mr-1 -mt-1 h-8 w-8 text-muted-foreground" aria-label={t('Epic actions')}>
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    {canEdit && (
                                                        <>
                                                            <DropdownMenuItem onClick={() => openForm(epic)}><Pencil className="mr-2 h-4 w-4" />{t('Edit')}</DropdownMenuItem>
                                                            {epic.status === 'open'
                                                                ? <DropdownMenuItem onClick={() => setStatus(epic, 'done')}><CheckCircle2 className="mr-2 h-4 w-4" />{t('Mark as done')}</DropdownMenuItem>
                                                                : <DropdownMenuItem onClick={() => setStatus(epic, 'open')}><RotateCcw className="mr-2 h-4 w-4" />{t('Reopen')}</DropdownMenuItem>}
                                                        </>
                                                    )}
                                                    {canDelete && (
                                                        <>
                                                            {canEdit && <DropdownMenuSeparator />}
                                                            <DropdownMenuItem className="text-red-600 focus:text-red-600" onClick={() => setDeleting(epic)}><Trash2 className="mr-2 h-4 w-4" />{t('Delete')}</DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        )}
                                    </div>

                                    <div className="mt-3 flex items-center gap-3">
                                        <Progress value={pct} className="h-1.5" indicatorClassName="bg-[var(--epic)]" style={{ ['--epic' as any]: epic.color }} />
                                        <span className="shrink-0 text-xs font-medium tabular-nums">{pct}%</span>
                                    </div>
                                    <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                        <span>{t(':done of :total items done').replace(':done', String(epic.done_count)).replace(':total', String(epic.item_count))}</span>
                                        {epic.bug_count > 0 && <span>{epic.bug_count === 1 ? t('1 bug') : t(':count bugs').replace(':count', String(epic.bug_count))}</span>}
                                        {epic.points > 0 && <span>{t(':done of :total pts').replace(':done', String(epic.done_points)).replace(':total', String(epic.points))}</span>}
                                        <button
                                            type="button"
                                            className="ml-auto inline-flex items-center gap-1 font-medium text-foreground hover:text-primary"
                                            aria-expanded={isOpen}
                                            onClick={() => setExpanded(prev => ({ ...prev, [epic.id]: !prev[epic.id] }))}
                                        >
                                            {isOpen ? t('Hide items') : t('Show items')}
                                            <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', isOpen && 'rotate-180')} />
                                        </button>
                                    </div>
                                </div>

                                {isOpen && (
                                    <div className="border-t bg-muted/20">
                                        {epic.tasks.map(task => (
                                            <div key={task.id} className="flex items-center gap-2 border-b px-4 py-2 text-sm last:border-b-0">
                                                <TypeIcon type={task.type} />
                                                <button type="button" onClick={() => openTask(task.id)} className={cn('min-w-0 flex-1 truncate text-left hover:text-primary hover:underline', task.is_completed && 'text-muted-foreground line-through')}>
                                                    {task.title}
                                                </button>
                                                <StageChip stage={task.stage} />
                                                <AssigneeAvatar assignee={task.assignee} />
                                                {canFile && (
                                                    <Button variant="ghost" size="icon" className="h-7 w-7 text-muted-foreground" aria-label={t('Remove from :epic').replace(':epic', epic.name)} title={t('Remove from epic')} onClick={() => removeItem(epic, task.id)}>
                                                        <X className="h-3.5 w-3.5" />
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                        {epic.tasks.length === 0 && (
                                            <p className="px-4 py-3 text-sm text-muted-foreground">{t('No items in this epic yet.')}</p>
                                        )}
                                        {canFile && unfiled.length > 0 && (
                                            <div className="px-4 py-3">
                                                <Select value="" onValueChange={(value) => addItem(epic, value)}>
                                                    <SelectTrigger className="h-8 w-full gap-2 bg-background text-sm sm:w-80" aria-label={t('Add an item to :epic').replace(':epic', epic.name)}>
                                                        <Plus className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                        <span className="mr-auto text-muted-foreground">{t('Add an item')}</span>
                                                    </SelectTrigger>
                                                    <SelectContent searchable>
                                                        {(unfiled as { id: number; title: string; type: string }[]).map(item => (
                                                            <SelectItem key={item.id} value={String(item.id)}>
                                                                <span className="inline-flex items-center gap-2">
                                                                    <TypeIcon type={item.type} className="h-3.5 w-3.5" />
                                                                    {item.title}
                                                                </span>
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </article>
                        );
                    })}
                </div>
            )}

            <Dialog open={!!form} onOpenChange={(open) => !open && setForm(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{form?.epic ? t('Edit epic') : t('New epic')}</DialogTitle>
                    </DialogHeader>
                    {form && (
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="epic-name">{t('Name')} <span className="text-red-500">*</span></Label>
                                <Input id="epic-name" className="mt-1.5" value={form.values.name} placeholder={t('e.g. Checkout and payments')}
                                    onChange={(e) => setForm({ ...form, values: { ...form.values, name: e.target.value } })} />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="epic-description">{t('Description')}</Label>
                                <Textarea id="epic-description" className="mt-1.5" rows={3} value={form.values.description} placeholder={t('What this epic delivers')}
                                    onChange={(e) => setForm({ ...form, values: { ...form.values, description: e.target.value } })} />
                            </div>
                            <div>
                                <Label>{t('Colour')}</Label>
                                <div className="mt-1.5 flex flex-wrap gap-2" role="radiogroup" aria-label={t('Colour')}>
                                    {(colors as string[]).map(color => (
                                        <button key={color} type="button" role="radio" aria-checked={form.values.color === color} aria-label={color}
                                            className={cn('h-7 w-7 rounded-full ring-offset-2 ring-offset-background transition', form.values.color === color ? 'ring-2 ring-foreground' : 'hover:scale-110')}
                                            style={{ backgroundColor: color }}
                                            onClick={() => setForm({ ...form, values: { ...form.values, color } })} />
                                    ))}
                                </div>
                            </div>
                            <div>
                                <Label>{t('Owner')}</Label>
                                <Select value={form.values.owner_id} onValueChange={(value) => setForm({ ...form, values: { ...form.values, owner_id: value } })}>
                                    <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('No owner')}</SelectItem>
                                        {(team as { id: number; name: string }[]).map(person => (
                                            <SelectItem key={person.id} value={String(person.id)}>{person.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.owner_id && <p className="mt-1 text-sm text-red-600">{errors.owner_id}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="epic-start">{t('Start date')}</Label>
                                    <Input id="epic-start" type="date" className="mt-1.5" value={form.values.start_date}
                                        onChange={(e) => setForm({ ...form, values: { ...form.values, start_date: e.target.value } })} />
                                </div>
                                <div>
                                    <Label htmlFor="epic-end">{t('Target date')}</Label>
                                    <Input id="epic-end" type="date" className="mt-1.5" min={form.values.start_date || undefined} value={form.values.end_date}
                                        onChange={(e) => setForm({ ...form, values: { ...form.values, end_date: e.target.value } })} />
                                    {errors.end_date && <p className="mt-1 text-sm text-red-600">{errors.end_date}</p>}
                                </div>
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setForm(null)}>{t('Cancel')}</Button>
                        <Button onClick={saveForm} disabled={!form?.values.name.trim()}>{form?.epic ? t('Save') : t('Create epic')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <CrudDeleteModal
                isOpen={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => {
                    if (deleting) router.delete(route('epics.destroy', deleting.id), { ...options, onFinish: () => setDeleting(null) });
                }}
                itemName={deleting?.name || ''}
                entityName={t('epic')}
            />
            {taskDialog}
        </ProjectShell>
    );
}
