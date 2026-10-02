import React, { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { DragDropContext, Droppable, Draggable, DropResult } from '@hello-pangea/dnd';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { Plus, MoreHorizontal, Edit, Trash2, GripVertical, Settings } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { TaskStage } from '@/types';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface Props {
    stages: TaskStage[];
    permissions?: any;
}

export default function TaskStagesIndex({ stages, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, permissions: pagePermissions } = usePage().props as any;
    const stagePermissions = permissions || pagePermissions;
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingStage, setEditingStage] = useState<TaskStage | null>(null);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [stageToDelete, setStageToDelete] = useState<TaskStage | null>(null);
    const [stagesList, setStagesList] = useState(stages);
    const [formData, setFormData] = useState({
        name: '',
        color: '#3b82f6'
    });

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const resetForm = () => {
        setFormData({ name: '', color: '#3b82f6' });
        setEditingStage(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingStage) {
            toast.loading('Updating stage...');
            router.put(route('task-stages.update', editingStage.id), formData, {
                onSuccess: (page) => {
                    toast.dismiss();
                    setStagesList(page.props.stages);
                    resetForm();
                },
                onError: () => {
                    toast.dismiss();
                    toast.error('Failed to update stage');
                },
                preserveState: true,
                preserveScroll: true
            });
        } else {
            toast.loading('Creating stage...');
            router.post(route('task-stages.store'), formData, {
                onSuccess: (page) => {
                    toast.dismiss();
                    setStagesList(page.props.stages);
                    setIsCreateOpen(false);
                    resetForm();
                },
                onError: () => {
                    toast.dismiss();
                    toast.error('Failed to create stage');
                },
                preserveState: true,
                preserveScroll: true
            });
        }
    };

    const handleEdit = (stage: TaskStage) => {
        resetForm();
        setEditingStage(stage);
        setFormData({ name: stage.name, color: stage.color });
    };

    const handleCreateNew = () => {
        resetForm();
        setIsCreateOpen(true);
    };

    const handleDelete = (stage: TaskStage) => {
        setStageToDelete(stage);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (stageToDelete) {
            toast.loading('Deleting stage...');
            router.delete(route('task-stages.destroy', stageToDelete.id), {
                onSuccess: (page) => {
                    toast.dismiss();
                    setStagesList(page.props.stages);
                    setIsDeleteModalOpen(false);
                    setStageToDelete(null);
                },
                onError: () => {
                    toast.dismiss();
                    setIsDeleteModalOpen(false);
                    setStageToDelete(null);
                },
                preserveState: true,
                preserveScroll: true
            });
        }
    };

    const handleSetDefault = (stageId: number) => {
        toast.loading('Setting default stage...');
        router.put(route('task-stages.set-default', stageId), {}, {
            onSuccess: (page) => {
                toast.dismiss();
                setStagesList(page.props.stages);
            },
            onError: () => {
                toast.dismiss();
                toast.error('Failed to set default stage');
            },
            preserveState: true,
            preserveScroll: true
        });
    };

    const handleDragEnd = (result: DropResult) => {
        if (!result.destination) return;

        const items = Array.from(stagesList);
        const [reorderedItem] = items.splice(result.source.index, 1);
        items.splice(result.destination.index, 0, reorderedItem);

        // Update order values
        const updatedItems = items.map((item, index) => ({
            ...item,
            order: index + 1
        }));

        setStagesList(updatedItems);

        // Send reorder request to backend
        const reorderData = updatedItems.map((stage, index) => ({
            id: stage.id,
            order: index + 1
        }));

        router.post(route('task-stages.reorder'), {
            stages: reorderData
        }, {
            onError: () => {
                toast.error('Failed to reorder stages');
                setStagesList(stages); // Revert on error
            },
            preserveState: true,
            preserveScroll: true
        });
    };

    const getPriorityColor = (priority: string) => {
        switch (priority) {
            case 'critical': return 'bg-red-100 text-red-800';
            case 'high': return 'bg-orange-100 text-orange-800';
            case 'medium': return 'bg-yellow-100 text-yellow-800';
            case 'low': return 'bg-green-100 text-green-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const pageActions = [];
    
    if (stagePermissions?.create) {
        pageActions.push({
            label: t('Add Stage'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default' as const,
            onClick: handleCreateNew
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Tasks'), href: route('tasks.index') },
        { title: t('Task Stages') }
    ];

    return (
        <PageTemplate 
            title={t('Task Stages')} 
            url="/task-stages"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
        >
            <Head title={t('Task Stages')} />
            
            <div className="space-y-8">
                {/* Overview Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white border rounded-lg p-6 shadow-sm">
                        <div className="flex items-center">
                            <div className="p-2 bg-blue-100 rounded-lg">
                                <Settings className="h-6 w-6 text-blue-600" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">{t('Total Stages')}</p>
                                <p className="text-2xl font-bold text-gray-900">{stagesList.length}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white border rounded-lg p-6 shadow-sm">
                        <div className="flex items-center">
                            <div className="p-2 bg-green-100 rounded-lg">
                                <Settings className="h-6 w-6 text-green-600" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">{t('Total Tasks')}</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {stagesList.reduce((sum, stage) => sum + (stage.tasks_count || 0), 0)}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white border rounded-lg p-6 shadow-sm">
                        <div className="flex items-center">
                            <div className="p-2 bg-purple-100 rounded-lg">
                                <Settings className="h-6 w-6 text-purple-600" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">{t('Default Stage')}</p>
                                <p className="text-lg font-semibold text-gray-900">
                                    {stagesList.find(s => s.is_default)?.name || t('None')}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white border rounded-lg p-6 shadow-sm">
                        <div className="flex items-center">
                            <div className="p-2 bg-orange-100 rounded-lg">
                                <Settings className="h-6 w-6 text-orange-600" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">{t('Avg Tasks/Stage')}</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {stagesList.length > 0 ? Math.round(stagesList.reduce((sum, stage) => sum + (stage.tasks_count || 0), 0) / stagesList.length) : 0}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stages Management */}
                <div>
                    <div className="flex items-center mb-4">
                        <h2 className="text-xl font-semibold">{t('Workflow Stages')}</h2>
                        <Badge variant="secondary" className="ml-2">
                            {t('Drag to reorder')}
                        </Badge>
                    </div>
                        <DragDropContext onDragEnd={handleDragEnd}>
                            <Droppable droppableId="stages">
                                {(provided) => (
                                    <div {...provided.droppableProps} ref={provided.innerRef} className="space-y-3">
                                        {stagesList.map((stage, index) => (
                                            <Draggable key={stage.id} draggableId={stage.id.toString()} index={index}>
                                                {(provided, snapshot) => (
                                                    <div
                                                        ref={provided.innerRef}
                                                        {...provided.draggableProps}
                                                        className={`bg-white border rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow ${
                                                            snapshot.isDragging ? 'shadow-lg ring-2 ring-blue-500' : ''
                                                        }`}
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div className="flex items-center space-x-4">
                                                                <div
                                                                    {...provided.dragHandleProps}
                                                                    className="cursor-move p-1 hover:bg-gray-100 rounded"
                                                                >
                                                                    <GripVertical className="h-5 w-5 text-gray-400" />
                                                                </div>
                                                                <div 
                                                                    className="w-4 h-4 rounded-full border-2 border-white shadow-sm" 
                                                                    style={{ backgroundColor: stage.color }}
                                                                />
                                                                <div>
                                                                    <div className="flex items-center space-x-2">
                                                                        <h3 className="font-semibold text-gray-900">{stage.name}</h3>
                                                                        {stage.is_default && (
                                                                            <Badge variant="outline" className="text-xs">
                                                                                {t('Default')}
                                                                            </Badge>
                                                                        )}
                                                                    </div>
                                                                    <p className="text-sm text-gray-500">{t('Order')}: {stage.order}</p>
                                                                </div>
                                                            </div>
                                                            
                                                            <div className="flex items-center space-x-4">
                                                                <div className="text-right">
                                                                    <p className="text-lg font-semibold text-gray-900">
                                                                        {stage.tasks_count || 0}
                                                                    </p>
                                                                    <p className="text-sm text-gray-500">{t('tasks')}</p>
                                                                </div>
                                                                
                                                                <div className="text-right">
                                                                    <code className="text-xs bg-gray-100 px-2 py-1 rounded">
                                                                        {stage.color}
                                                                    </code>
                                                                </div>
                                                                
                                                                <DropdownMenu>
                                                                    <DropdownMenuTrigger asChild>
                                                                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                                            <MoreHorizontal className="h-4 w-4" />
                                                                        </Button>
                                                                    </DropdownMenuTrigger>
                                                                    <DropdownMenuContent align="end">
                                                                        {stagePermissions?.update && (
                                                                            <DropdownMenuItem onClick={() => handleEdit(stage)}>
                                                                                <Edit className="h-4 w-4 mr-2" />
                                                                                {t('Edit')}
                                                                            </DropdownMenuItem>
                                                                        )}
                                                                        {stagePermissions?.set_default && !stage.is_default && (
                                                                            <DropdownMenuItem onClick={() => handleSetDefault(stage.id)}>
                                                                                <Settings className="h-4 w-4 mr-2" />
                                                                                {t('Set as Default')}
                                                                            </DropdownMenuItem>
                                                                        )}
                                                                        {stagePermissions?.delete && (
                                                                            <DropdownMenuItem 
                                                                                onClick={() => handleDelete(stage)}
                                                                                className="text-red-600"
                                                                                disabled={stage.tasks_count > 0}
                                                                            >
                                                                                <Trash2 className="h-4 w-4 mr-2" />
                                                                                {t('Delete')}
                                                                            </DropdownMenuItem>
                                                                        )}
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            </div>
                                                        </div>
                                                        
                                                        {/* Show some tasks if available */}
                                                        {stage.tasks && stage.tasks.length > 0 && (
                                                            <div className="mt-3 pt-3 border-t border-gray-100">
                                                                <p className="text-xs font-medium text-gray-500 mb-2">{t('Recent Tasks')}:</p>
                                                                <div className="flex flex-wrap gap-2">
                                                                    {stage.tasks.slice(0, 3).map((task) => (
                                                                        <div key={task.id} className="flex items-center space-x-2 bg-gray-50 px-2 py-1 rounded text-xs">
                                                                            <span className="truncate max-w-32">{task.title}</span>
                                                                            <Badge 
                                                                                variant="secondary" 
                                                                                className={`text-xs ${getPriorityColor(task.priority)}`}
                                                                            >
                                                                                {task.priority}
                                                                            </Badge>
                                                                        </div>
                                                                    ))}
                                                                    {stage.tasks.length > 3 && (
                                                                        <span className="text-xs text-gray-400">+{stage.tasks.length - 3} {t('more')}</span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </Draggable>
                                        ))}
                                        {provided.placeholder}
                                    </div>
                                )}
                            </Droppable>
                        </DragDropContext>
                        
                        {stagesList.length === 0 && (
                            <div className="text-center py-12">
                                <Settings className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-gray-900 mb-2">{t('No stages yet')}</h3>
                                <p className="text-gray-500 mb-4">{t('Create your first task stage to get started')}</p>
                                {stagePermissions?.create && (
                                    <Button onClick={handleCreateNew} className="bg-blue-600 hover:bg-blue-700">
                                        <Plus className="h-4 w-4 mr-2" />
                                        {t('Add Your First Stage')}
                                    </Button>
                                )}
                            </div>
                        )}
                </div>
                </div>

            {/* Create Stage Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={(open) => {
                setIsCreateOpen(open);
                if (!open) resetForm();
            }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('Create New Stage')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Stage Name')}
                            </label>
                            <Input
                                value={formData.name}
                                onChange={(e) => setFormData({...formData, name: e.target.value})}
                                placeholder={t('Enter stage name')}
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Color')}
                            </label>
                            <div className="flex items-center space-x-3">
                                <input
                                    type="color"
                                    value={formData.color}
                                    onChange={(e) => setFormData({...formData, color: e.target.value})}
                                    className="w-12 h-10 rounded-md border border-gray-300 cursor-pointer"
                                />
                                <Input
                                    value={formData.color}
                                    onChange={(e) => setFormData({...formData, color: e.target.value})}
                                    placeholder="#3b82f6"
                                    className="flex-1"
                                />
                            </div>
                        </div>
                        <div className="flex justify-end space-x-2 pt-4">
                            <Button type="button" variant="outline" onClick={() => {
                                setIsCreateOpen(false);
                                resetForm();
                            }}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" className="bg-blue-600 hover:bg-blue-700">
                                {t('Create Stage')}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Stage Dialog */}
            <Dialog open={!!editingStage} onOpenChange={(open) => {
                if (!open) resetForm();
            }}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>{t('Edit Stage')}</DialogTitle>
                        </DialogHeader>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    {t('Stage Name')}
                                </label>
                                <Input
                                    value={formData.name}
                                    onChange={(e) => setFormData({...formData, name: e.target.value})}
                                    placeholder={t('Enter stage name')}
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    {t('Color')}
                                </label>
                                <div className="flex items-center space-x-3">
                                    <input
                                        type="color"
                                        value={formData.color}
                                        onChange={(e) => setFormData({...formData, color: e.target.value})}
                                        className="w-12 h-10 rounded-md border border-gray-300 cursor-pointer"
                                    />
                                    <Input
                                        value={formData.color}
                                        onChange={(e) => setFormData({...formData, color: e.target.value})}
                                        placeholder="#3b82f6"
                                        className="flex-1"
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end space-x-2 pt-4">
                                <Button type="button" variant="outline" onClick={() => resetForm()}>
                                    {t('Cancel')}
                                </Button>
                                <Button type="submit" className="bg-blue-600 hover:bg-blue-700">
                                    {t('Update Stage')}
                                </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>

            {/* Delete Modal */}
            <EnhancedDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => {
                    setIsDeleteModalOpen(false);
                    setStageToDelete(null);
                }}
                onConfirm={handleDeleteConfirm}
                itemName={stageToDelete?.name || ''}
                entityName={t('task stage')}
                warningMessage={stageToDelete?.tasks_count > 0 
                    ? t('This stage contains {{count}} task{{s}}. Please move or delete these tasks first.', { count: stageToDelete.tasks_count, s: stageToDelete.tasks_count > 1 ? 's' : '' })
                    : t('This action cannot be undone.')
                }
                additionalInfo={stageToDelete?.tasks_count > 0 ? [
                    t('{{count}} task{{s}} will need to be reassigned', { count: stageToDelete.tasks_count, s: stageToDelete.tasks_count > 1 ? 's' : '' }),
                    t('Stage order will be automatically adjusted')
                ] : [
                    t('Stage order will be automatically adjusted')
                ]}
            />
        </PageTemplate>
    );
}