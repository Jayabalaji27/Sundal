import React, { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Bug, Check, ChevronDown } from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { Task, Project, ProjectMilestone, User } from '@/types';
import { useTranslation } from 'react-i18next';
import { Label } from '../ui/label';
import { cn } from '@/lib/utils';
import { SEVERITY_OPTIONS, WorkType } from './agile-bits';

// Fibonacci sizes, as in Jira / Zoho Sprints.
export const STORY_POINT_OPTIONS = [1, 2, 3, 5, 8, 13, 21];

interface Props {
    isOpen: boolean;
    onClose: () => void;
    task?: Task;
    projects: Project[];
    members: User[];
    milestones?: ProjectMilestone[];
    googleCalendarEnabled?: boolean;
    initialProjectId?: string;
    /** Open a new item as a bug ("Report bug") rather than a task. */
    initialType?: WorkType;
}

const BUG_DEFAULTS = {
    severity: 'major',
    steps_to_reproduce: '',
    expected_behavior: '',
    actual_behavior: '',
    environment: '',
    resolution_notes: '',
};

const emptyForm = (projectId: string, type: WorkType) => ({
    type,
    project_id: projectId,
    milestone_id: 'none',
    epic_id: 'none',
    title: '',
    description: '',
    priority: 'medium',
    story_points: 'none',
    start_date: '',
    end_date: '',
    assigned_to: 'none',
    is_googlecalendar_sync: false,
    ...BUG_DEFAULTS,
});

/**
 * One form for all work: a task, or a bug (a task of type "bug" with a
 * severity and reproduction details). Both land on the same Board, Backlog
 * and Sprints.
 */
