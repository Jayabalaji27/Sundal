import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Filter, Grid, List, SortAsc, SortDesc } from 'lucide-react';
import { TaskAttachment } from '@/types';

interface Props {
    attachments: TaskAttachment[];
    onFilter: (filters: FileFilters) => void;
    viewMode: 'grid' | 'list';
    onViewModeChange: (mode: 'grid' | 'list') => void;
}

interface FileFilters {
    type: string;
    sortBy: 'name' | 'date' | 'size';
    sortOrder: 'asc' | 'desc';
}

export default function FileOrganizer({ attachments, onFilter, viewMode, onViewModeChange }: Props) {
    const [filters, setFilters] = useState<FileFilters>({
        type: 'all',
        sortBy: 'date',
        sortOrder: 'desc'
    });

    const handleFilterChange = (key: keyof FileFilters, value: string) => {
        const newFilters = { ...filters, [key]: value };
        setFilters(newFilters);
        onFilter(newFilters);
    };

    const getFileTypes = () => {
        const types = new Set<string>();
        attachments.forEach(attachment => {
            if (attachment.media_item?.mime_type) {
                const mainType = attachment.media_item.mime_type.split('/')[0];
                types.add(mainType);
            }
        });
        return Array.from(types);
    };

    const getFileTypeLabel = (type: string) => {
        switch (type) {
            case 'image': return 'Images';
            case 'video': return 'Videos';
            case 'audio': return 'Audio';
            case 'application': return 'Documents';
            case 'text': return 'Text Files';
            default: return type;
        }
    };

    const fileTypes = getFileTypes();

    return (
        <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div className="flex items-center space-x-4">
                {/* File Type Filter */}
                <div className="flex items-center space-x-2">
                    <Filter className="h-4 w-4 text-gray-500" />
                    <Select value={filters.type} onValueChange={(value) => handleFilterChange('type', value)}>
                        <SelectTrigger className="w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Files</SelectItem>
                            {fileTypes.map(type => (
                                <SelectItem key={type} value={type}>
                                    {getFileTypeLabel(type)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Sort Options */}
                <div className="flex items-center space-x-2">
                    <Select value={filters.sortBy} onValueChange={(value) => handleFilterChange('sortBy', value)}>
                        <SelectTrigger className="w-24">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="name">Name</SelectItem>
                            <SelectItem value="date">Date</SelectItem>
                            <SelectItem value="size">Size</SelectItem>
                        </SelectContent>
                    </Select>
                    
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleFilterChange('sortOrder', filters.sortOrder === 'asc' ? 'desc' : 'asc')}
                    >
                        {filters.sortOrder === 'asc' ? (
                            <SortAsc className="h-4 w-4" />
                        ) : (
                            <SortDesc className="h-4 w-4" />
                        )}
                    </Button>
                </div>

                {/* File Count */}
                <Badge variant="outline">
                    {attachments.length} files
                </Badge>
            </div>

            {/* View Mode Toggle */}
            <div className="flex items-center space-x-1 bg-white rounded-md p-1">
                <Button
                    variant={viewMode === 'grid' ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => onViewModeChange('grid')}
                >
                    <Grid className="h-4 w-4" />
                </Button>
                <Button
                    variant={viewMode === 'list' ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => onViewModeChange('list')}
                >
                    <List className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}