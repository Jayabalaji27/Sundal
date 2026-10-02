import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { MessageSquare, MoreHorizontal, Edit, Trash2, Send } from 'lucide-react';
import { Task, TaskComment, User } from '@/types';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';

interface Props {
    task: Task;
    comments: TaskComment[];
    currentUser: User;
    onUpdate?: () => void;
    canAddComments?: boolean;
}

export default function TaskComments({ task, comments, currentUser, onUpdate, canAddComments = true }: Props) {
    const [newComment, setNewComment] = useState('');
    const [editingComment, setEditingComment] = useState<number | null>(null);
    const [editText, setEditText] = useState('');
    const [deleteModal, setDeleteModal] = useState<{ isOpen: boolean; commentId: number | null }>({ isOpen: false, commentId: null });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!newComment.trim()) return;

        router.post(route('task-comments.store', task.id), {
            comment: newComment,
            mentions: []
        }, {
            onSuccess: () => {
                setNewComment('');
                onUpdate?.();
            }
        });
    };

    const handleEdit = (comment: TaskComment) => {
        setEditingComment(comment.id);
        setEditText(comment.comment);
    };

    const handleUpdate = (commentId: number) => {
        router.put(route('task-comments.update', commentId), {
            comment: editText,
            mentions: []
        }, {
            onSuccess: () => {
                setEditingComment(null);
                setEditText('');
                onUpdate?.();
            }
        });
    };

    const handleDelete = (commentId: number) => {
        setDeleteModal({ isOpen: true, commentId });
    };

    const confirmDelete = () => {
        if (deleteModal.commentId) {
            router.delete(route('task-comments.destroy', deleteModal.commentId), {
                onSuccess: () => {
                    setDeleteModal({ isOpen: false, commentId: null });
                    onUpdate?.();
                }
            });
        }
    };

    return (
        <>
            <CrudDeleteModal
                isOpen={deleteModal.isOpen}
                onClose={() => setDeleteModal({ isOpen: false, commentId: null })}
                onConfirm={confirmDelete}
                itemName="this comment"
                entityName="comment"
            />
            <div className="flex flex-col h-full overflow-hidden">
                {/* Comments List */}
                <div className="flex-1 overflow-y-auto pr-2 space-y-4 mb-4 pt-4">
                    {comments.map((comment) => (
                        <div key={comment.id} className="flex space-x-3 p-4 bg-white border border-gray-100 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div className="flex-1">
                                <div className="flex items-center justify-between mb-2">
                                    <div className="flex items-center space-x-2">
                                        <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold uppercase overflow-hidden">
                                            {comment.user?.avatar ? (
                                                <img 
                                                    src={comment.user.avatar} 
                                                    alt={comment.user.name} 
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => {
                                                        e.currentTarget.style.display = 'none';
                                                        e.currentTarget.parentElement!.innerText = comment.user?.name?.substring(0, 2) || '';
                                                    }}
                                                />
                                            ) : (
                                                comment.user?.name?.substring(0, 2)
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold text-gray-900 leading-none">{comment.user?.name}</p>
                                            <p className="text-[10px] text-gray-500 mt-1">
                                                {new Date(comment.created_at).toLocaleString()}
                                            </p>
                                        </div>
                                    </div>
                                    {(comment.can_update || comment.can_delete) && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                    <MoreHorizontal className="h-4 w-4 text-gray-400" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="z-[9999]">
                                                {comment.can_update && (
                                                    <DropdownMenuItem onClick={() => handleEdit(comment)}>
                                                        <Edit className="h-4 w-4 mr-2" />
                                                        Edit
                                                    </DropdownMenuItem>
                                                )}
                                                {comment.can_delete && (
                                                    <DropdownMenuItem
                                                        onClick={() => handleDelete(comment.id)}
                                                        className="text-red-600"
                                                    >
                                                        <Trash2 className="h-4 w-4 mr-2" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>

                                {editingComment === comment.id ? (
                                    <div className="space-y-2 mt-2">
                                        <Textarea
                                            value={editText}
                                            onChange={(e) => setEditText(e.target.value)}
                                            rows={2}
                                            className="resize-none focus-visible:ring-primary"
                                        />
                                        <div className="flex space-x-2">
                                            <Button size="sm" onClick={() => handleUpdate(comment.id)}>
                                                Save
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setEditingComment(null)}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{comment.comment}</p>
                                )}
                            </div>
                        </div>
                    ))}

                    {comments.length === 0 && (
                        <div className="flex flex-col items-center justify-center h-full text-center py-10 text-gray-400">
                            <div className="bg-gray-50 p-4 rounded-full mb-4">
                                <MessageSquare className="h-8 w-8 text-gray-300" />
                            </div>
                            <p className="text-sm font-medium">No comments yet</p>
                            <p className="text-xs">Be the first to comment!</p>
                        </div>
                    )}
                </div>

                {/* Add Comment Form */}
                {canAddComments && (
                    <div className="shrink-0 border-t pt-4 bg-white">
                        <div className="mb-3 flex items-center justify-between">
                            <h4 className="text-sm font-bold text-gray-900">Post comment</h4>
                        </div>
                        <form onSubmit={handleSubmit} className="relative">
                            <Textarea
                                value={newComment}
                                onChange={(e) => setNewComment(e.target.value)}
                                placeholder="Write your message here..."
                                rows={3}
                                className="pr-12 py-3 resize-none border-gray-200 focus-visible:ring-primary focus-visible:border-primary rounded-xl"
                            />
                            <div className="absolute bottom-2 right-2">
                                <Button 
                                    type="submit" 
                                    size="icon" 
                                    disabled={!newComment.trim()}
                                    className="h-8 w-8 rounded-lg text-white shadow-sm disabled:opacity-50 transition-all"
                                >
                                    <Send className="h-4 w-4" />
                                </Button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </>
    );
}