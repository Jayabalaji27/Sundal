import React, { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { Dialog, DialogContent, DialogTitle } from './ui/dialog';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Badge } from './ui/badge';
import { toast } from 'sonner';
import { Upload, X, Image as ImageIcon, Search, Plus, Check } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { hasPermission } from '@/utils/authorization';
import { useTranslation } from 'react-i18next';

interface MediaItem {
  id: number;
  name: string;
  file_name: string;
  url: string;
  thumb_url: string;
  size: number;
  mime_type: string;
  created_at: string;
}

interface MediaLibraryModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSelect: (url: string, mediaIds?: number[]) => void;
  multiple?: boolean;
}

export default function MediaLibraryModal({
  isOpen,
  onClose,
  onSelect,
  multiple = false
}: MediaLibraryModalProps) {
  const { t } = useTranslation();
  const { auth,storageSettings } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const canCreateMedia = hasPermission(permissions, 'media_upload');
  const canManageMedia = hasPermission(permissions, 'manage-media');

  const allowedTypes = storageSettings?.allowed_file_types || 'jpg,png,webp,gif';
    const acceptAttribute = allowedTypes
        .split(',')
        .map((type) => `.${type.trim()}`)
        .join(',');

  const [media, setMedia] = useState<MediaItem[]>([]);
  const [filteredMedia, setFilteredMedia] = useState<MediaItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [selectedItems, setSelectedItems] = useState<string[]>([]);
  const [dragActive, setDragActive] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 18;

  const fetchMedia = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch(route('api.media.index'), {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      setMedia(data);
      setFilteredMedia(data);
    } catch (error) {
      toast.error(t('Failed to load media'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isOpen) {
      fetchMedia();
      setSearchTerm('');
    }
  }, [isOpen, fetchMedia]);

  // Filter media based on search term
  useEffect(() => {
    if (!searchTerm.trim()) {
      setFilteredMedia(media);
    } else {
      const filtered = media.filter(item =>
        item.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.file_name.toLowerCase().includes(searchTerm.toLowerCase())
      );
      setFilteredMedia(filtered);
    }
    setCurrentPage(1);
  }, [searchTerm, media]);

  // Pagination calculations
  const totalPages = Math.ceil(filteredMedia.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const currentMedia = filteredMedia.slice(startIndex, startIndex + itemsPerPage);

  const handleFileUpload = async (files: FileList) => {
    setUploading(true);

    const validFiles = Array.from(files).filter(file => {
      // if (!file.type.startsWith('image/')) {
      //   toast.error(t('{{fileName}} is not an image file', { fileName: file.name }));
      //   return false;
      // }
      return true;
    });

    if (validFiles.length === 0) {
      setUploading(false);
      return;
    }

    const formData = new FormData();
    validFiles.forEach(file => {
      formData.append('files[]', file);
    });

    try {
      const response = await fetch(route('api.media.batch'), {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const result = await response.json();

      if (response.ok) {
        if (result.data && result.data.length > 0) {
          setMedia(prev => [...result.data, ...prev]);
        }

        // Show appropriate success/warning messages
        if (result.errors && result.errors.length > 0) {
          toast.warning(result.message || `${result.data?.length || 0} uploaded, ${result.errors.length} failed`);
          result.errors.forEach((error: string) => {
            toast.error(error, { duration: 5000 });
          });
        } else {
          toast.success(result.message || `${result.data?.length || 0} file(s) uploaded successfully`);
        }
      } else {
        toast.error(result.message || 'Failed to upload files');
        if (result.errors) {
          result.errors.forEach((error: string) => {
            toast.error(error, { duration: 5000 });
          });
        }
      }
    } catch (error) {
      toast.error('Error uploading files');
    }

    setUploading(false);
  };

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);

    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileUpload(e.dataTransfer.files);
    }
  };

  const handleSelect = (url: string) => {
    if (multiple) {
      setSelectedItems(prev =>
        prev.includes(url)
          ? prev.filter(item => item !== url)
          : [...prev, url]
      );
    } else {
      const mediaItem = media.find(m => m.url === url);
      // Single selection with media ID
      onSelect(url, mediaItem ? [mediaItem.id] : []);
      onClose();
    }
  };

  const handleConfirmSelection = () => {
    if (multiple && selectedItems.length > 0) {
      // Get media IDs for selected items
      const mediaIds = selectedItems.map(url => {
        const mediaItem = media.find(m => m.url === url);
        return mediaItem ? mediaItem.id : null;
      }).filter(Boolean) as number[];

      // Multiple selection with media IDs
      onSelect(selectedItems.join(','), mediaIds);
      onClose();
    }
  };

    const getFileIcon = (mimeType: string) => {
    if (mimeType.startsWith('image/')) return <ImageIcon className="h-8 w-8" />;
    if (mimeType.includes('pdf')) return <div className="h-8 w-8 bg-red-500 rounded text-white text-sm flex items-center justify-center font-bold">PDF</div>;
    if (mimeType.includes('word') || mimeType.includes('document')) return <div className="h-8 w-8 bg-blue-500 rounded text-white text-sm flex items-center justify-center font-bold">DOC</div>;
    if (mimeType.includes('csv') || mimeType.includes('spreadsheet')) return <div className="h-8 w-8 bg-green-500 rounded text-white text-sm flex items-center justify-center font-bold">CSV</div>;
    if (mimeType.startsWith('video/')) return <div className="h-8 w-8 bg-purple-500 rounded text-white text-sm flex items-center justify-center font-bold">VID</div>;
    if (mimeType.startsWith('audio/')) return <div className="h-8 w-8 bg-orange-500 rounded text-white text-sm flex items-center justify-center font-bold">AUD</div>;
    return <div className="h-8 w-8 bg-gray-500 rounded text-white text-sm flex items-center justify-center font-bold">FILE</div>;
  };


  const modalContent = (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent
        className="w-[95vw] max-w-5xl p-0 gap-0 overflow-hidden flex flex-col"
        style={{ zIndex: 10000, height: 'min(85vh, 700px)' }}
      >
        {/* Fixed Header */}
        <div className="flex-shrink-0 flex items-center justify-between px-5 py-4 border-b">
          <DialogTitle className="flex items-center gap-2 text-base font-semibold">
            <ImageIcon className="h-5 w-5" />
            {t('Media Library')}
            {filteredMedia.length > 0 && (
              <Badge variant="secondary" className="ml-1">
                {filteredMedia.length}
              </Badge>
            )}
          </DialogTitle>
        </div>

        {/* Fixed Search + Upload bar */}
        <div className="flex-shrink-0 flex flex-col sm:flex-row gap-2 px-5 py-3 border-b bg-background">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground h-4 w-4" />
            <Input
              placeholder={t('Search media files...')}
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-10 h-9"
            />
          </div>
          {canCreateMedia && (
            <>
              <Input
                type="file"
                multiple
                accept={acceptAttribute}
                onChange={(e) => e.target.files && handleFileUpload(e.target.files)}
                className="hidden"
                id="file-upload"
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-9 shrink-0"
                onClick={() => document.getElementById('file-upload')?.click()}
                disabled={uploading}
              >
                <Plus className="h-4 w-4 mr-1.5" />
                {uploading ? t('Uploading...') : t('Upload')}
              </Button>
            </>
          )}
        </div>

        {/* Stats bar */}
        <div className="flex-shrink-0 flex flex-wrap items-center justify-between gap-2 px-5 py-2 text-xs text-muted-foreground bg-muted/30 border-b">
          <span>{filteredMedia.length} {t('files')} &bull; {t('Page')} {currentPage} {t('of')} {totalPages || 1}</span>
          {multiple && selectedItems.length > 0 && (
            <Badge variant="default" className="text-xs">
              {selectedItems.length} {t('selected')}
            </Badge>
          )}
        </div>

        {/* Scrollable Media Grid */}
        <div className="flex-1 overflow-y-auto min-h-0">
          {loading ? (
            <div className="flex items-center justify-center h-full py-16">
              <div className="text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-3" />
                <p className="text-sm text-muted-foreground">{t('Loading media...')}</p>
              </div>
            </div>
          ) : filteredMedia.length === 0 ? (
            <div className="flex items-center justify-center h-full py-16">
              <div className="text-center max-w-xs px-4">
                <div
                  className={`mx-auto w-20 h-20 border-2 border-dashed rounded-xl flex items-center justify-center mb-5 transition-colors ${
                    dragActive ? 'border-primary bg-primary/5' : 'border-muted-foreground/25'
                  }`}
                  onDragEnter={handleDrag}
                  onDragLeave={handleDrag}
                  onDragOver={handleDrag}
                  onDrop={handleDrop}
                >
                  <Upload className="h-8 w-8 text-muted-foreground" />
                </div>
                <h3 className="text-base font-semibold mb-2">{t('No media files found')}</h3>
                {searchTerm && (
                  <p className="text-sm text-muted-foreground mb-1">
                    {t('No results for')} <span className="font-medium text-foreground">"{searchTerm}"</span>
                  </p>
                )}
                <p className="text-sm text-muted-foreground mb-4">
                  {searchTerm ? t('Try a different search term or upload new images') : t('Upload images to get started')}
                </p>
                {canCreateMedia && (
                  <Button
                    type="button"
                    size="sm"
                    onClick={() => document.getElementById('file-upload')?.click()}
                    disabled={uploading}
                  >
                    <Plus className="h-4 w-4 mr-1.5" />
                    {t('Upload Images')}
                  </Button>
                )}
              </div>
            </div>
          ) : (
            <div className="p-4">
              <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                {currentMedia.map((item) => (
                  <div
                    key={item.id}
                    className={`relative group cursor-pointer rounded-lg overflow-hidden transition-all hover:scale-105 ${
                      selectedItems.includes(item.url)
                        ? 'ring-2 ring-primary shadow-lg'
                        : 'hover:shadow-md border border-border hover:border-primary/50'
                    }`}
                    onClick={() => handleSelect(item.url)}
                  >
                    <div className="relative aspect-square bg-muted">
                      {/* <img
                        src={item.thumb_url}
                        alt={item.name}
                        className="w-full h-full object-cover"
                        onError={(e) => { e.currentTarget.src = item.url; }}
                      /> */}
                      {item.mime_type.startsWith('image/') ? (
                          <img
                            src={item.thumb_url}
                            alt={item.name}
                            className="w-full h-full object-cover"
                            onError={(e) => {
                              e.currentTarget.src = item.url;
                            }}
                          />
                        ) : (
                          <div className="w-full h-full flex flex-col items-center justify-center p-4">
                            <div className="mb-2">
                              {getFileIcon(item.mime_type)}
                            </div>
                            <div className="text-xs text-center font-medium text-muted-foreground truncate w-full">
                              {item.mime_type.split('/')[1]?.toUpperCase() || 'FILE'}
                            </div>
                          </div>
                        )}
                      {selectedItems.includes(item.url) && (
                        <div className="absolute inset-0 bg-primary/30 flex items-center justify-center">
                          <div className="bg-primary text-primary-foreground rounded-full p-1.5">
                            <Check className="h-4 w-4" />
                          </div>
                        </div>
                      )}
                      <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors" />
                      <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <p className="text-xs text-white truncate" title={item.name}>{item.name}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Fixed Pagination */}
        {totalPages > 1 && (
          <div className="flex-shrink-0 flex flex-wrap items-center justify-between gap-2 px-5 py-3 border-t bg-background">
            <span className="text-xs text-muted-foreground">
              {t('Showing')} {startIndex + 1}–{Math.min(startIndex + itemsPerPage, filteredMedia.length)} {t('of')} {filteredMedia.length}
            </span>
            <div className="flex flex-wrap gap-1">
              <Button variant="outline" size="sm" disabled={currentPage === 1}
                onClick={() => setCurrentPage(p => Math.max(p - 1, 1))}>
                {t('Previous')}
              </Button>
              {Array.from({ length: Math.min(totalPages, 5) }, (_, i) => {
                let page = i + 1;
                if (totalPages > 5) {
                  if (currentPage <= 3) page = i + 1;
                  else if (currentPage >= totalPages - 2) page = totalPages - 4 + i;
                  else page = currentPage - 2 + i;
                }
                return (
                  <Button key={page} variant={currentPage === page ? 'default' : 'outline'}
                    size="sm" className="w-8 h-8 p-0"
                    onClick={() => setCurrentPage(page)}>
                    {page}
                  </Button>
                );
              })}
              <Button variant="outline" size="sm" disabled={currentPage === totalPages}
                onClick={() => setCurrentPage(p => Math.min(p + 1, totalPages))}>
                {t('Next')}
              </Button>
            </div>
          </div>
        )}

        {/* Fixed Actions Footer — always visible */}
        <div className="flex-shrink-0 flex flex-wrap items-center justify-between gap-2 px-5 py-3 border-t bg-background">
          <Button variant="outline" size="sm" onClick={onClose}>
            {t('Cancel')}
          </Button>
          <div className="flex flex-wrap gap-2">
            {multiple && selectedItems.length > 0 && (
              <Button variant="outline" size="sm" onClick={() => setSelectedItems([])}>
                {t('Clear')}
              </Button>
            )}
            {multiple && selectedItems.length > 0 && (
              <Button size="sm" onClick={handleConfirmSelection}>
                {t('Select')} {selectedItems.length} {selectedItems.length > 1 ? t('items') : t('item')}
              </Button>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );

  return isOpen ? createPortal(modalContent, document.body) : null;
}