export default function TaskFormModal({ isOpen, onClose, task, projects, members, milestones = [], googleCalendarEnabled = false, initialProjectId, initialType = 'task' }: Props) {
    const { t } = useTranslation();
    const isEditing = !!task;
    const [formData, setFormData] = useState(() => emptyForm(task?.project_id?.toString() || initialProjectId || '', (task?.type as WorkType) || initialType));

    const [currentMilestones, setCurrentMilestones] = useState<ProjectMilestone[]>([]);
    // Open epics of the chosen project (sent with the project as `epics`).
    const [currentEpics, setCurrentEpics] = useState<{ id: number; name: string; color: string }[]>([]);
    const [currentMembers, setCurrentMembers] = useState<User[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showMoreBugDetails, setShowMoreBugDetails] = useState(false);
    const titleRef = useRef<HTMLInputElement>(null);

    // Reset form only when the modal opens (or the task being edited changes) —
    // not on every parent re-render. A failed-validation submit triggers an
    // Inertia partial reload that gives `projects`/`members` new array
    // references, and depending on those here would silently wipe the form
    // the user just typed into.
    useEffect(() => {
        if (!isOpen) return;
        setErrors({});
        if (task) {
            // Editing mode - populate with task data
            const project = projects.find(p => p.id === task.project_id);
            setCurrentMilestones(project?.milestones || []);
            setCurrentEpics((project as any)?.epics || []);
            const projectMembers = project?.members?.filter(member => member.user?.type !== 'client').map(member => member.user) || [];
            setCurrentMembers(projectMembers.length > 0 ? projectMembers : members);

            setFormData({
                type: (task.type as WorkType) || 'task',
                project_id: task.project_id?.toString() || '',
                milestone_id: task.milestone_id?.toString() || 'none',
                epic_id: (task as any).epic_id?.toString() || 'none',
                title: task.title,
                description: task.description || '',
                priority: task.priority,
                story_points: task.story_points != null ? String(task.story_points) : 'none',
                start_date: task.start_date?.split('T')[0] || '',
                end_date: task.end_date?.split('T')[0] || '',
                assigned_to: task.assigned_to?.id?.toString() || 'none',
                is_googlecalendar_sync: task.is_googlecalendar_sync || false,
                severity: task.severity || 'major',
                steps_to_reproduce: task.steps_to_reproduce || '',
                expected_behavior: task.expected_behavior || '',
                actual_behavior: task.actual_behavior || '',
                environment: task.environment || '',
                resolution_notes: task.resolution_notes || '',
            });
            setShowMoreBugDetails(!!(task.expected_behavior || task.actual_behavior || task.environment || task.resolution_notes));
        } else {
            // Create mode - reset to defaults, pre-selecting the project we were
            // deep-linked from (e.g. "Create First Task" from a project's Tasks tab)
            setFormData(emptyForm(initialProjectId || '', initialType));
            setShowMoreBugDetails(false);

            const initialProject = initialProjectId ? projects.find(p => p.id.toString() === initialProjectId) : undefined;
            setCurrentMilestones(initialProject?.milestones || []);
            setCurrentEpics((initialProject as any)?.epics || []);
            const initialProjectMembers = initialProject?.members?.filter(member => member.user?.type !== 'client').map(member => member.user) || [];
            setCurrentMembers(initialProject ? (initialProjectMembers.length > 0 ? initialProjectMembers : members) : []);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isOpen, task?.id, initialProjectId, initialType]);

    const isBug = formData.type === 'bug';
    const set = (changes: Partial<typeof formData>) => setFormData(prev => ({ ...prev, ...changes }));

    // Keep a value set elsewhere (e.g. 4) selectable even though it's not a Fibonacci size.
    const pointOptions = formData.story_points !== 'none' && !STORY_POINT_OPTIONS.includes(Number(formData.story_points))
        ? [...STORY_POINT_OPTIONS, Number(formData.story_points)].sort((a, b) => a - b)
        : STORY_POINT_OPTIONS;

    const handleProjectChange = (projectId: string) => {
        set({ project_id: projectId, milestone_id: 'none', epic_id: 'none', assigned_to: 'none' });

        const project = projects.find(p => p.id.toString() === projectId);

        // Load project milestones
        const milestones = project?.milestones || [];
        setCurrentMilestones(milestones);
        setCurrentEpics((project as any)?.epics || []);

        // Load project members (not clients)
        const projectMembers = project?.members?.filter(member => member.user?.type !== 'client').map(member => member.user) || [];
        setCurrentMembers(projectMembers.length > 0 ? projectMembers : members);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isSubmitting) return;

        setIsSubmitting(true);
        setErrors({});

        const submitData = {
            ...formData,
            milestone_id: formData.milestone_id === 'none' ? '' : formData.milestone_id,
            epic_id: formData.epic_id === 'none' ? '' : formData.epic_id,
            assigned_to: formData.assigned_to === 'none' ? '' : formData.assigned_to,
            story_points: formData.story_points === 'none' ? '' : formData.story_points,
            // A task carries no bug details (the server clears them too).
            ...(isBug ? {} : Object.fromEntries(Object.keys(BUG_DEFAULTS).map(key => [key, '']))),
        };

        const options = {
            onSuccess: () => {
                setIsSubmitting(false);
                onClose();
            },
            onError: (errors: Record<string, string>) => {
                setIsSubmitting(false);
                setErrors(errors);
            },
        };

        if (isEditing) {
            router.put(route('tasks.update', task.id), submitData, options);
        } else {
            router.post(route('tasks.store'), submitData, options);
        }
    };

    const title = isEditing
        ? (isBug ? t('Edit Bug') : t('Edit Task'))
        : (isBug ? t('Report Bug') : t('Create Task'));

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            {/* Start in the title, so the Task / Bug switch doesn't look picked by focus. */}
            <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto" onOpenAutoFocus={(event) => { event.preventDefault(); titleRef.current?.focus(); }}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Task or bug: both live on the same Board, Backlog and Sprints. */}
                    <div role="radiogroup" aria-label={t('Type')} className="inline-flex rounded-lg border bg-muted/40 p-1">
                        {([
                            { value: 'task', label: t('Task'), icon: Check, tone: 'bg-sky-500' },
                            { value: 'bug', label: t('Bug'), icon: Bug, tone: 'bg-red-500' },
                        ] as const).map(option => {
                            const active = formData.type === option.value;
                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    role="radio"
                                    aria-checked={active}
                                    onClick={() => set({ type: option.value })}
                                    className={cn(
                                        'inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring',
                                        active ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <span className={cn('inline-flex h-4 w-4 items-center justify-center rounded-[4px] text-white', option.tone, !active && 'opacity-60')}>
                                        <option.icon className="h-3 w-3" strokeWidth={option.value === 'bug' ? 2.25 : 3} />
                                    </span>
                                    {option.label}
                                </button>
                            );
                        })}
                    </div>

                    {!isEditing && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Project')} <span className="text-red-500">*</span> <span className="text-xs text-gray-500">({t('Select to load milestones & team members')})</span>
                            </label>
                            <Select value={formData.project_id || ''} onValueChange={handleProjectChange}>
                                <SelectTrigger className={errors.project_id ? 'border-red-500' : ''}>
                                    <SelectValue placeholder={t('Select a project')} />
                                </SelectTrigger>
                                <SelectContent searchable className="z-[9999]">
                                    {projects.map((project) => (
                                        <SelectItem key={project.id} value={project.id.toString()}>
                                            {project.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.project_id && <p className="text-sm text-red-600 mt-1">{errors.project_id}</p>}
                        </div>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            {t('Title')} <span className="text-red-500">*</span>
                        </label>
                        <Input
                            ref={titleRef}
                            value={formData.title}
                            onChange={(e) => set({ title: e.target.value })}
                            placeholder={isBug ? t('e.g. Checkout button does nothing on mobile') : t('Enter task title')}
                            className={errors.title ? 'border-red-500' : ''}
                        />
                        {errors.title && <p className="text-sm text-red-600 mt-1">{errors.title}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            {t('Description')}
                        </label>
                        <Textarea
                            value={formData.description}
                            onChange={(e) => set({ description: e.target.value })}
                            placeholder={isBug ? t("What's wrong, and who is affected?") : t('Describe the task...')}
                            rows={3}
                        />
                    </div>

                    {isBug && (
                        <div className="space-y-4 rounded-lg border border-red-200/70 bg-red-50/40 p-4 dark:border-red-900/50 dark:bg-red-950/20">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    {t('Severity')}
                                </label>
                                {/* How bad it is (priority says how soon to fix it). */}
                                <div role="radiogroup" aria-label={t('Severity')} className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    {SEVERITY_OPTIONS.map(option => {
                                        const active = formData.severity === option.value;
                                        return (
                                            <button
                                                key={option.value}
                                                type="button"
                                                role="radio"
                                                aria-checked={active}
                                                onClick={() => set({ severity: option.value })}
                                                className={cn(
                                                    'rounded-md border bg-background px-3 py-2 text-left transition-colors',
                                                    active ? 'border-red-500 ring-1 ring-red-500' : 'hover:border-foreground/30',
                                                )}
                                            >
                                                <span className="block text-sm font-medium">{t(option.label)}</span>
                                                <span className="block text-xs leading-snug text-muted-foreground">{t(option.hint)}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                                {errors.severity && <p className="text-sm text-red-600 mt-1">{errors.severity}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    {t('Steps to reproduce')}
                                </label>
                                <Textarea
                                    value={formData.steps_to_reproduce}
                                    onChange={(e) => set({ steps_to_reproduce: e.target.value })}
                                    placeholder={t('1. Go to …\n2. Click …\n3. See the error')}
                                    rows={3}
                                    className="bg-background"
                                />
                            </div>

                            <button
                                type="button"
                                onClick={() => setShowMoreBugDetails(open => !open)}
                                aria-expanded={showMoreBugDetails}
                                className="inline-flex items-center gap-1 text-sm font-medium text-muted-foreground hover:text-foreground"
                            >
                                <ChevronDown className={cn('h-4 w-4 transition-transform', showMoreBugDetails && 'rotate-180')} />
                                {isEditing ? t('Expected, actual, environment and resolution') : t('Expected, actual and environment')}
                            </button>

                            {showMoreBugDetails && (
                                <div className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">{t('Expected result')}</label>
                                            <Textarea value={formData.expected_behavior} onChange={(e) => set({ expected_behavior: e.target.value })} rows={2} className="bg-background" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">{t('Actual result')}</label>
                                            <Textarea value={formData.actual_behavior} onChange={(e) => set({ actual_behavior: e.target.value })} rows={2} className="bg-background" />
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">{t('Environment')}</label>
                                        <Input
                                            value={formData.environment}
                                            onChange={(e) => set({ environment: e.target.value })}
                                            placeholder={t('e.g. Chrome 129 on Windows, staging')}
                                            className="bg-background"
                                        />
                                        {errors.environment && <p className="text-sm text-red-600 mt-1">{errors.environment}</p>}
                                    </div>
                                    {isEditing && (
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">{t('Resolution notes')}</label>
                                            <Textarea
                                                value={formData.resolution_notes}
                                                onChange={(e) => set({ resolution_notes: e.target.value })}
                                                placeholder={t('What was fixed, and how')}
                                                rows={2}
                                                className="bg-background"
                                            />
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Priority')}
                            </label>
                            <Select value={formData.priority} onValueChange={(value) => set({ priority: value })}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="z-[9999]">
                                    <SelectItem value="low">{t('Low')}</SelectItem>
                                    <SelectItem value="medium">{t('Medium')}</SelectItem>
                                    <SelectItem value="high">{t('High')}</SelectItem>
                                    <SelectItem value="critical">{t('Critical')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2" title={t('How big the task is; sprints add these up')}>
                                {t('Story points')}
                            </label>
                            <Select value={formData.story_points} onValueChange={(value) => set({ story_points: value })}>
                                <SelectTrigger className={errors.story_points ? 'border-red-500' : ''}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="z-[9999]">
                                    <SelectItem value="none">{t('Not estimated')}</SelectItem>
                                    {pointOptions.map((points) => (
                                        <SelectItem key={points} value={String(points)}>{points}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.story_points && <p className="text-sm text-red-600 mt-1">{errors.story_points}</p>}
                        </div>

                        <div className="col-span-2 sm:col-span-1">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Assign to')}
                            </label>
                            <Select
                                value={formData.assigned_to}
                                onValueChange={(value) => set({ assigned_to: value })}
                                disabled={!formData.project_id}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={!formData.project_id ? t('Select project first') : currentMembers.length > 0 ? t('Select assignee') : t('No team members available')} />
                                </SelectTrigger>
                                <SelectContent className="z-[9999]">
                                    <SelectItem value="none">{t('Unassigned')}</SelectItem>
                                    {currentMembers.map((member) => (
                                        <SelectItem key={member.id} value={member.id.toString()}>
                                            {member.name} {member.type !== 'client' ? t('(Team)') : t('(Client)')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Start Date')}
                            </Label>
                            <Input
                                type="date"
                                value={formData.start_date}
                                onChange={(e) => set({ start_date: e.target.value })}
                            />
                        </div>

                        <div>
                            <Label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Due Date')}
                            </Label>
                            <Input
                                type="date"
                                value={formData.end_date}
                                min={formData.start_date}
                                onChange={(e) => set({ end_date: e.target.value })}
                            />
                            {errors.end_date && <p className="text-sm text-red-600 mt-1">{errors.end_date}</p>}
                        </div>
                    </div>

                    {formData.project_id && (currentMilestones.length > 0 || currentEpics.length > 0) && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            {currentEpics.length > 0 && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        {t('Epic')}
                                    </label>
                                    <Select value={formData.epic_id} onValueChange={(value) => set({ epic_id: value })}>
                                        <SelectTrigger className={errors.epic_id ? 'border-red-500' : ''}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent searchable className="z-[9999]">
                                            <SelectItem value="none">{t('No epic')}</SelectItem>
                                            {currentEpics.map((epic) => (
                                                <SelectItem key={epic.id} value={epic.id.toString()}>
                                                    <span className="inline-flex items-center gap-2">
                                                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: epic.color }} />
                                                        {epic.name}
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.epic_id && <p className="text-sm text-red-600 mt-1">{errors.epic_id}</p>}
                                </div>
                            )}
                            {currentMilestones.length > 0 && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        {t('Milestone')}
                                    </label>
                                    <Select value={formData.milestone_id} onValueChange={(value) => set({ milestone_id: value })}>
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Select a milestone (optional)')} />
                                        </SelectTrigger>
                                        <SelectContent searchable className="z-[9999]">
                                            <SelectItem value="none">{t('No milestone')}</SelectItem>
                                            {currentMilestones.map((milestone) => (
                                                <SelectItem key={milestone.id} value={milestone.id.toString()}>
                                                    {milestone.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </div>
                    )}

                    {googleCalendarEnabled && !isEditing && (
                        <div className="flex items-center space-x-2">
                            <Switch
                                id="google-calendar-sync"
                                checked={formData.is_googlecalendar_sync}
                                onCheckedChange={(checked) => set({ is_googlecalendar_sync: checked })}
                            />
                            <label htmlFor="google-calendar-sync" className="text-sm font-medium text-gray-700">
                                {t('Sync with Google Calendar')}
                            </label>
                        </div>
                    )}

                    <div className="flex justify-end space-x-2 pt-4">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isEditing ? t('Update') : isBug ? t('Report bug') : t('Create')}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
