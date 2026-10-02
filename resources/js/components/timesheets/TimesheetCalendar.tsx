import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import TimeEntryForm from './TimeEntryForm';
import CalendarDayView from './CalendarDayView';

interface TimeEntry {
    id: number;
    project: { title: string };
    task?: { title: string };
    hours: number;
    is_billable: boolean;
    user?: { name: string };
}

interface CalendarDay {
    date: string;
    entries: TimeEntry[];
    totalHours: number;
    isCurrentMonth: boolean;
    isToday: boolean;
}

interface Props {
    calendarData: CalendarDay[];
    currentMonth: string;
    projects: any[];
    timesheetId: number;
    onMonthChange: (month: string) => void;
    permissions?: {
        canAccessAllData: boolean;
        canManageTimesheets: boolean;
        isReadOnly: boolean;
        userRole: string;
    };
}

export default function TimesheetCalendar({ 
    calendarData, 
    currentMonth, 
    projects, 
    timesheetId, 
    onMonthChange,
    permissions 
}: Props) {
    const [selectedDate, setSelectedDate] = useState<string>('');
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isDayViewOpen, setIsDayViewOpen] = useState(false);

    const currentDate = new Date(currentMonth);
    
    const navigateMonth = (direction: 'prev' | 'next') => {
        const newDate = new Date(currentDate);
        newDate.setMonth(newDate.getMonth() + (direction === 'next' ? 1 : -1));
        onMonthChange(newDate.toISOString().slice(0, 7));
    };

    const formatMonth = () => {
        return currentDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long' 
        });
    };

    const getDayNumber = (dateStr: string) => {
        return new Date(dateStr).getDate();
    };

    const addTimeEntry = (date: string) => {
        setSelectedDate(date);
        setIsFormOpen(true);
    };

    const openDayView = (date: string) => {
        setSelectedDate(date);
        setIsDayViewOpen(true);
    };

    const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => navigateMonth('prev')}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        
                        <span>{formatMonth()}</span>
                        
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => navigateMonth('next')}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </CardTitle>
                </div>
            </CardHeader>
            
            <CardContent>
                {/* Calendar Grid */}
                <div className="grid grid-cols-7 gap-1">
                    {/* Week day headers */}
                    {weekDays.map(day => (
                        <div key={day} className="p-2 text-center text-sm font-medium text-muted-foreground">
                            {day}
                        </div>
                    ))}
                    
                    {/* Calendar days */}
                    {calendarData.map((day, index) => (
                        <div
                            key={index}
                            className={`
                                min-h-[100px] p-1 border rounded-md cursor-pointer hover:bg-gray-50
                                ${!day.isCurrentMonth ? 'bg-gray-50 text-muted-foreground' : ''}
                                ${day.isToday ? 'ring-2 ring-blue-500' : ''}
                            `}
                            onClick={() => day.entries.length > 0 ? openDayView(day.date) : addTimeEntry(day.date)}
                        >
                            <div className="flex justify-between items-start mb-1">
                                <span className={`text-sm ${day.isToday ? 'font-bold' : ''}`}>
                                    {getDayNumber(day.date)}
                                </span>
                                {day.totalHours > 0 && (
                                    <Badge variant="secondary" className="text-xs">
                                        {day.totalHours.toFixed(1)}h
                                    </Badge>
                                )}
                            </div>
                            
                            <div className="space-y-1">
                                {day.entries.slice(0, 2).map((entry, entryIndex) => (
                                    <div
                                        key={entryIndex}
                                        className={`
                                            text-xs p-1 rounded truncate
                                            ${entry.is_billable ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}
                                        `}
                                        title={`${entry.project.title} - ${entry.hours}h${permissions?.canAccessAllData && entry.user ? ` (${entry.user.name})` : ''}`}
                                    >
                                        <div className="truncate">
                                            {entry.project.title}
                                            {permissions?.canAccessAllData && entry.user && (
                                                <div className="text-xs opacity-75 truncate">
                                                    {entry.user.name}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                
                                {day.entries.length > 2 && (
                                    <div className="text-xs text-muted-foreground text-center">
                                        +{day.entries.length - 2} more
                                    </div>
                                )}
                            </div>
                            
                            {day.entries.length === 0 && day.isCurrentMonth && (
                                <div className="flex items-center justify-center h-full opacity-0 hover:opacity-100 transition-opacity">
                                    <Plus className="h-4 w-4 text-muted-foreground" />
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </CardContent>

            <TimeEntryForm
                isOpen={isFormOpen}
                onClose={() => {
                    setIsFormOpen(false);
                    setSelectedDate('');
                }}
                timesheetId={timesheetId}
                projects={projects}
            />

            <CalendarDayView
                date={selectedDate}
                entries={calendarData.find(d => d.date === selectedDate)?.entries || []}
                projects={projects}
                timesheetId={timesheetId}
                isOpen={isDayViewOpen}
                onClose={() => {
                    setIsDayViewOpen(false);
                    setSelectedDate('');
                }}
                onEntryUpdate={() => window.location.reload()}
                permissions={permissions}
            />
        </Card>
    );
}