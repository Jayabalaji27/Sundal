import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Plus, Edit, Trash2, Clock, Eye } from 'lucide-react';
import TimeEntryForm from './TimeEntryForm';

interface TimeEntry {
    id: number;
    project: { title: string };
    task?: { title: string };
    start_time?: string;
    end_time?: string;
    hours: number;
    description?: string;
    is_billable: boolean;
    user?: { name: string };
}

interface Props {
    date: string;
    entries: TimeEntry[];
    projects: any[];
    timesheetId: number;
    isOpen: boolean;
    onClose: () => void;
    onEntryUpdate: () => void;
    permissions?: {
        canAccessAllData: boolean;
        canManageTimesheets: boolean;
        isReadOnly: boolean;
        userRole: string;
    };
}

export default function CalendarDayView({ 
    date, 
    entries, 
    projects, 
    timesheetId, 
    isOpen, 
    onClose, 
    onEntryUpdate,
    permissions 
}: Props) {
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editingEntry, setEditingEntry] = useState<TimeEntry | null>(null);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [entryToDelete, setEntryToDelete] = useState<TimeEntry | null>(null);

    const formatDate = () => {
        return new Date(date).toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    };

    const getTotalHours = () => entries.reduce((sum, entry) => sum + (Number(entry.hours) || 0), 0);
    const getBillableHours = () => entries.filter(e => e.is_billable).reduce((sum, entry) => sum + (Number(entry.hours) || 0), 0);

    const handleEdit = (entry: TimeEntry) => {
        setEditingEntry(entry);
        setIsFormOpen(true);
    };

    const handleDelete = (entry: TimeEntry) => {
        setEntryToDelete(entry);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (entryToDelete) {
            router.delete(route('timesheet-entries.destroy', entryToDelete.id), {
                onSuccess: () => {
                    onEntryUpdate();
                    setIsDeleteModalOpen(false);
                    setEntryToDelete(null);
                },
                onError: () => {
                    console.error('Failed to delete entry');
                    setIsDeleteModalOpen(false);
                    setEntryToDelete(null);
                }
            });
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl" style={{ zIndex: 1000 }}>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        {formatDate()}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Day Summary */}
                    <div className="grid grid-cols-3 gap-4">
                        <div className="bg-blue-50 p-3 rounded-lg text-center">
                            <div className="text-2xl font-bold text-blue-900">{getTotalHours().toFixed(1)}h</div>
                            <div className="text-sm text-blue-600">Total</div>
                        </div>
                        <div className="bg-green-50 p-3 rounded-lg text-center">
                            <div className="text-2xl font-bold text-green-900">{getBillableHours().toFixed(1)}h</div>
                            <div className="text-sm text-green-600">Billable</div>
                        </div>
                        <div className="bg-purple-50 p-3 rounded-lg text-center">
                            <div className="text-2xl font-bold text-purple-900">{entries.length}</div>
                            <div className="text-sm text-purple-600">Entries</div>
                        </div>
                    </div>

                    {/* Add Entry Button */}
                    <Button 
                        onClick={() => setIsFormOpen(true)} 
                        className="w-full"
                    >
                        <Plus className="h-4 w-4 mr-2" />
                        Add Time Entry
                    </Button>

                    {/* Entries List */}
                    <div className="space-y-2 max-h-96 overflow-y-auto">
                        {entries.map((entry) => (
                            <Card key={entry.id}>
                                <CardContent className="p-4">
                                    <div className="flex justify-between items-start">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="font-medium">{entry.project.title}</span>
                                                <Badge variant={entry.is_billable ? 'default' : 'secondary'}>
                                                    {entry.is_billable ? 'Billable' : 'Non-billable'}
                                                </Badge>
                                                {permissions?.canAccessAllData && entry.user && (
                                                    <Badge variant="outline" className="text-xs">
                                                        {entry.user.name}
                                                    </Badge>
                                                )}
                                            </div>
                                            {entry.task && (
                                                <div className="text-sm text-muted-foreground mb-1">
                                                    Task: {entry.task.title}
                                                </div>
                                            )}
                                            <div className="text-sm text-muted-foreground">
                                                {entry.start_time && entry.end_time 
                                                    ? `${entry.start_time} - ${entry.end_time}`
                                                    : `${entry.hours}h`
                                                }
                                            </div>
                                            {entry.description && (
                                                <div className="text-sm text-gray-600 mt-1">
                                                    {entry.description}
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1 ml-4">
                                            <div className="text-lg font-bold text-blue-600">
                                                {entry.hours}h
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleEdit(entry)}
                                                className="h-8 w-8 text-amber-500 hover:text-amber-700"
                                                title="Edit"
                                            >
                                                <Edit className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleDelete(entry)}
                                                className="h-8 w-8 text-red-500 hover:text-red-700"
                                                title="Delete"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                        
                        {entries.length === 0 && (
                            <div className="text-center py-8 text-muted-foreground">
                                No time entries for this day
                            </div>
                        )}
                    </div>
                </div>

                <TimeEntryForm
                    isOpen={isFormOpen}
                    onClose={() => {
                        setIsFormOpen(false);
                        setEditingEntry(null);
                    }}
                    timeEntry={editingEntry || undefined}
                    timesheetId={timesheetId}
                    projects={projects}
                    className="z-50"
                />

                {isDeleteModalOpen && (
                    <Dialog open={isDeleteModalOpen} onOpenChange={() => {
                        setIsDeleteModalOpen(false);
                        setEntryToDelete(null);
                    }}>
                        <DialogContent className="sm:max-w-md" style={{ zIndex: 10000 }}>
                            <DialogHeader>
                                <DialogTitle>Delete time entry</DialogTitle>
                                <div className="text-sm text-muted-foreground">
                                    Are you sure you want to delete {entryToDelete ? `${entryToDelete.hours}h entry for ${entryToDelete.project.title}` : 'this time entry'}? This action cannot be undone.
                                </div>
                            </DialogHeader>
                            <div className="flex justify-end gap-2 pt-4">
                                <Button type="button" variant="outline" onClick={() => {
                                    setIsDeleteModalOpen(false);
                                    setEntryToDelete(null);
                                }}>
                                    Cancel
                                </Button>
                                <Button type="button" variant="destructive" onClick={handleDeleteConfirm}>
                                    Delete
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                )}
            </DialogContent>
        </Dialog>
    );
}