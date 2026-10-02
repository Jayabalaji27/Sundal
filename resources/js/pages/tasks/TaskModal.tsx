import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Calendar, User, MessageSquare, CheckSquare, Paperclip, Flag, Layers, FolderOpen } from 'lucide-react';
import { Task, User as UserType, TaskStage, ProjectMilestone } from '@/types';
import TaskComments from '@/components/tasks/TaskComments';
import TaskChecklist from '@/components/tasks/TaskChecklist';
import TaskAttachments from '@/components/tasks/TaskAttachments';
import { toast } from '@/components/custom-toast';

interface Props {
    task: Task;
    isOpen: boolean;
    onClose: () => void;
    members: UserType[];
    stages: TaskStage[];
    milestones: ProjectMilestone[];
    permissions?: any;
    workspaceRole?: string;
    mode?: 'view' | 'edit';
}

export default function TaskModal({ task, isOpen, onClose, members, stages, milestones, permissions, workspaceRole: initialWorkspaceRole, mode = 'edit' }: Props) {
    const { t } = useTranslation();
    const [currentTask, setCurrentTask] = useState(task);
    const [taskPermissions, setTaskPermissions] = useState(permissions);
    const [workspaceRole, setWorkspaceRole] = useState<string | null>(initialWorkspaceRole || null);

    const refreshTask = async () => {
        try {
            const response = await fetch(route('tasks.show', task.id));
            const data = await response.json();
            setCurrentTask(data.task);
            setTaskPermissions(data.permissions);
            setWorkspaceRole(data.workspace_role);
        } catch (error) {
            console.error('Failed to refresh task:', error);
        }
    };



    const handleStageChange = (stageId: string) => {
        router.put(route('tasks.change-stage', task.id), {
            task_stage_id: stageId
        }, {
            onSuccess: () => {
                refreshTask();
            },
            onError: () => {
                toast.error('Failed to update stage');
            }
        });
    };

    const handlePriorityChange = (priority: string) => {
        router.put(route('tasks.update', task.id), {
            title: currentTask.title,
            description: currentTask.description || '',
            priority: priority,
            start_date: currentTask.start_date,
            end_date: currentTask.end_date,
            assigned_to: currentTask.assigned_to?.id,
            milestone_id: currentTask.milestone_id
        }, {
            onSuccess: () => {
                refreshTask();
            },
            onError: () => {
                toast.error('Failed to update priority');
            }
        });
    };

    const handleAssigneeChange = (assigneeId: string) => {
        const assignedUserId = assigneeId === 'unassigned' ? null : parseInt(assigneeId);
        
        router.put(route('tasks.update', task.id), {
            title: currentTask.title,
            description: currentTask.description || '',
            priority: currentTask.priority,
            start_date: currentTask.start_date,
            end_date: currentTask.end_date,
            assigned_to: assignedUserId,
            milestone_id: currentTask.milestone_id
        }, {
            onSuccess: () => {
                refreshTask();
            },
            onError: () => {
                toast.error('Failed to update assignee');
            }
        });
    };

    const handleDateChange = (field: string, value: string) => {
        router.put(route('tasks.update', task.id), {
            title: currentTask.title,
            description: currentTask.description || '',
            priority: currentTask.priority,
            start_date: field === 'start_date' ? (value || null) : currentTask.start_date,
            end_date: field === 'end_date' ? (value || null) : currentTask.end_date,
            assigned_to: currentTask.assigned_to?.id,
            milestone_id: currentTask.milestone_id
        }, {
            onSuccess: () => {
                refreshTask();
            },
            onError: () => {
                toast.error('Failed to update date');
            }
        });
    };

    const isViewMode = mode === 'view';

    const getPriorityColor = (priority: string) => {
        switch (priority) {
            case 'critical': return 'bg-red-50 text-red-700 ring-red-600/20';
            case 'high': return 'bg-orange-50 text-orange-700 ring-orange-600/20';
            case 'medium': return 'bg-yellow-50 text-yellow-700 ring-yellow-600/20';
            case 'low': return 'bg-green-50 text-green-700 ring-green-600/20';
            default: return 'bg-gray-50 text-gray-700 ring-gray-600/20';
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-5xl h-[90vh] flex flex-col p-0 overflow-hidden">
                <DialogHeader className="p-6 pb-2 border-b">
                    <DialogTitle>{currentTask.title}</DialogTitle>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto md:overflow-hidden p-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 h-full">
                    {/* Main Content */}
                    <div className="md:col-span-2 flex flex-col space-y-4 overflow-hidden">
                        <div>
                            <h3 className="text-sm font-medium text-gray-900 mb-2">{t('Description')}</h3>
                            <div className="text-sm text-gray-600 max-h-32 overflow-y-auto pr-2">
                                {currentTask.description || t('-')}
                            </div>
                        </div>

                        {/* Tabs for Comments, Checklist, Attachments */}
                        <Tabs defaultValue="comments" className="flex-1 flex flex-col overflow-hidden">
                            <TabsList className="h-auto shrink-0 flex-wrap">
                                <TabsTrigger value="comments" className="flex items-center space-x-2">
                                    <MessageSquare className="h-4 w-4" />
                                    <span>{t('Comments')} ({currentTask.comments?.length || 0})</span>
                                </TabsTrigger>
                                <TabsTrigger value="checklist" className="flex items-center space-x-2">
                                    <CheckSquare className="h-4 w-4" />
                                    <span>{t('Checklist')} ({currentTask.checklists?.length || 0})</span>
                                </TabsTrigger>
                                <TabsTrigger value="attachments" className="flex items-center space-x-2">
                                    <Paperclip className="h-4 w-4" />
                                    <span>{t('Files')} ({currentTask.attachments?.length || 0})</span>
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="comments" className="flex-1 flex flex-col overflow-hidden mt-0">
                                <TaskComments 
                                    task={currentTask} 
                                    comments={currentTask.comments || []} 
                                    currentUser={members[0]} 
                                    onUpdate={refreshTask}
                                    canAddComments={workspaceRole !== 'client'}
                                />
                            </TabsContent>

                            <TabsContent value="checklist" className="flex-1 flex flex-col overflow-hidden mt-0">
                                <TaskChecklist 
                                    task={currentTask} 
                                    checklist={currentTask.checklists || []} 
                                    members={currentTask.project?.members?.filter(m => m.user?.type !== 'client').map(m => m.user) || members} 
                                    onUpdate={refreshTask}
                                    canManageChecklists={workspaceRole !== 'client' && workspaceRole !== 'member'}
                                />
                            </TabsContent>

                            <TabsContent value="attachments" className="flex-1 flex flex-col overflow-hidden mt-0">
                                <TaskAttachments 
                                    task={currentTask} 
                                    attachments={currentTask.attachments || []} 
                                    availableMedia={currentTask.project?.workspace?.media || []}
                                    onUpdate={refreshTask}
                                    canAddAttachments={workspaceRole !== 'client' && workspaceRole !== 'member'}
                                    canManageAttachments={workspaceRole !== 'client' && workspaceRole !== 'member'}
                                />
                            </TabsContent>
                        </Tabs>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4 overflow-y-auto pr-2 border-t pt-4 md:border-t-0 md:pt-0 md:border-l md:pl-4">

                        {/* Stage */}
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Layers className="h-4 w-4" />
                                {t('Stage')}
                            </label>
                            {!isViewMode && taskPermissions?.change_status ? (
                                <div className="mt-1">
                                    <Select value={currentTask.task_stage_id.toString()} onValueChange={handleStageChange}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent className="z-[9999]">
                                            {stages.map((stage) => (
                                                <SelectItem key={stage.id} value={stage.id.toString()}>
                                                    <div className="flex items-center space-x-2">
                                                        <div className="w-3 h-3 rounded-full" style={{ backgroundColor: stage.color }} />
                                                        <span>{stage.name}</span>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : (
                                <div className="mt-1 flex items-center gap-2">
                                    <div className="w-3 h-3 rounded-full shrink-0" style={{ backgroundColor: currentTask.task_stage?.color }} />
                                    <span className="text-sm font-medium text-gray-900 dark:text-white">{currentTask.task_stage?.name || '-'}</span>
                                </div>
                            )}
                        </div>

                        {/* Priority */}
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Flag className="h-4 w-4" />
                                {t('Priority')}
                            </label>
                            {!isViewMode && taskPermissions?.update && workspaceRole !== 'client' ? (
                                <div className="mt-1">
                                    <Select value={currentTask.priority} onValueChange={handlePriorityChange}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent className="z-[9999]">
                                            <SelectItem value="low">{t('Low')}</SelectItem>
                                            <SelectItem value="medium">{t('Medium')}</SelectItem>
                                            <SelectItem value="high">{t('High')}</SelectItem>
                                            <SelectItem value="critical">{t('Critical')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : (
                                <div className="mt-1">
                                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${getPriorityColor(currentTask.priority)}`}>
                                        {currentTask.priority ? t(currentTask.priority.charAt(0).toUpperCase() + currentTask.priority.slice(1)) : '-'}
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Assignee */}
                        {workspaceRole !== 'client' && (
                            <div>
                                <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                    <User className="h-4 w-4" />
                                    {t('Assignee')}
                                </label>
                                {!isViewMode && taskPermissions?.assign_users === true ? (
                                    <div className="mt-1">
                                        <Select value={currentTask.assigned_to?.id?.toString() || 'unassigned'} onValueChange={handleAssigneeChange}>
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('Select assignee')} />
                                            </SelectTrigger>
                                            <SelectContent className="z-[9999]">
                                                <SelectItem value="unassigned">{t('Unassigned')}</SelectItem>
                                                {(() => {
                                                    const projectMembers = currentTask.project?.members?.filter(m => m.user?.type !== 'client').map(m => m.user) || [];
                                                    return projectMembers.length > 0 ? projectMembers : members;
                                                })().map((member) => (
                                                    <SelectItem key={member.id} value={member.id.toString()}>
                                                        {member.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                ) : (
                                    <div className="mt-1">
                                        <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20">
                                            {currentTask.assigned_to?.name || t('Unassigned')}
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Dates */}
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                {t('Start Date')}
                            </label>
                            {!isViewMode && taskPermissions?.update && workspaceRole !== 'client' ? (
                                <Input
                                    type="date"
                                    value={currentTask.start_date?.split('T')[0] || ''}
                                    onChange={(e) => handleDateChange('start_date', e.target.value)}
                                    className="mt-1"
                                />
                            ) : (
                                <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                    {currentTask.start_date ? new Date(currentTask.start_date).toLocaleDateString() : '-'}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                {t('Due Date')}
                            </label>
                            {!isViewMode && taskPermissions?.update && workspaceRole !== 'client' ? (
                                <Input
                                    type="date"
                                    value={currentTask.end_date?.split('T')[0] || ''}
                                    onChange={(e) => handleDateChange('end_date', e.target.value)}
                                    className="mt-1"
                                />
                            ) : (
                                <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                    {currentTask.end_date ? new Date(currentTask.end_date).toLocaleDateString() : '-'}
                                </p>
                            )}
                        </div>

                        {/* Progress */}
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Layers className="h-4 w-4" />
                                {t('Progress')}
                            </label>
                            <div className="mt-2 flex items-center gap-2">
                                <div className="flex-1 bg-gray-200 rounded-full h-2">
                                    <div
                                        className="bg-blue-600 h-2 rounded-full transition-all"
                                        style={{ width: `${currentTask.progress || 0}%` }}
                                    />
                                </div>
                                <span className="text-xs font-medium text-gray-600 shrink-0">{currentTask.progress || 0}%</span>
                            </div>
                        </div>

                        {/* Project */}
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <FolderOpen className="h-4 w-4" />
                                {t('Project')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{currentTask.project?.title || '-'}</p>
                        </div>

                        {/* Milestone */}
                        {currentTask.milestone && (
                            <div>
                                <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                    <Flag className="h-4 w-4" />
                                    {t('Milestone')}
                                </label>
                                <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{currentTask.milestone.title}</p>
                            </div>
                        )}

                    </div>
                </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}