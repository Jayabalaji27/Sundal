import React from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Download, X } from 'lucide-react';
import { MediaItem } from '@/types';

interface Props {
    mediaItem: MediaItem | null;
    isOpen: boolean;
    onClose: () => void;
}

export default function FilePreview({ mediaItem, isOpen, onClose }: Props) {
    if (!mediaItem) return null;

    const handleDownload = () => {
        window.open(route('api.media.download', mediaItem.id), '_blank');
    };

    const renderPreview = () => {
        if (mediaItem.mime_type?.startsWith('image/')) {
            return (
                <img 
                    src={mediaItem.url} 
                    alt={mediaItem.name}
                    className="max-w-full max-h-[70vh] object-contain mx-auto"
                />
            );
        }

        if (mediaItem.mime_type === 'application/pdf') {
            return (
                <iframe
                    src={mediaItem.url}
                    className="w-full h-[70vh] border-0"
                    title={mediaItem.name}
                />
            );
        }

        if (mediaItem.mime_type?.startsWith('video/')) {
            return (
                <video 
                    controls 
                    className="max-w-full max-h-[70vh] mx-auto"
                >
                    <source src={mediaItem.url} type={mediaItem.mime_type} />
                    Your browser does not support the video tag.
                </video>
            );
        }

        if (mediaItem.mime_type?.startsWith('audio/')) {
            return (
                <div className="flex flex-col items-center space-y-4 py-8">
                    <div className="text-6xl">🎵</div>
                    <audio controls className="w-full max-w-md">
                        <source src={mediaItem.url} type={mediaItem.mime_type} />
                        Your browser does not support the audio tag.
                    </audio>
                </div>
            );
        }

        return (
            <div className="text-center py-12">
                <div className="text-6xl mb-4">📄</div>
                <p className="text-gray-600 mb-4">Preview not available for this file type</p>
                <Button onClick={handleDownload}>
                    <Download className="h-4 w-4 mr-2" />
                    Download to view
                </Button>
            </div>
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh]">
                <DialogHeader>
                    <div className="flex items-center justify-between gap-2">
                        <DialogTitle className="min-w-0 flex-1 truncate">{mediaItem.name}</DialogTitle>
                        <div className="flex shrink-0 items-center space-x-2">
                            <Button variant="outline" size="sm" onClick={handleDownload}>
                                <Download className="h-4 w-4 mr-2" />
                                Download
                            </Button>
                            <Button variant="ghost" size="sm" onClick={onClose}>
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </DialogHeader>
                
                <div className="overflow-auto">
                    {renderPreview()}
                </div>
                
                <div className="text-sm text-gray-500 text-center">
                    {mediaItem.size && `Size: ${formatFileSize(mediaItem.size)} • `}
                    Type: {mediaItem.mime_type}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}