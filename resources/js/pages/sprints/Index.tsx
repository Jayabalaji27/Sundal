import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Play, CheckCircle2, Inbox, Eye, Pencil, Trash2, ArrowRight } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';

const STATUS_COLOR: Record<string, string> = {
    planning:  'bg-blue-100 text-blue-700',
    active:    'bg-green-100 text-green-700',
    completed: 'bg-gray-100 text-gray-600',
};

const PRIORITY_COLOR: Record<string, string> = {
    urgent: 'bg-red-100 text-red-700',
    high:   'bg-orange-100 text-orange-700',
    medium: 'bg-blue-100 text-blue-700',
    low:    'bg-slate-100 text-slate-600',
};

export default function SprintsIndex() {
    const { t } = useTranslation();
    const { project, sprints, backlog, stages, flash } = usePage().props as any;

    const [modal, setModal] = useState(false);
    const [deleteModal, setDeleteModal] = useState(false);
    const [current, setCurrent] = useState<any>(null);
    const [name, setName] = useState('');
    const [goal, setGoal] = useState('');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);

    const openEdit = (s: any) => {
        setCurrent(s);
        setName(s.name); setGoal(s.goal || '');
        setStartDate(s.start_date || ''); setEndDate(s.end_date || '');
        setModal(true);
    };
    const save = () => {
        const data = { name, goal, start_date: startDate, end_date: endDate };
        router.put(route('sprints.update', current.id), data, { onSuccess: () => setModal(false), onError: e => toast.error(Object.values(e)[0] as string) });
    };

    const addToSprint = (sprintId: number, taskId: number) =>
        router.post(route('sprints.add-task', sprintId), { task_id: taskId }, { preserveScroll: true });

    return (
        <PageTemplate
            title={`${t('Sprints')} — ${project.title}`}
            description={t('Plan sprints, manage backlog, track velocity.')}
            url={route('sprints.index', project.id)}
            breadcrumbs={[
                { title: t('Projects'), href: route('projects.index') },
                { title: project.title, href: route('projects.show', project.id) },
                { title: t('Sprints'), href: route('sprints.index', project.id) },
            ]}
        >
            <div className="grid gap-6 lg:grid-cols-3">
                {/* Sprints list */}
                <div className="lg:col-span-2 space-y-4">
                    {sprints.length === 0 && (
                        <div className="flex flex-col items-center rounded-xl border-2 border-dashed py-16 text-center">
                            <Play className="mb-3 h-10 w-10 text-muted-foreground" />
                            <p className="font-medium text-muted-foreground">{t('No sprints yet')}</p>
                        </div>
                    )}
                    {sprints.map((s: any) => (
                        <Card key={s.id} className={s.status === 'active' ? 'border-green-300 shadow-md' : ''}>
                            <CardHeader className="pb-2">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <CardTitle className="text-base">{s.name}</CardTitle>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLOR[s.status]}`}>{t(s.status)}</span>
                                        </div>
                                        {s.goal && <p className="mt-1 text-sm text-muted-foreground">{s.goal}</p>}
                                        {(s.start_date || s.end_date) && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {s.start_date && new Date(s.start_date).toLocaleDateString()} {s.end_date && `→ ${new Date(s.end_date).toLocaleDateString()}`}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex gap-1">
                                        {s.status === 'planning' && (
                                            <Button size="sm" variant="outline" className="h-7 gap-1 text-xs"
                                                onClick={() => router.post(route('sprints.start', s.id))}>
                                                <Play className="h-3 w-3" /> {t('Start')}
                                            </Button>
                                        )}
                                        {s.status === 'active' && (
                                            <Button size="sm" variant="outline" className="h-7 gap-1 text-xs"
                                                onClick={() => router.post(route('sprints.complete', s.id))}>
                                                <CheckCircle2 className="h-3 w-3" /> {t('Complete')}
                                            </Button>
                                        )}
                                        <Button size="icon" variant="ghost" className="h-7 w-7"
                                            onClick={() => router.get(route('sprints.show', s.id))}>
                                            <Eye className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button size="icon" variant="ghost" className="h-7 w-7" onClick={() => openEdit(s)}>
                                            <Pencil className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button size="icon" variant="ghost" className="h-7 w-7 text-destructive hover:text-destructive"
                                            onClick={() => { setCurrent(s); setDeleteModal(true); }}>
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-0">
                                <p className="text-xs text-muted-foreground">{s.tasks_count} {t('tasks')}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Backlog */}
                <div>
                    <div className="mb-3 flex items-center gap-2">
                        <Inbox className="h-4 w-4 text-muted-foreground" />
                        <h2 className="font-semibold">{t('Backlog')} ({backlog.length})</h2>
                    </div>
                    {backlog.length === 0 ? (
                        <p className="text-sm text-muted-foreground italic">{t('All tasks are in sprints.')}</p>
                    ) : (
                        <div className="space-y-2">
                            {backlog.map((task: any) => (
                                <div key={task.id} className="rounded-lg border bg-card p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="text-sm font-medium leading-snug">{task.title}</p>
                                        {task.priority && (
                                            <span className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium ${PRIORITY_COLOR[task.priority] || ''}`}>
                                                {task.priority}
                                            </span>
                                        )}
                                    </div>
                                    {task.task_stage && (
                                        <p className="mt-1 text-xs text-muted-foreground">{task.task_stage.name}</p>
                                    )}
                                    {/* Add to sprint buttons */}
                                    {sprints.filter((s: any) => s.status !== 'completed').length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            {sprints.filter((s: any) => s.status !== 'completed').map((s: any) => (
                                                <button key={s.id}
                                                    onClick={() => addToSprint(s.id, task.id)}
                                                    className="flex items-center gap-1 rounded border px-2 py-0.5 text-[10px] hover:bg-primary hover:text-primary-foreground transition-colors">
                                                    <ArrowRight className="h-2.5 w-2.5" /> {s.name}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Sprint form modal */}
            <Dialog open={modal} onOpenChange={setModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{t('Edit Sprint')}</DialogTitle></DialogHeader>
                    <div className="space-y-3 py-2">
                        <div><Label>{t('Sprint Name')} *</Label><Input value={name} onChange={e => setName(e.target.value)} placeholder="Sprint 1" className="mt-1" /></div>
                        <div><Label>{t('Goal')}</Label><Textarea value={goal} onChange={e => setGoal(e.target.value)} placeholder="What do we aim to achieve?" className="mt-1" rows={2} /></div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><Label>{t('Start Date')}</Label><Input type="date" value={startDate} onChange={e => setStartDate(e.target.value)} className="mt-1" /></div>
                            <div><Label>{t('End Date')}</Label><Input type="date" value={endDate} onChange={e => setEndDate(e.target.value)} className="mt-1" /></div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setModal(false)}>{t('Cancel')}</Button>
                        <Button onClick={save} disabled={!name.trim()}>{t('Save')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EnhancedDeleteModal isOpen={deleteModal} onClose={() => setDeleteModal(false)}
                onConfirm={() => { if (current) router.delete(route('sprints.destroy', current.id), { onSuccess: () => setDeleteModal(false) }); }}
                title={t('Delete Sprint')} description={t('Tasks will be moved back to the backlog.')}
                itemName={current?.name || ''} />
        </PageTemplate>
    );
}
