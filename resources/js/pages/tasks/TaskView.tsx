import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import {
    CheckSquare, Flag, Layers, Calendar, User, FolderOpen
} from 'lucide-react';
import TaskComments from '@/components/tasks/TaskComments';
import TaskAttachments from '@/components/tasks/TaskAttachments';
import TaskChecklist from '@/components/tasks/TaskChecklist';

interface TaskViewProps {
    task: any;
    activeTab: string;
    onTabChange: (tab: string) => void;
    members?: any[];
    workspaceRole?: string | null;
    onUpdate?: () => void;
}

export default function TaskView({ task, activeTab, onTabChange, members = [], workspaceRole, onUpdate }: TaskViewProps) {
    const { t } = useTranslation();

    const getPriorityColor = (priority: string) => {
        const colors: Record<string, string> = {
            critical: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            high: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20',
            medium: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            low: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
        };
        return colors[priority] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const capitalizeFirst = (str: string) =>
        str ? str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ') : '-';

    return (
        <DialogContent className="max-w-3xl max-h-[75vh] flex flex-col p-0 overflow-hidden" onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader className="px-6 pt-6 pb-4 border-b shrink-0">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <CheckSquare className="h-5 w-5 text-primary" />
                    </div>
                    <DialogTitle className="text-xl font-semibold">{task.title}</DialogTitle>
                </div>
            </DialogHeader>

            <Tabs value={activeTab} onValueChange={onTabChange} className="flex flex-col overflow-hidden min-h-0">
                <div className="px-6 pt-3 pb-0 border-b shrink-0">
                    <TabsList className="flex h-auto w-full flex-wrap gap-1">
                        <TabsTrigger value="details">{t('Details')}</TabsTrigger>
                        <TabsTrigger value="comments">
                            {t('Comments')}{task.comments?.length ? ` (${task.comments.length})` : ''}
                        </TabsTrigger>
                        <TabsTrigger value="checklist">
                            {t('Checklist')}{task.checklists?.length ? ` (${task.checklists.length})` : ''}
                        </TabsTrigger>
                        <TabsTrigger value="attachments">
                            {t('Attachments')}{task.attachments?.length ? ` (${task.attachments.length})` : ''}
                        </TabsTrigger>
                    </TabsList>
                </div>

                {/* Details Tab */}
                <TabsContent value="details" className="overflow-y-auto px-6 py-4 space-y-4 max-h-[55vh]">

                    {/* Stage & Priority */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Layers className="h-4 w-4" />
                                {t('Stage')}
                            </label>
                            <div className="mt-1 flex items-center gap-2">
                                {task.task_stage?.color && (
                                    <div className="w-3 h-3 rounded-full shrink-0" style={{ backgroundColor: task.task_stage.color }} />
                                )}
                                <span className="text-sm font-medium text-gray-900 dark:text-white">
                                    {task.task_stage?.name || '-'}
                                </span>
                            </div>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Flag className="h-4 w-4" />
                                {t('Priority')}
                            </label>
                            <div className="mt-1">
                                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityColor(task.priority)}`}>
                                    {capitalizeFirst(task.priority)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Assignee & Project */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <User className="h-4 w-4" />
                                {t('Assignee')}
                            </label>
                            <div className="mt-1">
                                <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20">
                                    {task.assigned_to?.name || t('Unassigned')}
                                </span>
                            </div>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <FolderOpen className="h-4 w-4" />
                                {t('Project')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                {task.project?.title || '-'}
                            </p>
                        </div>
                    </div>

                    {/* Start Date & Due Date */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                {t('Start Date')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                {task.start_date ? window.appSettings.formatDateTime(new Date(task.start_date),false) : '-'}
                            </p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                {t('Due Date')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                {task.end_date ? window.appSettings.formatDateTime(new Date(task.end_date),false) : '-'}
                            </p>
                        </div>
                    </div>

                    {/* Progress */}
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Layers className="h-4 w-4" />
                            {t('Progress')}
                        </label>
                        <div className="mt-2 flex items-center gap-3">
                            <div className="flex-1 bg-gray-200 rounded-full h-2">
                                <div
                                    className="bg-blue-600 h-2 rounded-full transition-all"
                                    style={{ width: `${task.progress || 0}%` }}
                                />
                            </div>
                            <span className="text-xs font-medium text-gray-600 shrink-0">{task.progress || 0}%</span>
                        </div>
                    </div>

                    {/* Milestone */}
                    {task.milestone && (
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Flag className="h-4 w-4" />
                                {t('Milestone')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{task.milestone.title}</p>
                        </div>
                    )}

                    {/* Description */}
                    {task.description && (
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <CheckSquare className="h-4 w-4" />
                                {t('Description')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white whitespace-pre-wrap">{task.description}</p>
                        </div>
                    )}
                </TabsContent>

                {/* Comments Tab */}
                <TabsContent value="comments" className="overflow-hidden px-6 py-4 min-h-0 h-[55vh]">
                    <TaskComments
                        task={task}
                        comments={task.comments || []}
                        currentUser={members[0]}
                        onUpdate={onUpdate}
                        canAddComments={workspaceRole !== 'client'}
                    />
                </TabsContent>

                {/* Checklist Tab */}
                <TabsContent value="checklist" className="overflow-hidden px-6 py-4 min-h-0 h-[55vh]">
                    <TaskChecklist
                        task={task}
                        checklist={task.checklists || []}
                        members={task.project?.members?.filter((m: any) => m.user?.type !== 'client').map((m: any) => m.user) || members}
                        onUpdate={onUpdate}
                        canManageChecklists={workspaceRole !== 'client' && workspaceRole !== 'member'}
                    />
                </TabsContent>

                {/* Attachments Tab */}
                <TabsContent value="attachments" className="overflow-hidden px-6 py-4 min-h-0 h-[55vh]">
                    <TaskAttachments
                        task={task}
                        attachments={task.attachments || []}
                        availableMedia={task.project?.workspace?.media || []}
                        onUpdate={onUpdate}
                        canAddAttachments={workspaceRole !== 'client' && workspaceRole !== 'member'}
                        canManageAttachments={workspaceRole !== 'client' && workspaceRole !== 'member'}
                    />
                </TabsContent>
            </Tabs>
        </DialogContent>
    );
}
