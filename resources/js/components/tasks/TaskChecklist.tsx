import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { CheckSquare, Square, Plus, MoreHorizontal, Edit, Trash2, Calendar, User } from 'lucide-react';
import { Task, TaskChecklist as ChecklistItem, User as UserType } from '@/types';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';

interface Props {
    task: Task;
    checklist: ChecklistItem[];
    members: UserType[];
    onUpdate?: () => void;
    canManageChecklists?: boolean;
}

export default function TaskChecklist({ task, checklist, members, onUpdate, canManageChecklists = true }: Props) {
    const [newItem, setNewItem] = useState('');
    const [editingItem, setEditingItem] = useState<number | null>(null);
    const [editData, setEditData] = useState({
        title: '',
        assigned_to: '',
        due_date: ''
    });
    const [deleteModal, setDeleteModal] = useState<{ isOpen: boolean; itemId: number | null }>({ isOpen: false, itemId: null });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!newItem.trim()) return;

        router.post(route('task-checklists.store', task.id), {
            title: newItem,
            assigned_to: '',
            due_date: ''
        }, {
            onSuccess: () => {
                setNewItem('');
                onUpdate?.();
            }
        });
    };

    const handleToggle = (itemId: number) => {
        router.post(route('task-checklists.toggle', itemId), {}, {
            onSuccess: () => {
                onUpdate?.();
            }
        });
    };

    const handleEdit = (item: ChecklistItem) => {
        setEditingItem(item.id);
        setEditData({
            title: item.title,
            assigned_to: item.assigned_to?.id?.toString() || '',
            due_date: item.due_date || ''
        });
    };

    const handleUpdate = (itemId: number) => {
        router.put(route('task-checklists.update', itemId), editData, {
            onSuccess: () => {
                setEditingItem(null);
                setEditData({ title: '', assigned_to: '', due_date: '' });
                onUpdate?.();
            }
        });
    };

    const handleDelete = (itemId: number) => {
        setDeleteModal({ isOpen: true, itemId });
    };

    const confirmDelete = () => {
        if (deleteModal.itemId) {
            router.delete(route('task-checklists.destroy', deleteModal.itemId), {
                onSuccess: () => {
                    setDeleteModal({ isOpen: false, itemId: null });
                    onUpdate?.();
                }
            });
        }
    };

    const completedCount = checklist.filter(item => item.is_completed).length;
    const progressPercentage = checklist.length > 0 ? (completedCount / checklist.length) * 100 : 0;

    return (
        <>
            <CrudDeleteModal
                isOpen={deleteModal.isOpen}
                onClose={() => setDeleteModal({ isOpen: false, itemId: null })}
                onConfirm={confirmDelete}
                itemName="this checklist item"
                entityName="checklist item"
            />
            <div className="flex flex-col h-full overflow-hidden pt-4">
                {/* Progress Bar */}
                {checklist.length > 0 && (
                    <div className="shrink-0 space-y-2 mb-4 px-1">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-600 font-medium">Progress</span>
                            <span className="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">{completedCount}/{checklist.length} completed</span>
                        </div>
                        <div className="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                            <div 
                                className="bg-green-500 h-2 rounded-full transition-all duration-500" 
                                style={{ width: `${progressPercentage}%` }}
                            />
                        </div>
                    </div>
                )}

                {/* Checklist Items */}
                <div className="flex-1 overflow-y-auto pr-2 space-y-2 mb-4">
                    {checklist.map((item) => (
                        <div key={item.id} className="flex items-start space-x-3 p-3 bg-white border border-gray-100 rounded-xl hover:shadow-sm transition-all group">
                            {canManageChecklists && (
                                <button
                                    onClick={() => handleToggle(item.id)}
                                    className="mt-0.5 cursor-pointer text-gray-400 hover:text-gray-600 transition-colors"
                                >
                                    {item.is_completed ? (
                                        <CheckSquare className="h-5 w-5 text-green-500 fill-green-50" />
                                    ) : (
                                        <Square className="h-5 w-5" />
                                    )}
                                </button>
                            )}

                            <div className="flex-1 min-w-0">
                                {editingItem === item.id ? (
                                    <div className="space-y-3 p-1">
                                        <Input
                                            value={editData.title}
                                            onChange={(e) => setEditData({...editData, title: e.target.value})}
                                            placeholder="Checklist item title"
                                            className="focus-visible:ring-blue-500"
                                        />
                                        <div className="grid grid-cols-2 gap-2">
                                            <Select 
                                                value={editData.assigned_to || 'unassigned'} 
                                                onValueChange={(value) => setEditData({...editData, assigned_to: value === 'unassigned' ? '' : value})}
                                            >
                                                <SelectTrigger className="h-9">
                                                    <SelectValue placeholder="Assign to" />
                                                </SelectTrigger>
                                                <SelectContent className="z-[9999]">
                                                    <SelectItem value="unassigned">Unassigned</SelectItem>
                                                    {members.map((member) => (
                                                        <SelectItem key={member.id} value={member.id.toString()}>
                                                            {member.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Input
                                                type="date"
                                                value={editData.due_date}
                                                onChange={(e) => setEditData({...editData, due_date: e.target.value})}
                                                className="h-9"
                                            />
                                        </div>
                                        <div className="flex space-x-2">
                                            <Button size="sm" onClick={() => handleUpdate(item.id)}>
                                                Save
                                            </Button>
                                            <Button 
                                                size="sm" 
                                                variant="outline" 
                                                onClick={() => setEditingItem(null)}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <div className={`text-sm font-medium ${item.is_completed ? 'line-through text-gray-400' : 'text-gray-800'}`}>
                                            {item.title}
                                        </div>
                                        {(item.assigned_to || item.due_date) && (
                                            <div className="flex items-center space-x-4 mt-2 text-[10px] text-gray-500">
                                                {item.assigned_to && (
                                                    <div className="flex items-center space-x-1 bg-gray-50 px-2 py-0.5 rounded">
                                                        <User className="h-3 w-3" />
                                                        <span>{item.assigned_to.name}</span>
                                                    </div>
                                                )}
                                                {item.due_date && (
                                                    <div className="flex items-center space-x-1 bg-gray-50 px-2 py-0.5 rounded">
                                                        <Calendar className="h-3 w-3" />
                                                        <span>{new Date(item.due_date).toLocaleDateString()}</span>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>

                            {(item.can_update || item.can_delete) && (
                                <div className="opacity-0 group-hover:opacity-100 transition-opacity">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                <MoreHorizontal className="h-4 w-4 text-gray-400" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="z-[9999]">
                                            {item.can_update && (
                                                <DropdownMenuItem onClick={() => handleEdit(item)}>
                                                    <Edit className="h-4 w-4 mr-2" />
                                                    Edit
                                                </DropdownMenuItem>
                                            )}
                                            {item.can_delete && (
                                                <DropdownMenuItem 
                                                    onClick={() => handleDelete(item.id)}
                                                    className="text-red-600"
                                                >
                                                    <Trash2 className="h-4 w-4 mr-2" />
                                                    Delete
                                                </DropdownMenuItem>
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            )}
                        </div>
                    ))}

                    {checklist.length === 0 && (
                        <div className="flex flex-col items-center justify-center h-full text-center py-10 text-gray-400">
                            <div className="bg-gray-50 p-4 rounded-full mb-4">
                                <CheckSquare className="h-8 w-8 text-gray-300" />
                            </div>
                            <p className="text-sm font-medium">No checklist items yet</p>
                            <p className="text-xs">Add your first item to stay organized!</p>
                        </div>
                    )}
                </div>

                {/* Add Item */}
                {canManageChecklists && (
                    <div className="shrink-0 border-t pt-4 bg-white">
                        <div className="mb-3 flex items-center justify-between">
                            <h4 className="text-sm font-bold text-gray-900">Add checklist item</h4>
                        </div>
                        <form onSubmit={handleSubmit} className="flex space-x-2">
                            <Input
                                value={newItem}
                                onChange={(e) => setNewItem(e.target.value)}
                                placeholder="Add checklist item..."
                                className="flex-1 focus-visible:ring-blue-500 rounded-xl"
                            />
                            <Button type="submit" size="sm" disabled={!newItem.trim()} className="text-white rounded-xl px-4">
                                <Plus className="h-4 w-4 mr-2" />
                                Add
                            </Button>
                        </form>
                    </div>
                )}
            </div>
        </>
    );
}