import React, { useState, useEffect } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Plus, Settings, Trash2, GripVertical, Star, MoreHorizontal, Edit } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslation } from 'react-i18next';

interface BugStatus {
    id: number;
    name: string;
    color: string;
    order: number;
    is_default: boolean;
    bugs_count: number;
}

interface Props {
    statuses: BugStatus[];
    permissions?: any;
}

export default function Index({ statuses, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, permissions: pagePermissions } = usePage().props as any;
    const statusPermissions = permissions || pagePermissions;
    const [showModal, setShowModal] = useState(false);
    const [editingStatus, setEditingStatus] = useState<BugStatus | null>(null);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [statusToDelete, setStatusToDelete] = useState<BugStatus | null>(null);

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        name: '',
        color: '#ef4444'
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (editingStatus) {
            toast.loading('Updating status...');
            put(route('bug-statuses.update', editingStatus.id), {
                onSuccess: () => {
                    toast.dismiss();
                    setShowModal(false);
                    setEditingStatus(null);
                    reset();
                },
                onError: () => {
                    toast.dismiss();
                    toast.error('Failed to update status');
                }
            });
        } else {
            toast.loading('Creating status...');
            post(route('bug-statuses.store'), {
                onSuccess: () => {
                    toast.dismiss();
                    setShowModal(false);
                    reset();
                },
                onError: () => {
                    toast.dismiss();
                    toast.error('Failed to create status');
                }
            });
        }
    };

    const handleEdit = (status: BugStatus) => {
        setEditingStatus(status);
        setData({
            name: status.name,
            color: status.color
        });
        setShowModal(true);
    };

    const handleDelete = (status: BugStatus) => {
        setStatusToDelete(status);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (statusToDelete) {
            toast.loading('Deleting status...');
            destroy(route('bug-statuses.destroy', statusToDelete.id), {
                onSuccess: () => {
                    toast.dismiss();
                    setIsDeleteModalOpen(false);
                    setStatusToDelete(null);
                },
                onError: () => {
                    toast.dismiss();
                    setIsDeleteModalOpen(false);
                    setStatusToDelete(null);
                }
            });
        }
    };

    const handleSetDefault = (status: BugStatus) => {
        toast.loading('Setting default status...');
        put(route('bug-statuses.set-default', status.id), {
            onSuccess: () => {
                toast.dismiss();
            },
            onError: () => {
                toast.dismiss();
                toast.error('Failed to set default status');
            }
        });
    };

    const handleDragStart = (e: React.DragEvent, status: BugStatus) => {
        e.dataTransfer.setData('text/plain', status.id.toString());
        e.dataTransfer.effectAllowed = 'move';
        (e.target as HTMLElement).style.opacity = '0.5';
    };

    const handleDragEnd = (e: React.DragEvent) => {
        (e.target as HTMLElement).style.opacity = '1';
        // Remove any lingering visual effects
        document.querySelectorAll('.drag-over').forEach(el => {
            el.classList.remove('drag-over');
        });
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        (e.currentTarget as HTMLElement).classList.add('drag-over');
    };

    const handleDragLeave = (e: React.DragEvent) => {
        (e.currentTarget as HTMLElement).classList.remove('drag-over');
    };

    const handleDrop = (e: React.DragEvent, targetStatus: BugStatus) => {
        e.preventDefault();
        (e.currentTarget as HTMLElement).classList.remove('drag-over');
        
        const draggedStatusId = e.dataTransfer.getData('text/plain');
        if (draggedStatusId && draggedStatusId !== targetStatus.id.toString()) {
            const draggedStatus = statuses.find(s => s.id.toString() === draggedStatusId);
            if (draggedStatus) {
                const reorderedStatuses = [...statuses];
                const draggedIndex = reorderedStatuses.findIndex(s => s.id === draggedStatus.id);
                const targetIndex = reorderedStatuses.findIndex(s => s.id === targetStatus.id);
                
                if (draggedIndex !== -1 && targetIndex !== -1) {
                    reorderedStatuses.splice(draggedIndex, 1);
                    reorderedStatuses.splice(targetIndex, 0, draggedStatus);
                    
                    const statusesWithNewOrder = reorderedStatuses.map((status, index) => ({
                        id: status.id,
                        order: index + 1
                    }));
                    
                    router.post(route('bug-statuses.reorder'), {
                        statuses: statusesWithNewOrder
                    }, {
                        preserveState: true,
                        preserveScroll: true,
                        onError: (errors) => {
                            toast.error('Failed to reorder statuses');
                            console.error('Reorder failed:', errors);
                        }
                    });
                }
            }
        }
    };

    const openCreateModal = () => {
        setEditingStatus(null);
        reset();
        setShowModal(true);
    };

    const pageActions = [];
    
    if (statusPermissions?.create) {
        pageActions.push({
            label: t('Add Status'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default' as const,
            onClick: openCreateModal
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Bugs'), href: route('bugs.index') },
        { title: t('Bug Statuses') }
    ];

    return (
        <PageTemplate 
            title={t('Bug Statuses')} 
            url="/bug-statuses"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
        >
            <Head title={t('Bug Statuses')} />
            <style>{`
                .drag-over {
                    border-color: #3b82f6 !important;
                    background-color: #eff6ff !important;
                    transform: scale(1.02);
                }
            `}</style>
            
            <div className="space-y-8">
                {/* Overview Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white border rounded-lg p-6 shadow-sm">
                        <div className="flex items-center">
                            <div className="p-2 bg-blue-100 rounded-lg">
                                <Settings className="h-6 w-6 text-blue-600" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">{t('Total Statuses')}</p>
                                <p className="text-2xl font-bold text-gray-900">{statuses.length}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white border rounded-lg p-6 shadow-sm">
                        <div className="flex items-center">
                            <div className="p-2 bg-green-100 rounded-lg">
                                <Settings className="h-6 w-6 text-green-600" />
                            </div>
                            <div className="ml-4">
                                <p className="text-sm font-medium text-gray-600">{t('Total Bugs')}</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {statuses.reduce((sum, status) => sum + (status.bugs_count || 0), 0)}
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
                                <p className="text-sm font-medium text-gray-600">{t('Default Status')}</p>
                                <p className="text-lg font-semibold text-gray-900">
                                    {statuses.find(s => s.is_default)?.name || t('None')}
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
                                <p className="text-sm font-medium text-gray-600">{t('Avg Bugs/Status')}</p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {statuses.length > 0 ? Math.round(statuses.reduce((sum, status) => sum + (status.bugs_count || 0), 0) / statuses.length) : 0}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Statuses Management */}
                <div>
                    <div className="flex items-center mb-4">
                        <h2 className="text-xl font-semibold">{t('Bug Workflow Statuses')}</h2>
                        <Badge variant="secondary" className="ml-2">
                            {t('Drag to reorder')}
                        </Badge>
                    </div>
                    <div className="space-y-3">
                        {statuses.map((status) => (
                            <div
                                key={status.id}
                                className="bg-white border rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow"
                                draggable
                                onDragStart={(e) => handleDragStart(e, status)}
                                onDragEnd={handleDragEnd}
                                onDragOver={handleDragOver}
                                onDragLeave={handleDragLeave}
                                onDrop={(e) => handleDrop(e, status)}
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-4">
                                        <div className="cursor-move p-1 hover:bg-gray-100 rounded">
                                            <GripVertical className="h-5 w-5 text-gray-400" />
                                        </div>
                                        <div 
                                            className="w-4 h-4 rounded-full border-2 border-white shadow-sm" 
                                            style={{ backgroundColor: status.color }}
                                        />
                                        <div>
                                            <div className="flex items-center space-x-2">
                                                <h3 className="font-semibold text-gray-900">{status.name}</h3>
                                                {status.is_default && (
                                                    <Badge variant="outline" className="text-xs">
                                                        {t('Default')}
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-sm text-gray-500">Order: {status.order}</p>
                                        </div>
                                    </div>
                                    
                                    <div className="flex items-center space-x-4">
                                        <div className="text-right">
                                            <p className="text-lg font-semibold text-gray-900">
                                                {status.bugs_count || 0}
                                            </p>
                                            <p className="text-sm text-gray-500">{t('bugs')}</p>
                                        </div>
                                        
                                        <div className="text-right">
                                            <code className="text-xs bg-gray-100 px-2 py-1 rounded">
                                                {status.color}
                                            </code>
                                        </div>
                                        
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {statusPermissions?.update && (
                                                    <DropdownMenuItem onClick={() => handleEdit(status)}>
                                                        <Edit className="h-4 w-4 mr-2" />
                                                        {t('Edit')}
                                                    </DropdownMenuItem>
                                                )}
                                                {statusPermissions?.set_default && !status.is_default && (
                                                    <DropdownMenuItem onClick={() => handleSetDefault(status)}>
                                                        <Star className="h-4 w-4 mr-2" />
                                                        {t('Set as Default')}
                                                    </DropdownMenuItem>
                                                )}
                                                {statusPermissions?.delete && status.bugs_count === 0 && (
                                                    <DropdownMenuItem 
                                                        onClick={() => handleDelete(status)}
                                                        className="text-red-600"
                                                        disabled={status.bugs_count > 0}
                                                    >
                                                        <Trash2 className="h-4 w-4 mr-2" />
                                                        {t('Delete')}
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </div>
                        ))}
                        
                        {statuses.length === 0 && (
                            <div className="text-center py-12">
                                <Settings className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-gray-900 mb-2">{t('No statuses yet')}</h3>
                                <p className="text-gray-500 mb-4">{t('Create your first bug status to get started')}</p>
                                {statusPermissions?.create && (
                                    <Button onClick={openCreateModal} className="bg-blue-600 hover:bg-blue-700">
                                        <Plus className="h-4 w-4 mr-2" />
                                        {t('Add Your First Status')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Create Status Dialog */}
            <Dialog open={showModal && !editingStatus} onOpenChange={(open) => {
                setShowModal(open);
                if (!open) reset();
            }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('Create New Status')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Status Name
                            </label>
                            <Input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Enter status name"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Color
                            </label>
                            <div className="flex items-center space-x-3">
                                <input
                                    type="color"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    className="w-12 h-10 rounded-md border border-gray-300 cursor-pointer"
                                />
                                <Input
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    placeholder="#ef4444"
                                    className="flex-1"
                                />
                            </div>
                        </div>
                        <div className="flex justify-end space-x-2 pt-4">
                            <Button type="button" variant="outline" onClick={() => {
                                setShowModal(false);
                                reset();
                            }}>
                                Cancel
                            </Button>
                            <Button type="submit" className="bg-blue-600 hover:bg-blue-700">
                                Create Status
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Status Dialog */}
            <Dialog open={!!editingStatus} onOpenChange={(open) => {
                if (!open) {
                    setEditingStatus(null);
                    reset();
                }
            }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{t('Edit Status')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Status Name
                            </label>
                            <Input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Enter status name"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Color
                            </label>
                            <div className="flex items-center space-x-3">
                                <input
                                    type="color"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    className="w-12 h-10 rounded-md border border-gray-300 cursor-pointer"
                                />
                                <Input
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    placeholder="#ef4444"
                                    className="flex-1"
                                />
                            </div>
                        </div>
                        <div className="flex justify-end space-x-2 pt-4">
                            <Button type="button" variant="outline" onClick={() => {
                                setEditingStatus(null);
                                reset();
                            }}>
                                Cancel
                            </Button>
                            <Button type="submit" className="bg-blue-600 hover:bg-blue-700">
                                Update Status
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => {
                    setIsDeleteModalOpen(false);
                    setStatusToDelete(null);
                }}
                onConfirm={handleDeleteConfirm}
                itemName={statusToDelete?.name || ''}
                entityName="bug status"
            />
        </PageTemplate>
    );
}