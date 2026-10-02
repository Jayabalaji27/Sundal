import React, { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ChevronLeft, ChevronRight, Plus, Clock, Calendar } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import TimeEntryForm from '@/components/timesheets/TimeEntryForm';
import TimeEntryList from '@/components/timesheets/TimeEntryList';
import { hasPermission } from '@/utils/authorization';
import { useTranslation } from 'react-i18next';

interface TimeEntry {
    id: number;
    project: { title: string };
    task?: { title: string };
    date: string;
    start_time?: string;
    end_time?: string;
    hours: number;
    description?: string;
    is_billable: boolean;
}

interface Project {
    id: number;
    title: string;
    tasks?: any[];
}

interface Props {
    entries: TimeEntry[] | { data: TimeEntry[], links?: any[], from?: number, to?: number, total?: number };
    projects: Project[];
    selectedDate: string;
    timesheetId: number;
    filters?: { search?: string, per_page?: number, project?: string, billable?: string };
    permissions?: any;
}

export default function DailyView({ entries, projects, selectedDate, timesheetId, filters = {}, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, auth } = usePage().props as any;
    const userPermissions = auth?.permissions || [];
    const [currentDate, setCurrentDate] = useState(new Date(selectedDate));
    const [isFormOpen, setIsFormOpen] = useState(false);

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const navigateDate = (direction: 'prev' | 'next') => {
        const newDate = new Date(currentDate);
        newDate.setDate(newDate.getDate() + (direction === 'next' ? 1 : -1));
        setCurrentDate(newDate);
        
        const dateStr = newDate.toISOString().split('T')[0];
        router.get(route('timesheets.daily-view'), { date: dateStr }, { preserveState: true });
    };

    const goToToday = () => {
        const today = new Date();
        setCurrentDate(today);
        const dateStr = today.toISOString().split('T')[0];
        router.get(route('timesheets.daily-view'), { date: dateStr }, { preserveState: true });
    };

    const handleDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newDate = new Date(e.target.value);
        setCurrentDate(newDate);
        router.get(route('timesheets.daily-view'), { date: e.target.value }, { preserveState: true });
    };

    // Handle both array and paginated data formats
    const entriesData = Array.isArray(entries) ? entries : entries.data || [];
    
    const getDayTotal = () => entriesData.reduce((sum, entry) => sum + (Number(entry.hours) || 0), 0);
    const getBillableTotal = () => entriesData.filter(e => e.is_billable).reduce((sum, entry) => sum + (Number(entry.hours) || 0), 0);

    const formatDate = (date: Date) => {
        return window.appSettings.formatDateTime(date, false);
    };

    const isToday = currentDate.toDateString() === new Date().toDateString();
    const isWeekend = currentDate.getDay() === 0 || currentDate.getDay() === 6;

    const pageActions = [];
    
    if (hasPermission(userPermissions, 'timesheet_update') && entriesData.length > 0) {
        pageActions.push(
            {
                label: 'Mark Billable',
                variant: 'outline' as const,
                onClick: () => {
                    toast.loading('Marking entries as billable...');
                    router.post(route('timesheet-entries.bulk-update'), {
                        entry_ids: entriesData.map(e => e.id),
                        is_billable: true
                    }, {
                        onSuccess: () => {
                            toast.dismiss();
                        },
                        onError: () => {
                            toast.dismiss();
                            toast.error('Failed to update entries');
                        }
                    });
                }
            },
            {
                label: 'Mark Non-Billable',
                variant: 'outline' as const,
                onClick: () => {
                    toast.loading('Marking entries as non-billable...');
                    router.post(route('timesheet-entries.bulk-update'), {
                        entry_ids: entriesData.map(e => e.id),
                        is_billable: false
                    }, {
                        onSuccess: () => {
                            toast.dismiss();
                        },
                        onError: () => {
                            toast.dismiss();
                            toast.error('Failed to update entries');
                        }
                    });
                }
            }
        );
    }

    const breadcrumbs = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Timesheets', href: route('timesheets.index') },
        { title: 'Daily View' }
    ];

    return (
        <PageTemplate 
            title="Daily View" 
            actions={pageActions}
            breadcrumbs={breadcrumbs}
        >
            <Head title={`Daily View - ${formatDate(currentDate)}`} />
            
            {/* Date Navigation */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => navigateDate('prev')}
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            
                            <div className="text-center">
                                <CardTitle className="text-lg">
                                    {formatDate(currentDate)}
                                    {isToday && <span className="ml-2 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">Today</span>}
                                    {isWeekend && <span className="ml-2 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">Weekend</span>}
                                </CardTitle>
                            </div>
                            
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => navigateDate('next')}
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                        
                        <div className="flex items-center gap-2">
                            <Input
                                type="date"
                                value={currentDate.toISOString().split('T')[0]}
                                onChange={handleDateChange}
                                className="w-auto"
                            />
                            {!isToday && (
                                <Button variant="outline" onClick={goToToday}>
                                    Today
                                </Button>
                            )}
                        </div>
                    </div>
                </CardHeader>
            </Card>

            {/* Day Summary */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <Clock className="h-5 w-5 text-blue-600" />
                            <span className="text-sm font-medium text-blue-600">Total Hours</span>
                        </div>
                        <div className="text-lg font-bold text-blue-900">
                            {(getDayTotal() || 0).toFixed(2)}h
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <Calendar className="h-5 w-5 text-green-600" />
                            <span className="text-sm font-medium text-green-600">Billable Hours</span>
                        </div>
                        <div className="text-lg font-bold text-green-900">
                            {(getBillableTotal() || 0).toFixed(2)}h
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-purple-600">Entries</span>
                        </div>
                        <div className="text-lg font-bold text-purple-900">
                            {entriesData.length}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-orange-600">Utilization</span>
                        </div>
                        <div className="text-lg font-bold text-orange-900">
                            {(((getDayTotal() || 0) / 8) * 100).toFixed(0)}%
                        </div>
                        <div className="text-xs text-muted-foreground">Based on 8h day</div>
                    </CardContent>
                </Card>
            </div>

            {/* Time Entries */}
            <Card>
                <CardHeader>
                    <div className="flex justify-between items-center">
                        <CardTitle className='text-xl'>Time Entries</CardTitle>
                        {hasPermission(userPermissions, 'timesheet_create') && (
                            <Button onClick={() => setIsFormOpen(true)}>
                                <Plus className="h-4 w-4 mr-2" />
                                Add Entry
                            </Button>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    <TimeEntryList
                        entries={entries}
                        timesheetId={timesheetId}
                        projects={projects}
                        filters={filters}
                        onRefresh={() => router.get(route('timesheets.daily-view'), { date: currentDate.toISOString().split('T')[0] }, { preserveState: true })}
                    />
                </CardContent>
            </Card>

            {/* Quick Actions */}
            {entriesData.length === 0 && (
                <Card className="mt-6">
                    <CardContent className="p-8 text-center">
                        <Clock className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <h3 className="text-lg font-semibold mb-2">No time entries for this day</h3>
                        <p className="text-gray-500 mb-4">Start tracking your time by adding your first entry</p>
                        {hasPermission(userPermissions, 'timesheet_create') && (
                            <Button onClick={() => setIsFormOpen(true)}>
                                <Plus className="h-4 w-4 mr-2" />
                                Add Time Entry
                            </Button>
                        )}
                    </CardContent>
                </Card>
            )}

            <TimeEntryForm
                isOpen={isFormOpen}
                onClose={() => setIsFormOpen(false)}
                timesheetId={timesheetId}
                projects={projects}
            />
        </PageTemplate>
    );
}