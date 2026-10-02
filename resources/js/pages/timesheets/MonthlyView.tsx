import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ChevronLeft, ChevronRight, Plus, Clock, Calendar, TrendingUp } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import TimeEntryForm from '@/components/timesheets/TimeEntryForm';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';

interface ProjectSummary {
    project_name: string;
    total_hours: number;
    billable_hours: number;
    percentage: number;
}

interface WeekSummary {
    week_start: string;
    week_end: string;
    total_hours: number;
    billable_hours: number;
    entries_count: number;
}

interface Props {
    monthData: {
        total_hours: number;
        billable_hours: number;
        entries_count: number;
        working_days: number;
    };
    projectBreakdown: ProjectSummary[];
    weeklyBreakdown: WeekSummary[];
    projects: any[];
    currentMonth: string;
    timesheetId: number;
    permissions?: any;
}

export default function MonthlyView({ 
    monthData, 
    projectBreakdown, 
    weeklyBreakdown, 
    projects, 
    currentMonth, 
    timesheetId,
    permissions 
}: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const userPermissions = auth?.permissions || [];
    const [currentDate, setCurrentDate] = useState(new Date(currentMonth));
    const [isFormOpen, setIsFormOpen] = useState(false);

    const navigateMonth = (direction: 'prev' | 'next') => {
        const newDate = new Date(currentDate);
        newDate.setMonth(newDate.getMonth() + (direction === 'next' ? 1 : -1));
        setCurrentDate(newDate);
        
        const monthStr = newDate.toISOString().slice(0, 7);
        router.get(route('timesheets.monthly-view'), { month: monthStr }, { preserveState: true });
    };

    const goToCurrentMonth = () => {
        const today = new Date();
        setCurrentDate(today);
        const monthStr = today.toISOString().slice(0, 7);
        router.get(route('timesheets.monthly-view'), { month: monthStr }, { preserveState: true });
    };

    const formatMonth = () => {
        return currentDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long' 
        });
    };

    const isCurrentMonth = () => {
        const today = new Date();
        return currentDate.getMonth() === today.getMonth() && 
               currentDate.getFullYear() === today.getFullYear();
    };

    const getAverageHoursPerDay = () => {
        return monthData.working_days > 0 ? (monthData.total_hours / monthData.working_days).toFixed(1) : '0.0';
    };

    const getBillablePercentage = () => {
        return monthData.total_hours > 0 ? ((monthData.billable_hours / monthData.total_hours) * 100).toFixed(0) : '0';
    };

    const formatWeekRange = (weekStart: string, weekEnd: string) => {
        const start = new Date(weekStart);
        const end = new Date(weekEnd);
        return `${start.getDate()}/${start.getMonth() + 1} - ${end.getDate()}/${end.getMonth() + 1}`;
    };

    const pageActions: any[] = [];

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Timesheets'), href: route('timesheets.index') },
        { title: t('Monthly View') }
    ];

    return (
        <PageTemplate 
            title={t('Monthly View')} 
            actions={pageActions}
            breadcrumbs={breadcrumbs}
        >
            <Head title={`Monthly View - ${formatMonth()}`} />
            
            {/* Month Navigation */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => navigateMonth('prev')}
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            
                            <div className="text-center">
                                <CardTitle className="text-lg">
                                    {formatMonth()}
                                    {isCurrentMonth() && <span className="ml-2 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">{t('Current Month')}</span>}
                                </CardTitle>
                            </div>
                            
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => navigateMonth('next')}
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                        
                        {!isCurrentMonth() && (
                            <Button variant="outline" onClick={goToCurrentMonth}>
                                {t('Current Month')}
                            </Button>
                        )}
                    </div>
                </CardHeader>
            </Card>

            {/* Month Summary */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <Clock className="h-5 w-5 text-blue-600" />
                            <span className="text-sm font-medium text-blue-600">{t('Total Hours')}</span>
                        </div>
                        <div className="text-lg font-bold text-blue-900">
                            {monthData.total_hours.toFixed(1)}h
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {getAverageHoursPerDay()}h avg/day
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <Calendar className="h-5 w-5 text-green-600" />
                            <span className="text-sm font-medium text-green-600">{t('Billable Hours')}</span>
                        </div>
                        <div className="text-lg font-bold text-green-900">
                            {monthData.billable_hours.toFixed(1)}h
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {getBillablePercentage()}% of total
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <TrendingUp className="h-5 w-5 text-purple-600" />
                            <span className="text-sm font-medium text-purple-600">{t('Entries')}</span>
                        </div>
                        <div className="text-lg font-bold text-purple-900">
                            {monthData.entries_count}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {monthData.working_days} working days
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-orange-600">{t('Utilization')}</span>
                        </div>
                        <div className="text-lg font-bold text-orange-900">
                            {((monthData.total_hours / (monthData.working_days * 8)) * 100).toFixed(0)}%
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Based on 8h/day
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Project Breakdown */}
                <Card>
                    <CardHeader>
                        <CardTitle className='text-lg'>{t('Project Breakdown')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {projectBreakdown.map((project, index) => (
                                <div key={index} className="space-y-2">
                                    <div className="flex justify-between items-center">
                                        <span className="font-medium">{project.project_name}</span>
                                        <span className="text-sm text-muted-foreground">
                                            {project.total_hours.toFixed(1)}h ({project.percentage.toFixed(0)}%)
                                        </span>
                                    </div>
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            className="bg-blue-600 h-2 rounded-full" 
                                            style={{ width: `${project.percentage}%` }}
                                        ></div>
                                    </div>
                                    <div className="flex justify-between text-xs text-muted-foreground">
                                        <span>Billable: {project.billable_hours.toFixed(1)}h</span>
                                        <span>Non-billable: {(project.total_hours - project.billable_hours).toFixed(1)}h</span>
                                    </div>
                                </div>
                            ))}
                            
                            {projectBreakdown.length === 0 && (
                                <div className="text-center py-8 text-muted-foreground">
                                    No project data available
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Weekly Breakdown */}
                <Card>
                    <CardHeader>
                        <CardTitle className='text-lg'>{t('Weekly Breakdown')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {weeklyBreakdown.map((week, index) => (
                                <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <div className="font-medium">
                                            Week {formatWeekRange(week.week_start, week.week_end)}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {week.entries_count} entries
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-bold text-blue-600">
                                            {week.total_hours.toFixed(1)}h
                                        </div>
                                        <div className="text-sm text-green-600">
                                            {week.billable_hours.toFixed(1)}h billable
                                        </div>
                                    </div>
                                </div>
                            ))}
                            
                            {weeklyBreakdown.length === 0 && (
                                <div className="text-center py-8 text-muted-foreground">
                                    No weekly data available
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Empty State */}
            {monthData.entries_count === 0 && (
                <Card className="mt-6">
                    <CardContent className="p-8 text-center">
                        <Calendar className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <h2 className="text-lg font-semibold mb-2">{t('No time entries this month')}</h2>
                        <p className="text-gray-500 mb-4">{t('Start tracking your time to see your monthly summary')}</p>
                        {hasPermission(userPermissions, 'timesheet_create') && (
                            <Button onClick={() => setIsFormOpen(true)}>
                                <Plus className="h-4 w-4 mr-2" />
                                {t('Add Time Entry')}
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