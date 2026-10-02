import React, { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { PageTemplate } from '@/components/page-template';
import TimesheetCalendar from '@/components/timesheets/TimesheetCalendar';
import { Plus, Calendar as CalendarIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface CalendarDay {
    date: string;
    entries: any[];
    totalHours: number;
    isCurrentMonth: boolean;
    isToday: boolean;
}

interface Props {
    calendarData: CalendarDay[];
    currentMonth: string;
    projects: any[];
    timesheetId: number;
    permissions?: any;
}

export default function CalendarView({ calendarData, currentMonth, projects, timesheetId, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, auth, systemSettings } = usePage().props as any;
    const userPermissions = auth?.permissions || [];
    const [activeCalendar, setActiveCalendar] = useState<'local' | 'google'>('local');
    const [isSyncing, setIsSyncing] = useState(false);
    const isGoogleCalendarSyncTested = systemSettings?.is_googlecalendar_sync === '1';
    

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const handleMonthChange = (month: string) => {
        router.get(route('timesheets.calendar-view'), { month }, { preserveState: true });
    };

    const handleCalendarChange = (type: 'local' | 'google') => {
        setActiveCalendar(type);
        setIsSyncing(true);
        
        if (type === 'google') {
            fetch(route('google-calendar.sync'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toast.success(t('Google Calendar synced successfully'));
                } else {
                    toast.error(t('Failed to sync Google Calendar'));
                    setActiveCalendar('local');
                }
            })
            .catch(() => {
                toast.error(t('Failed to sync Google Calendar'));
                setActiveCalendar('local');
            })
            .finally(() => setIsSyncing(false));
        } else {
            setTimeout(() => setIsSyncing(false), 500);
        }
    };

    const pageActions: any[] = [];

    if (isGoogleCalendarSyncTested) {
        pageActions.push({
            component: (
                <Select
                    value={activeCalendar}
                    onValueChange={(value: 'local' | 'google') => handleCalendarChange(value)}
                    disabled={isSyncing}
                >
                    <SelectTrigger className="w-48 h-9">
                        <SelectValue>
                            <div className="flex items-center gap-2">
                                {activeCalendar === 'local' && <CalendarIcon className="h-4 w-4" />}
                                <span>{activeCalendar === 'local' ? t('Local Calendar') : t('Google Calendar')}</span>
                            </div>
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="local">
                            <div className="flex items-center gap-2">
                                <CalendarIcon className="h-4 w-4" />
                                <span>{t('Local Calendar')}</span>
                            </div>
                        </SelectItem>
                        <SelectItem value="google">
                            <div className="flex items-center gap-2">
                                <span>{t('Google Calendar')}</span>
                            </div>
                        </SelectItem>
                    </SelectContent>
                </Select>
            )
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Timesheets'), href: route('timesheets.index') },
        { title: t('Calendar View') }
    ];

    return (
        <PageTemplate 
            title={t('Calendar View')} 
            actions={pageActions}
            breadcrumbs={breadcrumbs}
        >
            <Head title={t('Calendar View')} />
            
            <TimesheetCalendar
                calendarData={calendarData}
                currentMonth={currentMonth}
                projects={projects}
                timesheetId={timesheetId}
                onMonthChange={handleMonthChange}
                permissions={userPermissions}
            />
        </PageTemplate>
    );
}