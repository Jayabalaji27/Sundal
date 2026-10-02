import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { Calendar, CheckSquare, Clock, Flag, Layers, FileText, Link, Copy, FolderOpen, User } from 'lucide-react';
import { toast } from '@/components/custom-toast';

interface CalendarEventViewProps {
    event: any;
}

export default function CalendarEventView({ event }: CalendarEventViewProps) {
    const { t } = useTranslation();

    const getEventTypeBadge = (type: string) => {
        const config: Record<string, string> = {
            meeting: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            task: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
        };
        return config[type] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getMeetingStatusBadge = (status: string) => {
        const s = status?.toLowerCase();
        if (s === 'planned' || s === 'scheduled') return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20';
        if (s === 'held' || s === 'completed') return 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20';
        if (s === 'not_held' || s === 'cancelled') return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20';
        return 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getTaskStageBadge = (stage: string) => {
        const s = stage?.toLowerCase();
        if (s?.includes('to do') || s?.includes('todo')) return 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
        if (s?.includes('progress') || s?.includes('doing')) return 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20';
        if (s?.includes('review')) return 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20';
        if (s?.includes('blocked')) return 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20';
        if (s?.includes('done') || s?.includes('complete')) return 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20';
        return 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getPriorityBadge = (priority: string) => {
        const p = priority?.toLowerCase();
        if (p === 'high' || p === 'critical') return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20';
        if (p === 'medium') return 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20';
        if (p === 'low') return 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20';
        return 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const isMeeting = event.type === 'meeting';
    const isTask = event.type === 'task';

    return (
        <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader className="px-6 pt-6 pb-4 border-b">
                <div className="flex items-center justify-between mr-6">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg">
                            {isMeeting
                                ? <Calendar className="h-5 w-5 text-primary" />
                                : <CheckSquare className="h-5 w-5 text-primary" />
                            }
                        </div>
                        <DialogTitle className="text-xl font-semibold">{event.title}</DialogTitle>
                    </div>
                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getEventTypeBadge(event.type)}`}>
                        {t(event.type.charAt(0).toUpperCase() + event.type.slice(1))}
                    </span>
                </div>
            </DialogHeader>

            <div className="px-6 py-4 pb-6 space-y-4">

                {/* ── MEETING fields ── */}
                {isMeeting && (
                    <>
                        {/* Start Time & Duration */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {event.start_time && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Clock className="h-4 w-4" />
                                        {t('Start Time')}
                                    </label>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                        {event.start_time.substring(0, 16).replace('T', ' ')}
                                    </p>
                                </div>
                            )}
                            {event.duration && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Clock className="h-4 w-4" />
                                        {t('Duration (minutes)')}
                                    </label>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{event.duration}</p>
                                </div>
                            )}
                        </div>

                        {/* Status & Project */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {event.status && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Layers className="h-4 w-4" />
                                        {t('Status')}
                                    </label>
                                    <div className="mt-1">
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getMeetingStatusBadge(event.status)}`}>
                                            {event.status}
                                        </span>
                                    </div>
                                </div>
                            )}
                            {event.parent_name && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <FolderOpen className="h-4 w-4" />
                                        {t('Project')}
                                    </label>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{event.parent_name}</p>
                                </div>
                            )}
                        </div>
                    </>
                )}

                {/* ── TASK fields ── */}
                {isTask && (
                    <>
                        {/* Start Date & Due Date */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {event.start_date && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Calendar className="h-4 w-4" />
                                        {t('Start Date')}
                                    </label>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                        {window.appSettings.formatDateTime(new Date(event.start_date),false)}
                                    </p>
                                </div>
                            )}
                            {event.due_date && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Clock className="h-4 w-4" />
                                        {t('Due Date')}
                                    </label>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                        {window.appSettings.formatDateTime(new Date(event.due_date),false)}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Stage & Priority */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {event.stage && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Layers className="h-4 w-4" />
                                        {t('Stage')}
                                    </label>
                                    <div className="mt-1">
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getTaskStageBadge(event.stage)}`}>
                                            {event.stage}
                                        </span>
                                    </div>
                                </div>
                            )}
                            {event.priority && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Flag className="h-4 w-4" />
                                        {t('Priority')}
                                    </label>
                                    <div className="mt-1">
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityBadge(event.priority)}`}>
                                            {event.priority}
                                        </span>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Project & Progress */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {event.parent_name && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <FolderOpen className="h-4 w-4" />
                                        {t('Project')}
                                    </label>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{event.parent_name}</p>
                                </div>
                            )}
                            {event.progress !== undefined && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Layers className="h-4 w-4" />
                                        {t('Progress')}
                                    </label>
                                    <div className="mt-2 flex items-center gap-2">
                                        <div className="flex-1 bg-gray-200 rounded-full h-2">
                                            <div
                                                className="bg-blue-600 h-2 rounded-full"
                                                style={{ width: `${event.progress || 0}%` }}
                                            />
                                        </div>
                                        <span className="text-xs font-medium text-gray-600">{event.progress || 0}%</span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </>
                )}

                {/* Description - both types */}
                {event.description && (
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            {t('Description')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white whitespace-pre-wrap">{event.description}</p>
                    </div>
                )}

                {/* Meeting URLs */}
                {isMeeting && (event.join_url || event.start_url) && (
                    <div className="space-y-3 pt-2 border-t">
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Link className="h-4 w-4" />
                            {t('Meeting URLs')}
                        </label>
                        <div className="space-y-2">
                            {event.join_url && (
                                <div className="space-y-1">
                                    <p className="text-xs text-gray-500">{t('Join URL')}</p>
                                    <div className="flex items-center gap-2">
                                        <div className="flex-1 p-2 bg-gray-50 rounded text-xs font-mono text-gray-700 break-all">
                                            {event.join_url}
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                navigator.clipboard.writeText(event.join_url);
                                                toast.success(t('Join URL copied to clipboard'));
                                            }}
                                            className="text-blue-600 border-blue-200 hover:bg-blue-50 h-8 px-2 shrink-0"
                                        >
                                            <Copy className="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                            {event.start_url && (
                                <div className="space-y-1">
                                    <p className="text-xs text-gray-500">{t('Start URL')}</p>
                                    <div className="flex items-center gap-2">
                                        <div className="flex-1 p-2 bg-gray-50 rounded text-xs font-mono text-gray-700 break-all">
                                            {event.start_url}
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                navigator.clipboard.writeText(event.start_url);
                                                toast.success(t('Start URL copied to clipboard'));
                                            }}
                                            className="text-green-600 border-green-200 hover:bg-green-50 h-8 px-2 shrink-0"
                                        >
                                            <Copy className="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </DialogContent>
    );
}
