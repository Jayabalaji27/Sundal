import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { CheckSquare, Calendar, User, Flag, Clock, Users, MessageSquare, Paperclip, Eye, Download, File, FileText, FileSpreadsheet, FileArchive, FileCode, Image } from 'lucide-react';

interface TodoViewProps {
    todo: any;
    todoData: any;
    activeTab: string;
    onTabChange: (tab: string) => void;
}

export default function TodoView({ todo, todoData, activeTab, onTabChange }: TodoViewProps) {
    const { t } = useTranslation();

    const getPriorityColor = (priority: string) => {
        const colors: Record<string, string> = {
            high: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            medium: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            low: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
        };
        return colors[priority] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getStatusColor = (status: string) => {
        const colors: Record<string, string> = {
            pending: 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
            in_progress: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            completed: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            overdue: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
        };
        return colors[status] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const capitalizeFirst = (str: string) => str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ');

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-hidden flex flex-col p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader className="px-6 pt-6 pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <CheckSquare className="h-5 w-5 text-primary" />
                    </div>
                    <DialogTitle className="text-xl font-semibold">{todo.title}</DialogTitle>
                </div>
            </DialogHeader>

            <Tabs value={activeTab} onValueChange={onTabChange} className="flex-1 flex flex-col overflow-hidden">
                <div className="px-6 pt-3">
                    <TabsList className="flex h-auto w-full flex-wrap gap-1">
                        <TabsTrigger value="details">{t('Details')}</TabsTrigger>
                        <TabsTrigger value="comments">
                            {t('Comments')}{todoData?.comments?.length ? ` (${todoData.comments.length})` : ''}
                        </TabsTrigger>
                        <TabsTrigger value="attachments">
                            {t('Attachments')}{todoData?.attachments?.length ? ` (${todoData.attachments.length})` : ''}
                        </TabsTrigger>
                    </TabsList>
                </div>

                {/* Details Tab */}
                <TabsContent value="details" className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    {/* Title & Creator */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <User className="h-4 w-4" />
                                {t('Created By')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{todo.creator?.name || '-'}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                {t('Due Date')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                {todo.due_date ? window.appSettings.formatDateTime(new Date(todo.due_date),false) : '-'}
                            </p>
                        </div>
                    </div>

                    {/* Priority & Status */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Flag className="h-4 w-4" />
                                {t('Priority')}
                            </label>
                            <div className="mt-1">
                                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityColor(todo.priority)}`}>
                                    {capitalizeFirst(todo.priority)}
                                </span>
                            </div>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Clock className="h-4 w-4" />
                                {t('Status')}
                            </label>
                            <div className="mt-1">
                                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(todo.status)}`}>
                                    {capitalizeFirst(todo.status)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Members */}
                    {todo.members?.length > 0 && (
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Users className="h-4 w-4" />
                                {t('Shared with Members')}
                            </label>
                            <div className="flex flex-wrap gap-2 mt-2">
                                {todo.members.map((member: any) => (
                                    <span key={member.id} className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20">
                                        {member.name}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Description */}
                    {todo.description && (
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <CheckSquare className="h-4 w-4" />
                                {t('Description')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white whitespace-pre-wrap">{todo.description}</p>
                        </div>
                    )}
                </TabsContent>

                {/* Comments Tab */}
                <TabsContent value="comments" className="flex flex-col flex-1 overflow-hidden px-6 py-4">
                    {todoData ? (
                        <div className="space-y-3 flex-1 overflow-y-auto">
                            {todoData.comments?.length > 0 ? (
                                [...todoData.comments].reverse().map((comment: any) => (
                                    <div key={comment.id} className="flex gap-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                        <Avatar className="h-8 w-8 flex-shrink-0">
                                            <AvatarImage src={comment.user.avatar} />
                                            <AvatarFallback className="bg-blue-100 text-blue-600 font-semibold text-sm">
                                                {comment.user.name.charAt(0).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="font-medium text-sm text-gray-900">{comment.user.name}</span>
                                                <span className="text-xs text-gray-500">
                                                    {window.appSettings.formatDateTime(new Date(comment.created_at),false)}
                                                </span>
                                            </div>
                                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{comment.comment}</p>
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
                    ) : (
                        <div className="text-center py-8 text-gray-500">
                            <MessageSquare className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                            <p>{t('Loading comments...')}</p>
                        </div>
                    )}
                </TabsContent>

                {/* Attachments Tab */}
                <TabsContent value="attachments" className="flex flex-col flex-1 overflow-hidden px-6 py-4">
                    {todoData ? (
                        <div className="flex-1 overflow-y-auto">
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
                                        <div key={attachment.id} className="group border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-200">
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
                                                <div className={`relative w-full h-32 ${bg} flex flex-col items-center justify-center gap-1`}>
                                                    <FileIcon className={`h-10 w-10 ${color}`} />
                                                    <span className="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase">{ext}</span>
                                                    <div
                                                        className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer flex items-center justify-center"
                                                        onClick={() => window.open(fileUrl, '_blank')}
                                                    >
                                                        <div className="bg-white/25 border border-white/40 rounded-full p-1.5">
                                                            <Eye className="h-3.5 w-3.5 text-white" />
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                            <div className="p-2 bg-white dark:bg-gray-900">
                                                <p className="text-xs font-medium truncate mb-1">{name}</p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">{attachment.uploaded_by?.name}</p>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="w-full h-7 text-xs"
                                                    onClick={() => window.open(route('todo-attachments.download', attachment.id), '_blank')}
                                                >
                                                    <Download className="h-3 w-3 mr-1" />
                                                    {t('Download')}
                                                </Button>
                                            </div>
                                        </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-gray-500">
                                    <Paperclip className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                                    <p>{t('No attachments yet')}</p>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="text-center py-8 text-gray-500">
                            <Paperclip className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                            <p>{t('Loading attachments...')}</p>
                        </div>
                    )}
                </TabsContent>
            </Tabs>
        </DialogContent>
    );
}
