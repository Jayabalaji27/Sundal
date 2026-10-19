import React, { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ChevronLeft, ChevronRight, Plus, Clock } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import TimeEntryForm from '@/components/timesheets/TimeEntryForm';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';

interface DayEntry {
    date: string;
    entries: TimeEntry[];
    totalHours: number;
    billableHours: number;
}

interface TimeEntry {
    id: number;
    project: { title: string } | null;
    task?: { title: string } | null;
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
    weekData: DayEntry[];
    projects: Project[];
    weekStart: string;
    weekEnd: string;
    timesheetId: number | null;
    timesheetStatus?: 'draft' | 'submitted' | 'approved' | 'rejected' | null;
    rejectionReason?: string | null;
    permissions?: any;
}

const STATUS_STYLES: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700 ring-slate-500/30',
    submitted: 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
    approved: 'bg-green-50 text-green-700 ring-green-600/20',
    rejected: 'bg-red-50 text-red-700 ring-red-600/20',
};

// Weeks run Monday-Sunday to match the backend (Carbon's default week start).
const getMondayOfWeek = (date: Date): Date => {
    const d = new Date(date);
    const day = d.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);
    return d;
};

// Local Y-M-D, not toISOString() (which converts to UTC and can land on the
// wrong calendar day depending on the viewer's timezone offset/time of day).
const toLocalDateStr = (date: Date): string => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

// "YYYY-MM-DD" as a local calendar day. new Date('YYYY-MM-DD') is UTC
// midnight, which is the previous day for viewers west of UTC.
const parseLocalDate = (dateStr: string): Date => {
    const [y, m, d] = dateStr.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d);
};

