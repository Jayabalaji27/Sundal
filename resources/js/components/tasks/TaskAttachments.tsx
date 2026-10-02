import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Paperclip, Download, MoreHorizontal, Trash2, File, Image, FileText, FileSpreadsheet, FileArchive, FileCode, Eye } from 'lucide-react';
import { Task, TaskAttachment, MediaItem } from '@/types';
import MediaPicker from '@/components/MediaPicker';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';

interface TaskAttachment {
    id: number;
    task_id: number;
    media_item_id: number;
    uploaded_by: number;
    created_at: string;
    updated_at: string;
    media_item?: {
        id: number;
        name: string;
        url: string;
        thumb_url: string;
        mime_type: string;
    };
}

interface Props {
    task: Task;
    attachments: TaskAttachment[];
    availableMedia?: MediaItem[];
    onUpdate?: () => void;
    canAddAttachments?: boolean;
    canManageAttachments?: boolean;
}

export default function TaskAttachments({ task, attachments, availableMedia = [], onUpdate, canAddAttachments = true, canManageAttachments = true }: Props) {
    const [selectedMedia, setSelectedMedia] = useState('');
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [attachmentToDelete, setAttachmentToDelete] = useState<TaskAttachment | null>(null);

    const handleMediaSelect = (url: string, mediaIds?: number[]) => {
        setSelectedMedia(url);
        
        if (mediaIds && mediaIds.length > 0) {
            router.post(route('task-attachments.store', task.id), {
                media_item_ids: mediaIds
            }, {
                onSuccess: () => {
                    onUpdate?.();
                    setSelectedMedia('');
                }
            });
        }
    };

    const handleDownload = (attachmentId: number) => {
        window.open(route('task-attachments.download', attachmentId), '_blank');
    };

    const handleDelete = (attachment: TaskAttachment) => {
        setAttachmentToDelete(attachment);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (attachmentToDelete) {
            router.delete(route('task-attachments.destroy', attachmentToDelete.id), {
                onSuccess: () => {
                    setIsDeleteModalOpen(false);
                    setAttachmentToDelete(null);
                    onUpdate?.();
                },
                onError: () => {
                    setIsDeleteModalOpen(false);
                    setAttachmentToDelete(null);
                }
            });
        }
    };

    const handlePreview = (mediaItem: MediaItem) => {
        if (mediaItem.mime_type?.startsWith('image/')) {
            window.open(mediaItem.url, '_blank');
        }
    };

    const getFileConfig = (mimeType: string, fileName: string) => {
        const ext = fileName.split('.').pop()?.toLowerCase() || '';
        if (mimeType.startsWith('image/')) return { icon: Image, bg: 'bg-blue-50 dark:bg-blue-900/20', color: 'text-blue-400' };
        if (mimeType.includes('pdf')) return { icon: FileText, bg: 'bg-red-50 dark:bg-red-900/20', color: 'text-red-500' };
        if (mimeType.includes('word') || ext === 'doc' || ext === 'docx') return { icon: FileText, bg: 'bg-blue-50 dark:bg-blue-900/20', color: 'text-blue-500' };
        if (mimeType.includes('sheet') || mimeType.includes('excel') || ext === 'xls' || ext === 'xlsx' || ext === 'csv') return { icon: FileSpreadsheet, bg: 'bg-green-50 dark:bg-green-900/20', color: 'text-green-500' };
        if (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('archive') || ext === 'zip' || ext === 'rar') return { icon: FileArchive, bg: 'bg-yellow-50 dark:bg-yellow-900/20', color: 'text-yellow-500' };
        if (mimeType.includes('javascript') || mimeType.includes('typescript') || mimeType.includes('html') || mimeType.includes('css')) return { icon: FileCode, bg: 'bg-purple-50 dark:bg-purple-900/20', color: 'text-purple-500' };
        return { icon: File, bg: 'bg-gray-50 dark:bg-gray-800', color: 'text-gray-400 dark:text-gray-500' };
    };

    return (
        <>
            <div className="flex flex-col h-full overflow-hidden pt-4">
                {/* Media Display Grid */}
                <div className="flex-1 overflow-y-auto pr-2 mb-4">
                    {attachments.length > 0 ? (
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            {attachments.map((attachment) => {
                                return (
                                    <div key={attachment.id} className="relative group border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden hover:shadow-md transition-all bg-white dark:bg-gray-900">
                                        {/* Media Preview */}
                                        <div className="aspect-square bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                                            {attachment.media_item?.mime_type?.startsWith('image/') && attachment.media_item?.thumb_url ? (
                                                <div className="relative w-full h-full">
                                                    <img
                                                        src={attachment.media_item.thumb_url}
                                                        alt={attachment.media_item?.name || 'Attachment'}
                                                        className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                        onError={(e) => {
                                                            if (attachment.media_item?.url) {
                                                                e.currentTarget.src = attachment.media_item.url;
                                                            }
                                                        }}
                                                    />
                                                    {/* Hover overlay with eye button */}
                                                    <div
                                                        className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer flex items-center justify-center"
                                                        onClick={() => window.open(attachment.media_item?.url || attachment.media_item?.thumb_url, '_blank')}
                                                    >
                                                        <div className="bg-white/25 border border-white/40 rounded-full p-1.5">
                                                            <Eye className="h-3.5 w-3.5 text-white" />
                                                        </div>
                                                    </div>
                                                    {canManageAttachments && (
                                                        <div className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity" onClick={(e) => e.stopPropagation()}>
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild>
                                                                    <Button variant="secondary" size="sm" className="h-8 w-8 p-0 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm shadow-sm border-gray-200 dark:border-gray-600">
                                                                        <MoreHorizontal className="h-4 w-4" />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end" className="z-[9999]">
                                                                    <DropdownMenuItem onClick={() => handleDownload(attachment.id)}>
                                                                        <Download className="h-4 w-4 mr-2" />
                                                                        Download
                                                                    </DropdownMenuItem>

                                                                    <DropdownMenuItem
                                                                        onClick={() => handleDelete(attachment)}
                                                                        className="text-red-600"
                                                                    >
                                                                        <Trash2 className="h-4 w-4 mr-2" />
                                                                        Remove
                                                                    </DropdownMenuItem>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </div>
                                                    )}
                                                </div>
                                            ) : (
                                                (() => {
                                                    const { icon: FileIcon, bg, color } = getFileConfig(attachment.media_item?.mime_type || '', attachment.media_item?.name || '');
                                                    const ext = (attachment.media_item?.name || '').split('.').pop()?.toUpperCase() || '';
                                                    return (
                                                        <div className={`relative w-full h-full ${bg} flex flex-col items-center justify-center gap-1`}>
                                                            <FileIcon className={`h-10 w-10 ${color}`} />
                                                            <span className="text-[10px] font-semibold text-gray-500 dark:text-gray-400">{ext}</span>
                                                            <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 cursor-pointer flex items-center justify-center"
                                                                onClick={() => window.open(attachment.media_item?.url, '_blank')}>
                                                                <div className="bg-white/25 border border-white/40 rounded-full p-1.5"><Eye className="h-3.5 w-3.5 text-white" /></div>
                                                            </div>
                                                            {canManageAttachments && (
                                                                <div className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity" onClick={(e) => e.stopPropagation()}>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="secondary" size="sm" className="h-8 w-8 p-0 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm shadow-sm border-gray-200 dark:border-gray-600">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="z-[9999]">
                                                        <DropdownMenuItem onClick={() => handleDownload(attachment.id)}>
                                                            <Download className="h-4 w-4 mr-2" />
                                                            Download
                                                        </DropdownMenuItem>

                                                        <DropdownMenuItem 
                                                            onClick={() => handleDelete(attachment)}
                                                            className="text-red-600"
                                                        >
                                                            <Trash2 className="h-4 w-4 mr-2" />
                                                            Remove
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                                            )}
                                                        </div>
                                                    );
                                                })()
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center h-full text-center py-10 text-gray-400 dark:text-gray-500">
                            <div className="bg-gray-50 dark:bg-gray-800 p-4 rounded-full mb-4">
                                <Paperclip className="h-8 w-8 text-gray-300 dark:text-gray-600" />
                            </div>
                            <p className="text-sm font-medium">No attachments yet</p>
                            <p className="text-xs">Upload files to share with your team!</p>
                        </div>
                    )}
                </div>

                {/* Media Picker with Portal for Modal */}
                {canAddAttachments && (
                    <div className="shrink-0 border-t dark:border-gray-700 pt-4 bg-white dark:bg-gray-900">
                        <MediaPicker
                            label="Add Media"
                            value={selectedMedia}
                            onChange={handleMediaSelect}
                            placeholder="Select media..."
                            showPreview={true}
                            multiple={true}
                        />
                    </div>
                )}
            </div>

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => {
                    setIsDeleteModalOpen(false);
                    setAttachmentToDelete(null);
                }}
                onConfirm={handleDeleteConfirm}
                itemName={attachmentToDelete?.media_item?.name || 'attachment'}
                entityName="attachment"
            />
        </>
    );
}