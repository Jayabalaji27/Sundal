import { useState, useEffect } from 'react';
import { useForm, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { X, Send, Edit, Trash2, MessageSquare, Paperclip, Download, MoreVertical, Eye, File, FileText, FileSpreadsheet, FileArchive, FileCode, Image } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';
import { todayLocalDateString } from '@/utils/formHelpers';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    todo?: any;
    workspaceMembers: any[];
}

export default function TodoFormModal({ isOpen, onClose, todo, workspaceMembers }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const [activeTab, setActiveTab] = useState('details');
    const [todoData, setTodoData] = useState(todo);
    const [editingComment, setEditingComment] = useState<number | null>(null);
    const [editCommentText, setEditCommentText] = useState('');
    const [hoveredAttachment, setHoveredAttachment] = useState<number | null>(null);
    const [selectedFiles, setSelectedFiles] = useState<FileList | null>(null);
    const [attachmentToDelete, setAttachmentToDelete] = useState<any>(null);
    const [isDeleteAttachmentModalOpen, setIsDeleteAttachmentModalOpen] = useState(false);

    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm({
        title: todo?.title || '',
        description: todo?.description || '',
        priority: todo?.priority || 'medium',
        status: todo?.status || 'pending',
        due_date: todo?.due_date ? new Date(todo.due_date).toISOString().split('T')[0] : '',
        members: todo?.members?.map((m: any) => m.id.toString()) || []
    });

    const { data: commentData, setData: setCommentData, post: postComment, reset: resetComment } = useForm({
        comment: ''
    });

    useEffect(() => {
        if (isOpen && todo?.id) {
            axios.get(route('todos.edit', todo.id))
                .then(response => {
                    setTodoData(response.data.todo);
                    const todoResponse = response.data.todo;
                    setData({
                        title: todoResponse.title || '',
                        description: todoResponse.description || '',
                        priority: todoResponse.priority || 'medium',
                        status: todoResponse.status || 'pending',
                        due_date: todoResponse.due_date ? new Date(todoResponse.due_date).toISOString().split('T')[0] : '',
                        members: todoResponse.members?.map((m: any) => m.id.toString()) || []
                    });
                })
                .catch(console.error);
        } else if (isOpen && !todo) {
            setTodoData(null);
            setData({
                title: '',
                description: '',
                priority: 'medium',
                status: 'pending',
                due_date: '',
                members: []
            });
        }
        setActiveTab('details');
    }, [isOpen, todo]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors();

        const newErrors: Record<string, string> = {};
        if (!data.title.trim()) newErrors.title = t('Title is required');
        if (!data.priority) newErrors.priority = t('Priority is required');
        if (!data.status) newErrors.status = t('Status is required');
        if (!data.due_date) newErrors.due_date = t('Due date is required');

        if (Object.keys(newErrors).length > 0) {
            Object.entries(newErrors).forEach(([key, value]) => setError(key as any, value));
            return;
        }

        if (todo) {
            put(route('todos.update', todo.id), {
                onSuccess: () => onClose()
            });
        } else {
            post(route('todos.store'), {
                onSuccess: () => onClose()
            });
        }
    };

    const handleComment = (e: React.FormEvent) => {
        e.preventDefault();
        postComment(route('todo-comments.store', todoData.id), {
            onSuccess: () => {
                resetComment();
                if (todoData?.id) {
                    axios.get(route('todos.edit', todoData.id))
                        .then(response => {
                            setTodoData(response.data.todo);
                        })
                        .catch(console.error);
                }
            }
        });
    };

    const handleFileUpload = (e: React.FormEvent) => {
        e.preventDefault();
        if (todoData?.id && selectedFiles) {
            const formData = new FormData();
            Array.from(selectedFiles).forEach((file) => {
                formData.append('files[]', file);
            });

            router.post(route('todo-attachments.store', todoData.id), formData, {
                onSuccess: () => {
                    setSelectedFiles(null);
                    const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
                    if (fileInput) fileInput.value = '';
                    axios.get(route('todos.edit', todoData.id))
                        .then(response => setTodoData(response.data.todo))
                        .catch(console.error);
                },
                onError: (errors) => {
                    console.error('Upload error:', errors);
                }
            });
        }
    };

    const handleDeleteAttachment = (attachmentId: number) => {
        if (todoData?.id) {
            router.delete(route('todo-attachments.destroy', attachmentId), {
                onSuccess: () => {
                    axios.get(route('todos.edit', todoData.id))
                        .then(response => setTodoData(response.data.todo))
                        .catch(console.error);
                }
            });
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {todo ? t('Edit ToDo') : t('Create ToDo')}
                    </DialogTitle>
                </DialogHeader>

                <Tabs value={activeTab} onValueChange={setActiveTab} className="h-[550px]">
                    <TabsList className="flex h-auto w-full flex-wrap gap-1">
                        <TabsTrigger value="details">{t('Details')}</TabsTrigger>
                        <TabsTrigger value="comments" disabled={!todoData}>
                            {t('Comments')} {todoData?.comments?.length ? `(${todoData.comments.length})` : ''}
                        </TabsTrigger>
                        <TabsTrigger value="attachments" disabled={!todoData}>
                            {t('Attachments')} {todoData?.attachments?.length ? `(${todoData.attachments.length})` : ''}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="details" className="space-y-4 overflow-y-auto" style={{ maxHeight: '520px' }}>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <Label htmlFor="title" required>{t('Title')}</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder={t('Enter todo title')}
                                    className={errors.title ? 'border-red-500' : ''}
                                />
                                {errors.title && <p className="text-sm text-red-600 mt-1">{errors.title}</p>}
                            </div>

                            <div>
                                <Label htmlFor="description">{t('Description')}</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder={t('Enter todo description')}
                                    rows={3}
                                />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="priority" required>{t('Priority')}</Label>
                                    <Select value={data.priority} onValueChange={(value) => setData('priority', value)}>
                                        <SelectTrigger className={`bg-white ${errors.priority ? 'border-red-500' : ''}`}>
                                            <SelectValue placeholder={t('Select priority')} />
                                        </SelectTrigger>
                                        <SelectContent className="bg-white border shadow-lg z-[9999]">
                                            <SelectItem value="low" className="bg-white hover:bg-gray-100">{t('Low')}</SelectItem>
                                            <SelectItem value="medium" className="bg-white hover:bg-gray-100">{t('Medium')}</SelectItem>
                                            <SelectItem value="high" className="bg-white hover:bg-gray-100">{t('High')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.priority && <p className="text-sm text-red-600 mt-1">{errors.priority}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="status" required>{t('Status')}</Label>
                                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                        <SelectTrigger className={`bg-white ${errors.status ? 'border-red-500' : ''}`}>
                                            <SelectValue placeholder={t('Select status')} />
                                        </SelectTrigger>
                                        <SelectContent className="bg-white border shadow-lg z-[9999]">
                                            <SelectItem value="pending" className="bg-white hover:bg-gray-100">{t('Pending')}</SelectItem>
                                            <SelectItem value="in_progress" className="bg-white hover:bg-gray-100">{t('In Progress')}</SelectItem>
                                            <SelectItem value="completed" className="bg-white hover:bg-gray-100">{t('Completed')}</SelectItem>
                                            <SelectItem value="overdue" className="bg-white hover:bg-gray-100">{t('Overdue')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.status && <p className="text-sm text-red-600 mt-1">{errors.status}</p>}
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="due_date" required>{t('Due Date')}</Label>
                                <Input
                                    id="due_date"
                                    type="date"
                                    value={data.due_date}
                                    min={todayLocalDateString()}
                                    placeholder={t('Select due date')}
                                    onChange={(e) => setData('due_date', e.target.value)}
                                    className={errors.due_date ? 'border-red-500' : ''}
                                />
                                {errors.due_date && <p className="text-sm text-red-600 mt-1">{errors.due_date}</p>}
                            </div>

                            <div>
                                <Label htmlFor="members">{t('Share with Members')}</Label>
                                <Select
                                    value=""
                                    onValueChange={(value) => {
                                        if (value && !data.members.includes(value)) {
                                            setData('members', [...data.members, value]);
                                        }
                                    }}
                                >
                                    <SelectTrigger className="bg-white">
                                        <SelectValue placeholder={t('Select members...')} />
                                    </SelectTrigger>
                                    <SelectContent searchable className="bg-white border shadow-lg z-[9999]">
                                        {workspaceMembers.map((member) => (
                                            <SelectItem
                                                key={member.id}
                                                value={member.id.toString()}
                                                className="bg-white hover:bg-gray-100"
                                                disabled={data.members.includes(member.id.toString())}
                                            >
                                                {member.name} ({member.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {data.members.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {data.members.map((memberId) => {
                                            const member = workspaceMembers.find(m => m.id.toString() === memberId);
                                            return member ? (
                                                <Badge key={memberId} variant="secondary" className="flex items-center space-x-1 bg-gray-300 text-gray-800">
                                                    <span>{member.name}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setData('members', data.members.filter(id => id !== memberId));
                                                        }}
                                                        className="ml-1 cursor-pointer hover:text-red-600"
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </button>
                                                </Badge>
                                            ) : null;
                                        })}
                                    </div>
                                )}
                            </div>

                            <div className="flex justify-end gap-2 pt-4">
                                <Button type="button" variant="outline" onClick={onClose}>
                                    {t('Cancel')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {todo ? t('Update') : t('Create')}
                                </Button>
                            </div>
                        </form>
                    </TabsContent>

                    <TabsContent value="comments" className="flex flex-col h-[520px]">
                        {todoData ? (
                            <>
                                <div className="space-y-3 flex-1 overflow-y-auto mb-4">
                                    {todoData.comments?.length > 0 ? (
                                        [...todoData.comments].reverse().map((comment: any) => (
                                            <div key={comment.id} className="group flex gap-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                                <Avatar className="h-8 w-8 flex-shrink-0">
                                                    <AvatarImage src={comment.user.avatar} />
                                                    <AvatarFallback className="bg-blue-100 text-blue-600 font-semibold text-sm">
                                                        {comment.user.name.charAt(0).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center justify-between mb-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium text-sm text-gray-900">{comment.user.name}</span>
                                                            <span className="text-xs text-gray-500">
                                                                {new Date(comment.created_at).toLocaleDateString()}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            {comment.can_update && (
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            onClick={() => {
                                                                                setEditingComment(comment.id);
                                                                                setEditCommentText(comment.comment);
                                                                            }}
                                                                            className="h-8 w-8 text-amber-500 hover:text-amber-700"
                                                                        >
                                                                            <Edit className="h-4 w-4" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>Edit</TooltipContent>
                                                                </Tooltip>
                                                            )}
                                                            {comment.can_delete && (
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            onClick={() => {
                                                                                router.delete(route('todo-comments.destroy', comment.id), {
                                                                                    onSuccess: () => {
                                                                                        axios.get(route('todos.edit', todoData.id))
                                                                                            .then(response => setTodoData(response.data.todo))
                                                                                            .catch(console.error);
                                                                                    }
                                                                                });
                                                                            }}
                                                                            className="h-8 w-8 text-red-500 hover:text-red-700"
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>Delete</TooltipContent>
                                                                </Tooltip>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {editingComment === comment.id ? (
                                                        <div className="space-y-2 mt-2">
                                                            <Textarea
                                                                value={editCommentText}
                                                                onChange={(e) => setEditCommentText(e.target.value)}
                                                                rows={2}
                                                                className="text-sm"
                                                            />
                                                            <div className="flex gap-2">
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() => {
                                                                        router.put(route('todo-comments.update', comment.id), {
                                                                            comment: editCommentText
                                                                        }, {
                                                                            onSuccess: () => {
                                                                                setEditingComment(null);
                                                                                axios.get(route('todos.edit', todoData.id))
                                                                                    .then(response => setTodoData(response.data.todo))
                                                                                    .catch(console.error);
                                                                            }
                                                                        });
                                                                    }}
                                                                    className="h-7 px-3 text-xs"
                                                                >
                                                                    {t('Save')}
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() => setEditingComment(null)}
                                                                    className="h-7 px-3 text-xs"
                                                                >
                                                                    {t('Cancel')}
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-gray-700 mt-1 whitespace-pre-wrap">{comment.comment}</p>
                                                    )}
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <MessageSquare className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                            <p>{t('No comments yet')}</p>
                                        </div>
                                    )}
                                </div>

                                {hasPermission(permissions, 'todo_comment_create') && (
                                    <form onSubmit={handleComment} className="flex items-end gap-2">
                                        <Textarea
                                            value={commentData.comment}
                                            onChange={(e) => setCommentData('comment', e.target.value)}
                                            placeholder={t('Add a comment...')}
                                            rows={2}
                                            className="flex-1"
                                        />
                                        <Button type="submit" disabled={!commentData.comment.trim()} className="self-end">
                                            <Send className="h-4 w-4" />
                                        </Button>
                                    </form>
                                )}
                            </>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                <MessageSquare className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                <p>{t('Save the todo first to add comments')}</p>
                            </div>
                        )}
                    </TabsContent>

                    <TabsContent value="attachments" className="flex flex-col h-[520px]">
                        {todoData ? (
                            <>
                                <div className="space-y-3 flex-1 overflow-y-auto mb-4">
                                    {todoData.attachments?.length > 0 ? (
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                                            {[...todoData.attachments].reverse().map((attachment: any) => {
                                                const name = attachment.file || '';
                                                const ext = name.split('.').pop()?.toLowerCase() || '';
                                                const isImage = ['jpg','jpeg','png','gif','webp','svg','bmp','ico'].includes(ext);
                                                const fileUrl = attachment.file_url || `/storage/media/${name}`;

                                                const getFileConfig = () => {
                                                    if (isImage) return { icon: Image, bg: 'bg-blue-50 dark:bg-blue-900/20', color: 'text-blue-400' };
                                                    if (ext === 'pdf') return { icon: FileText, bg: 'bg-red-50 dark:bg-red-900/20', color: 'text-red-500' };
                                                    if (['doc','docx'].includes(ext)) return { icon: FileText, bg: 'bg-blue-50 dark:bg-blue-900/20', color: 'text-blue-500' };
                                                    if (['xls','xlsx','csv'].includes(ext)) return { icon: FileSpreadsheet, bg: 'bg-green-50 dark:bg-green-900/20', color: 'text-green-500' };
                                                    if (['zip','rar','7z','tar','gz'].includes(ext)) return { icon: FileArchive, bg: 'bg-yellow-50 dark:bg-yellow-900/20', color: 'text-yellow-500' };
                                                    if (['js','ts','html','css','php','py','json'].includes(ext)) return { icon: FileCode, bg: 'bg-purple-50 dark:bg-purple-900/20', color: 'text-purple-500' };
                                                    return { icon: File, bg: 'bg-gray-100 dark:bg-gray-800', color: 'text-gray-400 dark:text-gray-500' };
                                                };
                                                const { icon: FileIcon, bg, color } = getFileConfig();

                                                return (
                                                <div key={attachment.id} className="group border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-200 relative">
                                                    {isImage ? (
                                                        <div className="relative w-full h-32">
                                                            <img
                                                                src={fileUrl}
                                                                alt={name}
                                                                className="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                                                                onError={(e) => { e.currentTarget.style.display = 'none'; }}
                                                            />
                                                            <div
                                                                className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer flex items-center justify-center"
                                                                onClick={() => window.open(fileUrl, '_blank')}
                                                            >
                                                                <div className="bg-white/25 border border-white/40 rounded-full p-1.5">
                                                                    <Eye className="h-3.5 w-3.5 text-white" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className={`w-full h-32 ${bg} flex flex-col items-center justify-center gap-1`}>
                                                            <FileIcon className={`h-10 w-10 ${color}`} />
                                                            <span className="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase">{ext}</span>
                                                        </div>
                                                    )}
                                                    <div className="p-2 bg-white dark:bg-gray-900">
                                                        <p className="text-xs font-medium truncate mb-1">{attachment.file}</p>
                                                        <p className="text-xs text-gray-500 dark:text-gray-400">{attachment.uploaded_by?.name}</p>
                                                    </div>

                                                    <div className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity" onClick={(e) => e.stopPropagation()}>
                                                        <button
                                                            onClick={() => setHoveredAttachment(hoveredAttachment === attachment.id ? null : attachment.id)}
                                                            className="p-1 bg-white/90 dark:bg-gray-800/90 rounded-full shadow hover:bg-white dark:hover:bg-gray-800"
                                                        >
                                                            <MoreVertical className="h-4 w-4 text-gray-600 dark:text-gray-300" />
                                                        </button>
                                                        {hoveredAttachment === attachment.id && (
                                                            <div className="absolute right-0 mt-1 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded shadow-lg py-1 z-10 min-w-[120px]">
                                                                <button
                                                                    onClick={() => window.open(route('todo-attachments.download', attachment.id), '_blank')}
                                                                    className="w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2"
                                                                >
                                                                    <Download className="h-4 w-4" />
                                                                    {t('Download')}
                                                                </button>
                                                                {hasPermission(permissions, 'todo_attachment_delete') && (
                                                                    <button
                                                                        onClick={() => {
                                                                            setAttachmentToDelete(attachment);
                                                                            setIsDeleteAttachmentModalOpen(true);
                                                                            setHoveredAttachment(null);
                                                                        }}
                                                                        className="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2"
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                        {t('Remove')}
                                                                    </button>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                                ); })}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <Paperclip className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                            <p>{t('No attachments yet')}</p>
                                        </div>
                                    )}
                                </div>
                                {hasPermission(permissions, 'todo_attachment_create') && (
                                    <form onSubmit={handleFileUpload} className="flex gap-2">
                                        <Input
                                            type="file"
                                            multiple
                                            onChange={(e) => setSelectedFiles(e.target.files)}
                                            className="flex-1"
                                        />
                                        <Button type="submit" size="sm" disabled={!selectedFiles}>
                                            <Paperclip className="h-4 w-4 mr-2" />
                                            {t('Upload Files')}
                                        </Button>
                                    </form>
                                )}
                            </>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                <Paperclip className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                <p>{t('Save the todo first to add attachments')}</p>
                            </div>
                        )}
                    </TabsContent>
                </Tabs>
            </DialogContent>

            <CrudDeleteModal
                isOpen={isDeleteAttachmentModalOpen}
                onClose={() => {
                    setIsDeleteAttachmentModalOpen(false);
                    setAttachmentToDelete(null);
                }}
                onConfirm={() => {
                    if (attachmentToDelete) {
                        router.delete(route('todo-attachments.destroy', attachmentToDelete.id), {
                            onSuccess: () => {
                                setIsDeleteAttachmentModalOpen(false);
                                setAttachmentToDelete(null);
                                axios.get(route('todos.edit', todoData.id))
                                    .then(response => setTodoData(response.data.todo))
                                    .catch(console.error);
                            },
                            onError: () => {
                                setIsDeleteAttachmentModalOpen(false);
                                setAttachmentToDelete(null);
                            }
                        });
                    }
                }}
                itemName={attachmentToDelete?.file || "attachment"}
                entityName="attachment"
            />
        </Dialog>
    );
}
