import React, { useState } from 'react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import MediaLibraryModal from './MediaLibraryModal';
import { Image as ImageIcon, X } from 'lucide-react';

interface MediaPickerProps {
  label?: string;
  value?: string;
  onChange: (value: string, mediaIds?: number[]) => void;
  multiple?: boolean;
  placeholder?: string;
  showPreview?: boolean;
}

export default function MediaPicker({
  label,
  value = '',
  onChange,
  multiple = false,
  placeholder = 'Select image...',
  showPreview = true
}: MediaPickerProps) {
  const [isModalOpen, setIsModalOpen] = useState(false);

  const handleSelect = (selectedUrl: string, mediaIds?: number[]) => {
    onChange(selectedUrl, mediaIds);
  };

  const handleClear = () => {
    onChange('');
  };

  const imageUrls = value ? value.split(',').filter(Boolean) : [];

  const getFileIcon = (url: string) => {
    if (!url) return null;
    const extension = url.split('.').pop()?.toLowerCase();

    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(extension || '')) {
      return null; // Return null for images to show actual image
    }

    if (extension === 'pdf') return <div className="h-16 w-16 bg-red-500 rounded text-white text-xs flex items-center justify-center font-bold">PDF</div>;
    if (['doc', 'docx'].includes(extension || '')) return <div className="h-16 w-16 bg-blue-500 rounded text-white text-xs flex items-center justify-center font-bold">DOC</div>;
    if (['xls', 'xlsx', 'csv'].includes(extension || '')) return <div className="h-16 w-16 bg-green-500 rounded text-white text-xs flex items-center justify-center font-bold">XLS</div>;
    if (['ppt', 'pptx'].includes(extension || '')) return <div className="h-16 w-16 bg-orange-500 rounded text-white text-xs flex items-center justify-center font-bold">PPT</div>;

    return <div className="h-16 w-16 bg-gray-500 rounded text-white text-xs flex items-center justify-center font-bold">FILE</div>;
  };

  return (
    <div className="space-y-2">
      {label && <Label>{label}</Label>}

      <div className="flex gap-2">
        <Input
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          readOnly={multiple}
        />
        <Button
          type="button"
          variant="outline"
          onClick={() => setIsModalOpen(true)}
        >
          <ImageIcon className="h-4 w-4 mr-2" />
          Browse
        </Button>
        {value && (
          <Button
            type="button"
            variant="outline"
            size="icon"
            onClick={handleClear}
          >
            <X className="h-4 w-4" />
          </Button>
        )}
      </div>

      {/* Preview */}
      {showPreview && imageUrls.length > 0 && (
        <div className="grid grid-cols-4 gap-2 mt-2">
          {imageUrls.map((url, index) => {
            // <div key={index} className="relative">
            //   <img
            //     src={url}
            //     alt={`Preview ${index + 1}`}
            //     className="w-full h-20 object-cover rounded border"
            //   />
            // </div>
            const fileIcon = getFileIcon(url);
            return (
              <div key={index} className="relative">
                {fileIcon ? (
                  <div className="w-full h-20 flex items-center justify-center rounded border bg-muted">
                    {fileIcon}
                  </div>
                ) : (
                  <img
                    src={url}
                    alt={`Preview ${index + 1}`}
                    className="w-full h-20 object-cover rounded border"
                    onError={(e) => {
                      // If image fails to load, show file icon
                      const target = e.target as HTMLImageElement;
                      const container = target.parentElement;
                      if (container) {
                        container.innerHTML = '<div class="w-full h-20 flex items-center justify-center rounded border bg-muted"><div class="h-16 w-16 bg-gray-500 rounded text-white text-xs flex items-center justify-center font-bold">FILE</div></div>';
                      }
                    }}
                  />
                )}
              </div>
            );
          })}
        </div>
      )}

      <MediaLibraryModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSelect={handleSelect}
        multiple={multiple}
      />
    </div>
  );
}
