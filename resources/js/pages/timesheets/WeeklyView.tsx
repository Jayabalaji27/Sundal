import React, { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AlertCircle, ChevronLeft, ChevronRight, Plus, Clock, Send } from 'lucide-react';
import { StatusBadge } from '@/components/ui/status-badge';
import { EmptyState } from '@/components/EmptyState';
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
    const { t, i18n } = useTranslation();
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
        const formatDay = (d: Date) => d.toLocaleDateString(i18n.language || undefined, { month: 'short', day: 'numeric' });
        return `${formatDay(start)} - ${formatDay(end)}`;
    };

    const getDayName = (dateStr: string) => {
        return parseLocalDate(dateStr).toLocaleDateString(i18n.language || undefined, { weekday: 'short' });
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

    const statusTone: Record<string, 'neutral' | 'warning' | 'success' | 'danger'> = {
        draft: 'neutral', submitted: 'warning', approved: 'success', rejected: 'danger',
    };
    const canAdd = hasPermission(userPermissions, 'timesheet_create');
    // "Log time" from the empty state goes to today when it's in this week, else the week's first day.
    const defaultDay = weekData.find(day => isToday(day.date))?.date ?? weekData[0]?.date;

    return (
        <PageTemplate
            title={t('Timesheet')}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <Head title={`${t('Timesheet')} - ${formatWeekRange()}`} />

            {/* Week navigation, totals and the week's status */}
            <Card className="mb-6">
                <CardHeader className="pb-5">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <Button aria-label={t('Previous week')}
                                variant="outline"
                                size="icon"
                                onClick={() => navigateWeek('prev')}
                            >
                                <ChevronLeft />
                            </Button>

                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="text-lg">{formatWeekRange()}</CardTitle>
                                {isCurrentWeek() && <Badge>{t('Current Week')}</Badge>}
                            </div>

                            <Button aria-label={t('Next week')}
                                variant="outline"
                                size="icon"
                                onClick={() => navigateWeek('next')}
                            >
                                <ChevronRight />
                            </Button>
                            {!isCurrentWeek() && (
                                <Button variant="ghost" onClick={goToCurrentWeek}>
                                    {t('This week')}
                                </Button>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <span className="text-sm text-ds-fg-secondary">
                                <span className="font-semibold tabular-nums text-ds-fg">{getWeekTotal().toFixed(1)}h</span> {t('total')} · <span className="tabular-nums">{getWeekBillable().toFixed(1)}h</span> {t('billable')}
                            </span>
                            {timesheetStatus && (
                                <StatusBadge
                                    tone={statusTone[timesheetStatus] ?? 'neutral'}
                                    icon={timesheetStatus === 'submitted' ? <Clock aria-hidden="true" /> : undefined}
                                >
                                    {statusLabels[timesheetStatus] || timesheetStatus}
                                </StatusBadge>
                            )}
                            {/* The page's one primary action, once there is something to submit. */}
                            {canSubmit && (
                                <Button onClick={submitWeek}><Send />{t('Submit week')}</Button>
                            )}
                        </div>
                    </div>
                    {timesheetStatus === 'rejected' && rejectionReason && (
                        <div role="alert" className="mt-4 flex items-start gap-2 rounded-lg border border-ds-danger-border/60 bg-ds-danger-bg px-3 py-2.5 text-sm text-ds-danger-fg">
                            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            <p><span className="font-medium">{t('Rejected')}:</span> {rejectionReason}</p>
                        </div>
                    )}
                </CardHeader>
            </Card>

            {/* Daily Breakdown */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-7">
                {weekData.map((day) => {
                    const today = isToday(day.date);
                    return (
                    <Card key={day.date} className={today ? 'border-brand-300 ring-1 ring-brand-500/30' : ''} aria-current={today ? 'date' : undefined}>
                        <CardHeader className="pb-2">
                            <div className="text-center">
                                <div className="text-sm font-medium text-ds-fg-muted">
                                    {getDayName(day.date)}
                                </div>
                                <div className="flex items-center justify-center gap-1.5 text-lg font-bold text-ds-fg">
                                    {getDayDate(day.date)}
                                    {today && <Badge>{t('Today')}</Badge>}
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-3">
                            <div className="text-center">
                                <div className={`text-2xl font-bold tabular-nums ${day.totalHours > 0 ? 'text-ds-fg' : 'text-ds-fg-placeholder'}`}>
                                    {day.totalHours.toFixed(1)}h
                                </div>
                                <div className="text-xs text-ds-fg-muted">
                                    {day.billableHours.toFixed(1)}h {t('billable')}
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                {day.entries.slice(0, 3).map((entry, index) => (
                                    <div key={index} className="rounded-md border border-ds-border-subtle bg-ds-surface-subtle p-2 text-xs">
                                        <div className="truncate font-medium text-ds-fg">{entry.project?.title ?? t('Unknown Project')}</div>
                                        <div className="mt-0.5 flex items-center gap-1.5 text-ds-fg-muted">
                                            <span className="tabular-nums">{entry.hours}h</span>
                                            {entry.is_billable && <Badge variant="success" className="px-1.5 py-0 text-[11px]">{t('Billable')}</Badge>}
                                        </div>
                                    </div>
                                ))}

                                {day.entries.length > 3 && (
                                    <div className="text-center text-xs text-ds-fg-muted">
                                        +{day.entries.length - 3} {t('more')}
                                    </div>
                                )}
                            </div>

                            {canAdd && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="w-full"
                                    onClick={() => addTimeEntry(day.date)}
                                    aria-label={t('Add time for {{day}}', { day: `${getDayName(day.date)} ${getDayDate(day.date)}` })}
                                >
                                    <Plus />
                                    {t('Add')}
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                    );
                })}
            </div>

            {/* Empty State */}
            {getWeekEntries() === 0 && (
                <Card className="mt-6">
                    <EmptyState
                        compact
                        icon={Clock}
                        title={t('No time entries this week')}
                        description={t('Use Add on a day to log time, then submit the week.')}
                        action={canAdd && defaultDay ? { label: t('Log time'), icon: <Plus className="h-4 w-4" />, onClick: () => addTimeEntry(defaultDay) } : undefined}
                    />
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
