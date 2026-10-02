import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar, Clock } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import axios from 'axios';

interface TaskCalendarProps {
    projects: Array<{
        id: number;
        title: string;
    }>;
    members: Array<{
        id: number;
        name: string;
        email: string;
    }>;
    permissions: {
        view: boolean;
        view_tasks: boolean;
        view_meetings: boolean;
        manage_events: boolean;
    };
}

interface CalendarEvent {
    id: string;
    title: string;
    start: string;
    end: string;
    backgroundColor: string;
    borderColor: string;
    extendedProps: {
        type: 'task' | 'zoom_meeting' | 'google_meeting';
        status: string;
        project?: string;
        project_name?: string;
        description?: string;
        priority?: string;
        assigned_users?: string;
        start_date?: string;
        due_date?: string;
        join_url?: string;
        start_url?: string;
        duration?: number;
        timezone?: string;
    };
}

export default function TaskCalendarIndex({ projects, members, permissions }: TaskCalendarProps) {
    const { t } = useTranslation();
    const [events, setEvents] = useState<CalendarEvent[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchEvents();
    }, []);

    const fetchEvents = async () => {
        try {
            setLoading(true);
            const response = await axios.get(route('api.task-calendar.events'));
            setEvents(response.data);
        } catch (error) {
            console.error('Error fetching calendar events:', error);
            toast.error(t('Failed to load calendar events'));
        } finally {
            setLoading(false);
        }
    };

    const renderEventContent = (eventInfo: any) => {
        const { event } = eventInfo;
        const { extendedProps } = event;
        
        if (extendedProps.type === 'task') {
            return (
                <div className="p-1 text-xs">
                    <div className="font-medium truncate">{event.title}</div>
                    {extendedProps.project_name && (
                        <div className="text-gray-600 truncate">📁 {extendedProps.project_name}</div>
                    )}
                    {extendedProps.start_date && (
                        <div className="text-gray-600">📅 {new Date(extendedProps.start_date).toLocaleDateString()}</div>
                    )}
                    {extendedProps.due_date && (
                        <div className="text-gray-600">⏰ {new Date(extendedProps.due_date).toLocaleDateString()}</div>
                    )}
                    {extendedProps.status && (
                        <div className="text-gray-600">🏷️ {extendedProps.status}</div>
                    )}
                    {extendedProps.assigned_users && (
                        <div className="text-gray-600 truncate">👥 {extendedProps.assigned_users}</div>
                    )}
                </div>
            );
        }
        
        if (extendedProps.type === 'zoom_meeting') {
            return (
                <div className="p-1 text-xs">
                    <div className="font-medium truncate">{event.title}</div>
                    <div className="text-gray-600">🎥 Zoom Meeting</div>
                    {extendedProps.duration && (
                        <div className="text-gray-600">⏱️ {extendedProps.duration} min</div>
                    )}
                </div>
            );
        }
        
        if (extendedProps.type === 'google_meeting') {
            return (
                <div className="p-1 text-xs">
                    <div className="font-medium truncate">{event.title}</div>
                    <div className="text-gray-600">📹 Google Meet</div>
                    {extendedProps.duration && (
                        <div className="text-gray-600">⏱️ {extendedProps.duration} min</div>
                    )}
                </div>
            );
        }
        
        return <div className="p-1 text-xs font-medium truncate">{event.title}</div>;
    };



    return (
        <>
            <Head title={t('Calendar')} />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
                            {t('Calendar')}
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            {t('View tasks and meetings in calendar format')}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    <div className="lg:col-span-4">
                        <Card>
                            <CardContent className="p-6">
                                {loading ? (
                                    <div className="flex items-center justify-center h-96">
                                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                                    </div>
                                ) : (
                                    <FullCalendar
                                        plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
                                        initialView="dayGridMonth"
                                        headerToolbar={{
                                            left: 'prev,next today',
                                            center: 'title',
                                            right: 'dayGridMonth,timeGridWeek,timeGridDay'
                                        }}
                                        events={events}
                                        eventContent={renderEventContent}
                                        height="auto"
                                        eventDisplay="block"
                                        dayMaxEvents={false}
                                        moreLinkClick="popover"
                                        eventMinHeight={60}
                                    />
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        {/* Legend */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-medium">{t('Legend')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {permissions.view_tasks && (
                                    <div className="flex items-center space-x-2">
                                        <div className="w-3 h-3 bg-amber-500 rounded"></div>
                                        <span className="text-sm text-gray-600 dark:text-gray-400">{t('Tasks')}</span>
                                    </div>
                                )}
                                {permissions.view_meetings && (
                                    <>
                                        <div className="flex items-center space-x-2">
                                            <div className="w-3 h-3 bg-blue-500 rounded"></div>
                                            <span className="text-sm text-gray-600 dark:text-gray-400">{t('Zoom Meetings')}</span>
                                        </div>
                                        <div className="flex items-center space-x-2">
                                            <div className="w-3 h-3 bg-green-500 rounded"></div>
                                            <span className="text-sm text-gray-600 dark:text-gray-400">{t('Google Meetings')}</span>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Info Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-medium">{t('Task Details')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-xs">
                                <div>📁 {t('Project')}</div>
                                <div>📅 {t('Start Date')}</div>
                                <div>⏰ {t('Due Date')}</div>
                                <div>🏷️ {t('Status')}</div>
                                <div>👥 {t('Assigned Users')}</div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}