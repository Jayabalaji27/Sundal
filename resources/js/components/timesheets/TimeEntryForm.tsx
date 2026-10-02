import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Clock, Save, X } from 'lucide-react';

interface Project {
    id: number;
    title: string;
    tasks?: Task[];
}

interface Task {
    id: number;
    title: string;
}

interface TimeEntry {
    id?: number;
    timesheet_id: number;
    project_id: number;
    task_id?: number;
    date: string;
    start_time?: string;
    end_time?: string;
    hours: number;
    description?: string;
    is_billable: boolean;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    timeEntry?: TimeEntry;
    timesheetId: number;
    projects: Project[];
    selectedDate?: string;
}

export default function TimeEntryForm({ isOpen, onClose, timeEntry, timesheetId, projects, selectedDate }: Props) {
    const [formData, setFormData] = useState({
        project_id: '',
        task_id: '',
        date: window.appSettings?.formatDateForInput(new Date()) || new Date().toISOString().split('T')[0],
        start_time: '',
        end_time: '',
        hours: '',
        description: '',
        is_billable: true
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [timeMode, setTimeMode] = useState<'duration' | 'times'>('duration');
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (timeEntry) {
            setFormData({
                project_id: timeEntry.project_id.toString(),
                task_id: timeEntry.task_id?.toString() || 'none',
                date: window.appSettings?.formatDateForInput(timeEntry.date) || timeEntry.date,
                start_time: timeEntry.start_time || '',
                end_time: timeEntry.end_time || '',
                hours: timeEntry.hours.toString(),
                description: timeEntry.description || '',
                is_billable: timeEntry.is_billable
            });
            setTimeMode(timeEntry.start_time ? 'times' : 'duration');
        } else {
            const defaultDate = selectedDate || window.appSettings?.formatDateForInput(new Date()) || new Date().toISOString().split('T')[0];
            setFormData({
                project_id: '',
                task_id: 'none',
                date: defaultDate,
                start_time: '',
                end_time: '',
                hours: '',
                description: '',
                is_billable: true
            });
        }
    }, [timeEntry, isOpen, selectedDate]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        const submitData = {
            timesheet_id: timesheetId,
            project_id: formData.project_id,
            task_id: formData.task_id === 'none' ? null : formData.task_id || null,
            date: formData.date,
            start_time: timeMode === 'times' ? formData.start_time : null,
            end_time: timeMode === 'times' ? formData.end_time : null,
            hours: parseFloat(formData.hours),
            description: formData.description,
            is_billable: formData.is_billable
        };

        const url = timeEntry 
            ? route('timesheet-entries.update', timeEntry.id)
            : route('timesheet-entries.store');

        const method = timeEntry ? 'put' : 'post';

        router[method](url, submitData, {
            onSuccess: () => {
                setErrors({});
                onClose();
                setFormData({
                    project_id: '',
                    task_id: 'none',
                    date: window.appSettings?.formatDateForInput(new Date()) || new Date().toISOString().split('T')[0],
                    start_time: '',
                    end_time: '',
                    hours: '',
                    description: '',
                    is_billable: true
                });
            },
            onError: (errors) => setErrors(errors),
            onFinish: () => setIsSubmitting(false)
        });
    };

    const handleTimeCalculation = () => {
        if (formData.start_time && formData.end_time) {
            const start = new Date(`2000-01-01T${formData.start_time}`);
            const end = new Date(`2000-01-01T${formData.end_time}`);
            const diffMs = end.getTime() - start.getTime();
            const hours = diffMs / (1000 * 60 * 60);
            setFormData(prev => ({ ...prev, hours: (() => { if (typeof window !== "undefined" && window.appSettings?.formatCurrency) { return window.appSettings.formatCurrency(hours, { showSymbol: true }); } return hours.toFixed(2); })() }));
        }
    };

    useEffect(() => {
        if (timeMode === 'times') {
            handleTimeCalculation();
        }
    }, [formData.start_time, formData.end_time, timeMode]);

    const selectedProject = projects.find(p => p.id.toString() === formData.project_id);
    const availableTasks = selectedProject?.tasks || [];

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-lg" style={{ zIndex: 9999 }}>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        {timeEntry ? 'Edit Time Entry' : 'Add Time Entry'}
                    </DialogTitle>
                </DialogHeader>

                {Object.keys(errors).length > 0 && (
                    <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                        <ul className="text-red-700 text-sm space-y-1">
                            {Object.values(errors).map((error, index) => (
                                <li key={index}>• {error}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>Project *</Label>
                            <Select 
                                value={formData.project_id} 
                                onValueChange={(value) => setFormData(prev => ({ ...prev, project_id: value, task_id: 'none' }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select project" />
                                </SelectTrigger>
                                <SelectContent style={{zIndex: 999999}}>
                                    {projects.map(project => (
                                        <SelectItem key={project.id} value={project.id.toString()}>
                                            {project.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label>Task</Label>
                            <Select 
                                value={formData.task_id} 
                                onValueChange={(value) => setFormData(prev => ({ ...prev, task_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select task (optional)" />
                                </SelectTrigger>
                                <SelectContent style={{zIndex: 999999}}>
                                    <SelectItem value="none">No task</SelectItem>
                                    {availableTasks.map(task => (
                                        <SelectItem key={task.id} value={task.id.toString()}>
                                            {task.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>Date *</Label>
                        <Input
                            type="date"
                            value={formData.date}
                            onChange={(e) => setFormData(prev => ({ ...prev, date: e.target.value }))}
                            required
                        />
                    </div>

                    <div className="space-y-3">
                        <div className="flex gap-4">
                            <label className="flex items-center space-x-2">
                                <input
                                    type="radio"
                                    checked={timeMode === 'duration'}
                                    onChange={() => setTimeMode('duration')}
                                />
                                <span>Duration</span>
                            </label>
                            <label className="flex items-center space-x-2">
                                <input
                                    type="radio"
                                    checked={timeMode === 'times'}
                                    onChange={() => setTimeMode('times')}
                                />
                                <span>Start/End Times</span>
                            </label>
                        </div>

                        {timeMode === 'duration' ? (
                            <div className="space-y-2">
                                <Label>Hours *</Label>
                                <Input
                                    type="number"
                                    step="0.25"
                                    min="0.25"
                                    max="24"
                                    value={formData.hours}
                                    onChange={(e) => setFormData(prev => ({ ...prev, hours: e.target.value }))}
                                    placeholder="8.00"
                                    required
                                />
                            </div>
                        ) : (
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Start Time *</Label>
                                    <Input
                                        type="time"
                                        value={formData.start_time}
                                        onChange={(e) => setFormData(prev => ({ ...prev, start_time: e.target.value }))}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>End Time *</Label>
                                    <Input
                                        type="time"
                                        value={formData.end_time}
                                        onChange={(e) => setFormData(prev => ({ ...prev, end_time: e.target.value }))}
                                        required
                                    />
                                </div>
                            </div>
                        )}

                        {timeMode === 'times' && formData.hours && (
                            <div className="text-sm text-muted-foreground">
                                Calculated hours: {formData.hours}
                            </div>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label>Description</Label>
                        <Textarea
                            value={formData.description}
                            onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                            placeholder="What did you work on?"
                            rows={3}
                        />
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="is_billable"
                            checked={formData.is_billable}
                            onCheckedChange={(checked) => setFormData(prev => ({ ...prev, is_billable: !!checked }))}
                        />
                        <Label htmlFor="is_billable">Billable</Label>
                    </div>

                    <div className="flex justify-end gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={isSubmitting}
                        >
                            <X className="h-4 w-4 mr-2" />
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting || !formData.project_id || !formData.hours}>
                            <Save className="h-4 w-4 mr-2" />
                            {isSubmitting ? 'Saving...' : timeEntry ? 'Update' : 'Add Entry'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}