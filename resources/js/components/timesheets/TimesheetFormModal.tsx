import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Calendar, Save, X, Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

// Mirrors the backend's Monday-Sunday week convention (Carbon's default) so the
// displayed "end of week" always matches what the server will actually store —
// entries here are all dated to `start_date` (see handleSubmit), so this is only
// for showing which week they'll be filed under.
function toLocalDateStr(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function getWeekEnd(dateStr: string): string {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    const day = d.getDay();
    d.setDate(d.getDate() + ((7 - day) % 7));
    return toLocalDateStr(d);
}

interface Timesheet {
    id?: number;
    start_date: string;
    end_date: string;
    notes?: string;
    entries?: TimesheetEntry[];
    created_at?: string;
    updated_at?: string;
}

interface TimesheetEntry {
    id?: number;
    project_id: string;
    task_id?: string;
    date: string;
    start_time: string;
    end_time: string;
    hours?: number;
    description: string;
    is_billable?: boolean;
    hourly_rate?: number;
    created_at?: string;
    updated_at?: string;
}

interface Project {
    id: number;
    title: string;
    tasks?: Task[];
}

interface Task {
    id: number;
    title: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    timesheet?: Timesheet;
    projects?: Project[];
}

export default function TimesheetFormModal({ isOpen, onClose, timesheet, projects = [] }: Props) {
    const { t } = useTranslation();

    const [formData, setFormData] = useState({
        start_date: '',
        end_date: '',
        notes: ''
    });
    const [entries, setEntries] = useState<TimesheetEntry[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (timesheet && isOpen) {
            if (timesheet.id && !timesheet.entries) {
                // Fetch full timesheet data with entries
                fetch(route('timesheets.show', timesheet.id))
                    .then(response => response.json())
                    .then(data => {
                        const startDate = window.appSettings?.formatDateForInput(data.timesheet.start_date) || '';
                        const endDate = window.appSettings?.formatDateForInput(data.timesheet.end_date) || '';
                        setFormData({
                            start_date: startDate,
                            end_date: endDate,
                            notes: data.timesheet.notes || ''
                        });
                        setEntries(data.timesheet.entries?.map((entry: any) => ({
                            id: entry.id,
                            project_id: entry.project_id?.toString() || '',
                            task_id: entry.task_id?.toString() || 'none',
                            date: entry.date || data.timesheet.start_date,
                            start_time: entry.start_time ? entry.start_time.substring(0, 5) : '',
                            end_time: entry.end_time ? entry.end_time.substring(0, 5) : '',
                            hours: entry.hours || 0,
                            description: entry.description || '',
                            is_billable: entry.is_billable || true,
                            hourly_rate: entry.hourly_rate || 0,
                            created_at: entry.created_at,
                            updated_at: entry.updated_at
                        })) || []);
                    });
            } else {
                const startDate = window.appSettings?.formatDateForInput(timesheet.start_date) || '';
                const endDate = window.appSettings?.formatDateForInput(timesheet.end_date) || '';
                setFormData({
                    start_date: startDate,
                    end_date: endDate,
                    notes: timesheet.notes || ''
                });
                setEntries(timesheet.entries?.map((entry: any) => ({
                    id: entry.id,
                    project_id: entry.project_id?.toString() || '',
                    task_id: entry.task_id?.toString() || 'none',
                    date: entry.date || timesheet.start_date,
                    start_time: entry.start_time ? entry.start_time.substring(0, 5) : '',
                    end_time: entry.end_time ? entry.end_time.substring(0, 5) : '',
                    hours: entry.hours || 0,
                    description: entry.description || '',
                    is_billable: entry.is_billable || true,
                    hourly_rate: entry.hourly_rate || 0,
                    created_at: entry.created_at,
                    updated_at: entry.updated_at
                })) || []);
            }
        } else if (isOpen) {
            setFormData({
                start_date: '',
                end_date: '',
                notes: ''
            });
            setEntries([]);
        }
    }, [timesheet, isOpen]);

    const addEntry = () => {
        const now = new Date();
        const startTime = new Date(now.getTime());
        const endTime = new Date(now.getTime() + 8 * 60 * 60 * 1000); // 8 hours later

        const newEntry: TimesheetEntry = {
            project_id: '',
            task_id: 'none',
            date: formData.start_date,
            start_time: '09:00',
            end_time: '17:00',
            hours: 0,
            description: '',
            is_billable: true,
            hourly_rate: 0
        };
        setEntries([...entries, newEntry]);
    };

    const removeEntry = (index: number) => {
        setEntries(entries.filter((_, i) => i !== index));
    };

    const updateEntry = (index: number, field: keyof TimesheetEntry, value: any) => {
        const updatedEntries = [...entries];
        updatedEntries[index] = { ...updatedEntries[index], [field]: value };
        setEntries(updatedEntries);
    };

    const getTasksForProject = (projectId: string) => {
        const project = projects.find(p => p.id.toString() === projectId);
        return project?.tasks || [];
    };

    const getUsedTaskIds = (currentIndex: number, projectId: string): Set<string> => {
        return new Set(
            entries
                .filter((e, i) => i !== currentIndex && e.project_id?.toString() === projectId && e.task_id && e.task_id.toString() !== 'none')
                .map(e => e.task_id!.toString())
        );
    };

    const getAvailableProjects = (currentIndex: number) => {
        return projects.filter(project => {
            const tasks = project.tasks || [];
            // Always keep the currently selected project visible
            if (project.id.toString() === entries[currentIndex]?.project_id?.toString()) return true;
            if (tasks.length === 0) return true;
            const usedTaskIds = getUsedTaskIds(currentIndex, project.id.toString());
            return tasks.some(t => !usedTaskIds.has(t.id.toString()));
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});

        const validationErrors: Record<string, string> = {};

        // Validation
        if (!formData.start_date) {
            validationErrors.start_date = 'The date field is required.';
        }
        if (entries.length === 0) {
            validationErrors.entries = 'Please add at least one time entry.';
        }

        for (let i = 0; i < entries.length; i++) {
            const entry = entries[i];
            if (!entry.project_id) {
                validationErrors[`entries.${i}.project_id`] = 'The project field is required.';
            }
            if (!entry.task_id || entry.task_id === 'none') {
                validationErrors[`entries.${i}.task_id`] = 'The task field is required.';
            }
            if (!entry.start_time) {
                validationErrors[`entries.${i}.start_time`] = 'The start time field is required.';
            }
            if (!entry.end_time) {
                validationErrors[`entries.${i}.end_time`] = 'The end time field is required.';
            }
        }

        if (Object.keys(validationErrors).length > 0) {
            setErrors(validationErrors);
            return;
        }

        setIsSubmitting(true);

        const submitData = {
            ...formData,
            entries: entries.map(entry => ({
                ...entry,
                task_id: entry.task_id === 'none' ? null : entry.task_id,
                start_time: entry.start_time ? `${formData.start_date}T${entry.start_time}:00` : '',
                end_time: entry.end_time ? `${formData.start_date}T${entry.end_time}:00` : ''
            }))
        };

        console.log('Submitting data:', submitData);

        const url = timesheet
            ? route('timesheets.update', timesheet.id)
            : route('timesheets.store');

        const method = timesheet ? 'put' : 'post';

        router[method](url, submitData, {
            onSuccess: () => {
                onClose();
                setFormData({ start_date: '', end_date: '', notes: '' });
                setEntries([]);
            },
            onError: (backendErrors) => {
                console.log('Backend errors:', backendErrors);
                if (typeof backendErrors === 'object') {
                    const formattedErrors: Record<string, string> = {};
                    Object.keys(backendErrors).forEach(key => {
                        if (Array.isArray(backendErrors[key])) {
                            formattedErrors[key] = backendErrors[key][0];
                        } else {
                            formattedErrors[key] = backendErrors[key];
                        }
                    });
                    setErrors(formattedErrors);
                }
            },
            onFinish: () => setIsSubmitting(false)
        });
    };

    const handleChange = (field: string, value: string) => {
        if (field === 'start_date') {
            setFormData(prev => ({ ...prev, start_date: value, end_date: getWeekEnd(value) }));
            return;
        }
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Calendar className="h-5 w-5 text-primary" />
                        {timesheet ? 'Edit Timesheet' : 'New Timesheet'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Timesheet Info */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="start_date">{t('Date')} <span className="text-red-500">*</span></Label>
                            <Input
                                id="start_date"
                                type="date"
                                value={formData.start_date}
                                onChange={(e) => handleChange('start_date', e.target.value)}
                                className={errors.start_date ? 'border-red-500' : ''}
                            />
                            {errors.start_date && <p className="text-red-500 text-sm">{errors.start_date}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="end_date">{t('Week Ending')}</Label>
                            <Input
                                id="end_date"
                                type="date"
                                value={formData.end_date}
                                disabled
                                className="bg-muted"
                            />
                            <p className="text-xs text-muted-foreground">{t('Computed automatically — entries are always filed under a full Monday-Sunday week.')}</p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">{t('Notes')}</Label>
                        <Textarea
                            id="notes"
                            placeholder={t('Add any notes about this timesheet...')}
                            value={formData.notes}
                            onChange={(e) => handleChange('notes', e.target.value)}
                            rows={2}
                        />
                    </div>



                    {/* Time Entries */}
                    <div className="space-y-4">
                        <div className="flex justify-between items-center">
                            <Label className="text-base font-semibold">{t('Time Entries')}</Label>
                            <Button type="button" onClick={addEntry} size="sm">
                                <Plus className="h-4 w-4 mr-2" />
                                {t('Add Entry')}
                            </Button>
                        </div>
                        {errors.entries && <p className="text-red-500 text-sm">{errors.entries}</p>}

                        {entries.map((entry, index) => (
                            <div key={index} className="border rounded-lg p-4 space-y-4">
                                <div className="flex justify-between items-center">
                                    <span className="font-medium">{t('Entry')} {index + 1}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeEntry(index)}
                                        className="text-red-600 hover:text-red-700"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>{t('Project')} <span className="text-red-500">*</span></Label>
                                        <Select
                                            value={entry.project_id}
                                            onValueChange={(value) => updateEntry(index, 'project_id', value)}
                                            required
                                        >
                                            <SelectTrigger className={errors[`entries.${index}.project_id`] ? 'border-red-500' : ''}>
                                                <SelectValue placeholder="Select project" />
                                            </SelectTrigger>
                                            <SelectContent searchable side="bottom" align="start" sideOffset={4} style={{ zIndex: 999999 }}>
                                                {getAvailableProjects(index).map(project => (
                                                    <SelectItem key={project.id} value={project.id.toString()}>
                                                        {project.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors[`entries.${index}.project_id`] && <p className="text-red-500 text-sm">{errors[`entries.${index}.project_id`]}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('Task')} <span className="text-red-500">*</span></Label>
                                        <Select
                                            value={entry.task_id || 'none'}
                                            onValueChange={(value) => updateEntry(index, 'task_id', value)}
                                            required
                                        >
                                            <SelectTrigger className={errors[`entries.${index}.task_id`] ? 'border-red-500' : ''}>
                                                <SelectValue placeholder="Select task" />
                                            </SelectTrigger>
                                            <SelectContent searchable side="bottom" align="start" sideOffset={4} style={{ zIndex: 999999 }}>
                                                <SelectItem value="none">{t('No task')}</SelectItem>
                                                {getTasksForProject(entry.project_id)
                                                    .filter(task => {
                                                        const taskIdStr = task.id.toString();
                                                        // Always show the task currently selected in this entry
                                                        if (taskIdStr === entry.task_id?.toString()) return true;
                                                        return !getUsedTaskIds(index, entry.project_id).has(taskIdStr);
                                                    })
                                                    .map(task => (
                                                        <SelectItem key={task.id} value={task.id.toString()}>
                                                            {task.title}
                                                        </SelectItem>
                                                    ))}
                                            </SelectContent>
                                        </Select>
                                        {errors[`entries.${index}.task_id`] && <p className="text-red-500 text-sm">{errors[`entries.${index}.task_id`]}</p>}
                                    </div>
                                </div>



                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>{t('Start Time')} <span className="text-red-500">*</span></Label>
                                        <Input
                                            type="time"
                                            value={entry.start_time || ''}
                                            onChange={(e) => updateEntry(index, 'start_time', e.target.value)}
                                            className={errors[`entries.${index}.start_time`] ? 'border-red-500' : ''}
                                            required
                                        />
                                        {errors[`entries.${index}.start_time`] && <p className="text-red-500 text-sm">{errors[`entries.${index}.start_time`]}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>{t('End Time')} <span className="text-red-500">*</span></Label>
                                        <Input
                                            type="time"
                                            value={entry.end_time || ''}
                                            onChange={(e) => updateEntry(index, 'end_time', e.target.value)}
                                            className={errors[`entries.${index}.end_time`] ? 'border-red-500' : ''}
                                            required
                                        />
                                        {errors[`entries.${index}.end_time`] && <p className="text-red-500 text-sm">{errors[`entries.${index}.end_time`]}</p>}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('Description')}</Label>
                                    <Textarea
                                        placeholder="Describe the work done..."
                                        value={entry.description}
                                        onChange={(e) => updateEntry(index, 'description', e.target.value)}
                                        rows={2}
                                    />
                                </div>
                            </div>
                        ))}

                        {entries.length === 0 && (
                            <div className="text-center py-8 text-muted-foreground">
                                <Calendar className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>{t('No time entries added yet')}</p>
                                <Button type="button" onClick={addEntry} className="mt-2">
                                    <Plus className="h-4 w-4 mr-2" />
                                    {t('Add First Entry')}
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit">
                            {timesheet ? t('Update') : t('Create')}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