export default function WeeklyView({ weekData, projects, weekStart, weekEnd, timesheetId, timesheetStatus, rejectionReason, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, auth } = usePage().props as any;
    const userPermissions = auth?.permissions || [];
    // Derived from the prop, not kept in state: visits use preserveState, so a
    // useState copy went stale and flagged the wrong week as "Current Week".
    const currentWeekStart = parseLocalDate(weekStart);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [selectedDate, setSelectedDate] = useState<string>('');

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const navigateWeek = (direction: 'prev' | 'next') => {
        const newDate = new Date(currentWeekStart);
        newDate.setDate(newDate.getDate() + (direction === 'next' ? 7 : -7));
        
        const weekStartStr = toLocalDateStr(newDate);
        router.get(route('timesheets.weekly-view'), { week_start: weekStartStr }, { preserveState: true });
    };

    const goToCurrentWeek = () => {
        const startOfWeek = getMondayOfWeek(new Date());
        const weekStartStr = toLocalDateStr(startOfWeek);
        router.get(route('timesheets.weekly-view'), { week_start: weekStartStr }, { preserveState: true });
    };

    const addTimeEntry = (date: string) => {
        setSelectedDate(date);
        setIsFormOpen(true);
    };

    const getWeekTotal = () => weekData.reduce((sum, day) => sum + day.totalHours, 0);
    const getWeekBillable = () => weekData.reduce((sum, day) => sum + day.billableHours, 0);
    const getWeekEntries = () => weekData.reduce((sum, day) => sum + day.entries.length, 0);

    const formatWeekRange = () => {
        const start = parseLocalDate(weekStart);
        const end = parseLocalDate(weekEnd);
        const formatDay = (d: Date) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        return `${formatDay(start)} - ${formatDay(end)}`;
    };

    const getDayName = (dateStr: string) => {
        return parseLocalDate(dateStr).toLocaleDateString('en-US', { weekday: 'short' });
    };

    const getDayDate = (dateStr: string) => {
        return parseLocalDate(dateStr).getDate();
    };

    const isToday = (dateStr: string) => {
        return parseLocalDate(dateStr).toDateString() === new Date().toDateString();
    };

    const isCurrentWeek = () => {
        const startOfWeek = getMondayOfWeek(new Date());
        return startOfWeek.toDateString() === currentWeekStart.toDateString();
    };

    // The week is the timesheet: log time per day, then submit it for approval here.
    const canSubmit = !!timesheetId
        && (timesheetStatus === 'draft' || timesheetStatus === 'rejected')
        && getWeekEntries() > 0
        && hasPermission(userPermissions, 'timesheet_submit');
    const submitWeek = () => {
        if (timesheetId) router.post(route('timesheets.submit', timesheetId), {}, { preserveScroll: true });
    };
    const statusLabels: Record<string, string> = {
        draft: t('Draft'), submitted: t('Waiting for approval'), approved: t('Approved'), rejected: t('Rejected'),
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Timesheet') }
    ];

    return (
        <PageTemplate 
            title={t('Timesheet')} 
            breadcrumbs={breadcrumbs}
        >
            <Head title={`${t('Timesheet')} - ${formatWeekRange()}`} />
            
            {/* Week Navigation */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => navigateWeek('prev')}
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            
                            <div className="text-center">
                                <CardTitle className="text-lg">
                                    {formatWeekRange()}
                                    {isCurrentWeek() && <span className="ml-2 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">{t('Current Week')}</span>}
                                </CardTitle>
                            </div>
                            
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => navigateWeek('next')}
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                        
                        <div className="flex flex-wrap items-center gap-3">
                            {!isCurrentWeek() && (
                                <Button variant="outline" size="sm" onClick={goToCurrentWeek}>
                                    {t('This week')}
                                </Button>
                            )}
                            <span className="text-sm text-muted-foreground">
                                <span className="font-semibold text-foreground">{getWeekTotal().toFixed(1)}h</span> {t('total')} · {getWeekBillable().toFixed(1)}h {t('billable')}
                            </span>
                            {timesheetStatus && (
                                <span className={`inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${STATUS_STYLES[timesheetStatus] || ''}`}>
                                    {statusLabels[timesheetStatus] || timesheetStatus}
                                </span>
                            )}
                            {canSubmit && (
                                <Button size="sm" onClick={submitWeek}>{t('Submit week')}</Button>
                            )}
                        </div>
                    </div>
                    {timesheetStatus === 'rejected' && rejectionReason && (
                        <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                            <span className="font-medium">{t('Rejected')}:</span> {rejectionReason}
                        </p>
                    )}
                </CardHeader>
            </Card>

            {/* Daily Breakdown */}
            <div className="grid grid-cols-1 lg:grid-cols-7 gap-4">
                {weekData.map((day) => (
                    <Card key={day.date} className={`${isToday(day.date) ? 'ring-2 ring-blue-500' : ''}`}>
                        <CardHeader className="pb-2">
                            <div className="text-center">
                                <div className="text-sm font-medium text-muted-foreground">
                                    {getDayName(day.date)}
                                </div>
                                <div className="text-lg font-bold">
                                    {getDayDate(day.date)}
                                    {isToday(day.date) && <Badge variant="secondary" className="ml-1 text-xs">{t('Today')}</Badge>}
                                </div>
                            </div>
                        </CardHeader>
                        
                        <CardContent className="space-y-2">
                            <div className="text-center">
                                <div className="text-2xl font-bold text-blue-600">
                                    {day.totalHours.toFixed(1)}h
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {day.billableHours.toFixed(1)}h {t('billable')}
                                </div>
                            </div>
                            
                            <div className="space-y-1">
                                {day.entries.slice(0, 3).map((entry, index) => (
                                    <div key={index} className="text-xs p-2 bg-gray-50 rounded">
                                        <div className="font-medium truncate">{entry.project?.title ?? 'Unknown Project'}</div>
                                        <div className="text-muted-foreground">
                                            {entry.hours}h {entry.is_billable && <span className="ml-2 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">{t('Billable')}</span>}
                                        </div>
                                    </div>
                                ))}
                                
                                {day.entries.length > 3 && (
                                    <div className="text-xs text-center text-muted-foreground">
                                        +{day.entries.length - 3} {t('more')}
                                    </div>
                                )}
                            </div>
                            
                            {hasPermission(userPermissions, 'timesheet_create') && (
                                <Button 
                                    size="sm" 
                                    variant="outline" 
                                    className="w-full"
                                    onClick={() => addTimeEntry(day.date)}
                                >
                                    <Plus className="h-3 w-3 mr-1" />
                                    {t('Add')}
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Empty State */}
            {getWeekEntries() === 0 && (
                <Card className="mt-6">
                    <CardContent className="p-8 text-center">
                        <Clock className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <h3 className="text-lg font-semibold mb-2">{t('No time entries this week')}</h3>
                        <p className="text-gray-500">{t('Use Add on a day to log time, then submit the week.')}</p>
                    </CardContent>
                </Card>
            )}

            <TimeEntryForm
                isOpen={isFormOpen}
                onClose={() => {
                    setIsFormOpen(false);
                    setSelectedDate('');
                }}
                timesheetId={timesheetId}
                projects={projects}
                selectedDate={selectedDate}
            />
        </PageTemplate>
    );
}