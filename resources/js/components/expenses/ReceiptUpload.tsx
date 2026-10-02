import React, { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Upload, File, X, Download, Eye } from 'lucide-react';

interface ReceiptUploadProps {
    expenseId: number;
    existingAttachments?: Array<{
        id: number;
        media_item: {
            id: number;
            name: string;
            mime_type: string;
            size: number;
        };
        attachment_type: string;
    }>;
    onUploadComplete?: (attachments: any[]) => void;
}

export default function ReceiptUpload({ expenseId, existingAttachments = [], onUploadComplete }: ReceiptUploadProps) {
    const [attachments, setAttachments] = useState(existingAttachments);
    const [uploading, setUploading] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleFileSelect = (files: FileList | null) => {
        if (!files) return;
        uploadFiles(Array.from(files));
    };

    const uploadFiles = async (files: File[]) => {
        setUploading(true);
        
        const formData = new FormData();
        files.forEach(file => formData.append('files[]', file));
        formData.append('attachment_type', 'receipt');

        try {
            const response = await fetch(route('expense-receipts.upload', expenseId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: formData
            });

            const data = await response.json();
            
            if (response.ok) {
                const newAttachments = [...attachments, ...data.attachments];
                setAttachments(newAttachments);
                onUploadComplete?.(newAttachments);
            }
        } catch (error) {
            console.error('Upload failed:', error);
        } finally {
            setUploading(false);
        }
    };

    const deleteAttachment = async (attachmentId: number) => {
        try {
            const response = await fetch(route('expense-receipts.destroy', attachmentId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });

            if (response.ok) {
                const updatedAttachments = attachments.filter(att => att.id !== attachmentId);
                setAttachments(updatedAttachments);
                onUploadComplete?.(updatedAttachments);
            }
        } catch (error) {
            console.error('Delete failed:', error);
        }
    };

    const downloadAttachment = (attachmentId: number) => {
        window.open(route('expense-receipts.download', attachmentId), '_blank');
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getFileIcon = (mimeType: string) => {
        if (mimeType.startsWith('image/')) return '🖼️';
        if (mimeType === 'application/pdf') return '📄';
        return '📎';
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        handleFileSelect(e.dataTransfer.files);
    };

    return (
        <div className="space-y-4">
            {/* Upload Area */}
            <Card 
                className={`border-2 border-dashed transition-colors ${
                    dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300'
                }`}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
            >
                <CardContent className="p-6">
                    <div className="text-center">
                        <Upload className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                        <p className="text-lg font-medium mb-2">Upload Receipts</p>
                        <p className="text-gray-600 mb-4">
                            Drag and drop files here, or click to select
                        </p>
                        <Button 
                            onClick={() => fileInputRef.current?.click()}
                            disabled={uploading}
                        >
                            {uploading ? 'Uploading...' : 'Select Files'}
                        </Button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.pdf"
                            onChange={(e) => handleFileSelect(e.target.files)}
                            className="hidden"
                        />
                        <p className="text-xs text-gray-500 mt-2">
                            Supported formats: JPG, PNG, PDF (max 5MB each)
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Existing Attachments */}
            {attachments.length > 0 && (
                <div>
                    <h4 className="font-medium mb-3">Attachments ({attachments.length})</h4>
                    <div className="space-y-2">
                        {attachments.map((attachment) => (
                            <div key={attachment.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <span className="text-2xl">
                                        {getFileIcon(attachment.media_item.mime_type)}
                                    </span>
                                    <div>
                                        <div className="font-medium">{attachment.media_item.name}</div>
                                        <div className="text-sm text-gray-600">
                                            {formatFileSize(attachment.media_item.size)} • 
                                            <Badge variant="secondary" className="ml-1">
                                                {attachment.attachment_type}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => downloadAttachment(attachment.id)}
                                        className="text-blue-600 hover:text-blue-700"
                                    >
                                        <Download className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => deleteAttachment(attachment.id)}
                                        className="text-red-600 hover:text-red-700"
                                    >
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}