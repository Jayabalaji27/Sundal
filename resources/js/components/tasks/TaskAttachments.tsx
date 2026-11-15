import React, { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Paperclip, Download, MoreHorizontal, Trash2, File, Image, FileText, FileSpreadsheet, FileArchive, FileCode, Eye, UploadCloud, Loader2, Images } from 'lucide-react';
import { Task, TaskAttachment, MediaItem } from '@/types';
import MediaLibraryModal from '@/components/MediaLibraryModal';
import { toast } from '@/components/custom-toast';
import { cn } from '@/lib/utils';
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
    const { t } = useTranslation();
    const { storageSettings } = usePage().props as any;
    const allowedTypes: string = storageSettings?.allowed_file_types || 'jpg,jpeg,png,webp,gif,pdf';
    const maxFileSizeKb: number = storageSettings?.max_file_size_kb || 2048;
    const maxMb = Math.round((maxFileSizeKb / 1024) * 10) / 10;
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(0);
    const [dragging, setDragging] = useState(false);
    const [libraryOpen, setLibraryOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [attachmentToDelete, setAttachmentToDelete] = useState<TaskAttachment | null>(null);

    /** Links media items to the task, keeping the task window as it is. */
    const attach = (mediaIds: number[], done?: () => void) => {
        if (!mediaIds.length) { done?.(); return; }
        router.post(route('task-attachments.store', task.id), { media_item_ids: mediaIds }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onUpdate?.(),
            onError: () => toast.error(t('Could not attach the files. Please try again.')),
            onFinish: () => done?.(),
        });
    };

    /** Uploads files from the computer into the media library, then attaches them: one step for the user. */
    const uploadFiles = async (list: FileList | null) => {
        const files = Array.from(list || []);
        if (!files.length || uploading) return;
        files.filter(f => f.size > maxFileSizeKb * 1024)
            .forEach(f => toast.error(t('{{name}} is larger than {{size}} MB.', { name: f.name, size: maxMb })));
        const ok = files.filter(f => f.size <= maxFileSizeKb * 1024);
        if (!ok.length) return;

        setUploading(ok.length);
        const formData = new FormData();
        ok.forEach(f => formData.append('files[]', f));
        try {
            const response = await fetch(route('api.media.batch'), {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const result = await response.json().catch(() => ({}));
            (result.errors || []).forEach((e: string) => toast.error(e));
            if (!response.ok && !result.errors?.length) toast.error(result.message || t('Upload failed.'));
            const ids: number[] = (result.data || []).map((item: { id: number }) => item.id);
            if (ids.length) {
                attach(ids, () => setUploading(0));
                toast.success(t('{{count}} file(s) attached', { count: ids.length }));
            } else {
                setUploading(0);
            }
        } catch {
            toast.error(t('Upload failed. Check your connection and try again.'));
            setUploading(0);
        } finally {
            if (fileInput.current) fileInput.current.value = '';
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

                {/* Upload: drop files or click to pick them; they attach straight away. */}
                {canAddAttachments && (
                    <div className="shrink-0 border-t border-ds-border-subtle pt-4">
                        <input
                            ref={fileInput}
                            type="file"
                            multiple
                            className="hidden"
                            accept={allowedTypes.split(',').map(ext => '.' + ext.trim()).join(',')}
                            onChange={(e) => uploadFiles(e.target.files)}
                        />
                        <button
                            type="button"
                            disabled={!!uploading}
                            onClick={() => fileInput.current?.click()}
                            onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={(e) => { e.preventDefault(); setDragging(false); uploadFiles(e.dataTransfer.files); }}
                            className={cn(
                                'flex w-full cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed px-4 py-4 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus disabled:cursor-wait',
                                dragging ? 'border-primary bg-primary/5' : 'border-ds-border hover:border-primary/60 hover:bg-ds-surface-hover',
                            )}
                        >
                            {uploading
                                ? <Loader2 className="h-6 w-6 shrink-0 animate-spin text-primary" />
                                : <UploadCloud className={cn('h-6 w-6 shrink-0', dragging ? 'text-primary' : 'text-ds-fg-muted')} />}
                            <span className="min-w-0">
                                <span className="block text-sm text-ds-fg">
                                    {uploading
                                        ? t('Uploading {{count}} file(s)…', { count: uploading })
                                        : <>{t('Drag files here or')} <span className="font-semibold text-primary">{t('browse')}</span></>}
                                </span>
                                <span className="block text-xs text-ds-fg-muted">
                                    {allowedTypes.split(',').map(x => x.trim().toUpperCase()).join(', ')} · {t('up to {{size}} MB', { size: maxMb })}
                                </span>
                            </span>
                        </button>
                        <div className="mt-2 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setLibraryOpen(true)}
                                className="inline-flex cursor-pointer items-center gap-1.5 rounded text-xs font-medium text-ds-fg-secondary hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus"
                            >
                                <Images className="h-3.5 w-3.5" />
                                {t('Choose from media library')}
                            </button>
                        </div>
                        <MediaLibraryModal
                            isOpen={libraryOpen}
                            onClose={() => setLibraryOpen(false)}
                            onSelect={(_url, mediaIds) => { setLibraryOpen(false); attach(mediaIds || []); }}
                            multiple
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