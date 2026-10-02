import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Filter, X, Search } from 'lucide-react';

interface FilterOptions {
    search?: string;
    status?: string;
    user_id?: string;
    project_id?: string;
    start_date?: string;
    end_date?: string;
    is_billable?: string;
    min_hours?: string;
    max_hours?: string;
}

interface User {
    id: number;
    name: string;
}

interface Project {
    id: number;
    title: string;
}

interface Props {
    filters: FilterOptions;
    users: User[];
    projects: Project[];
    onFiltersChange: (filters: FilterOptions) => void;
    onClearFilters: () => void;
}

export default function TimesheetFilters({ 
    filters, 
    users, 
    projects, 
    onFiltersChange, 
    onClearFilters 
}: Props) {
    const [isExpanded, setIsExpanded] = useState(false);
    const [localFilters, setLocalFilters] = useState<FilterOptions>(filters);

    const handleFilterChange = (key: keyof FilterOptions, value: string) => {
        const newFilters = { ...localFilters, [key]: value };
        setLocalFilters(newFilters);
    };

    const applyFilters = () => {
        onFiltersChange(localFilters);
    };

    const clearFilters = () => {
        const emptyFilters: FilterOptions = {};
        setLocalFilters(emptyFilters);
        onClearFilters();
    };

    const getActiveFiltersCount = () => {
        return Object.values(filters).filter(value => value && value !== 'all').length;
    };

    const getFilterLabel = (key: string, value: string) => {
        switch (key) {
            case 'status':
                return `Status: ${value}`;
            case 'user_id':
                const user = users.find(u => u.id.toString() === value);
                return `Member: ${user?.name || value}`;
            case 'project_id':
                const project = projects.find(p => p.id.toString() === value);
                return `Project: ${project?.title || value}`;
            case 'is_billable':
                return `Billable: ${value === '1' ? 'Yes' : 'No'}`;
            case 'start_date':
                return `From: ${new Date(value).toLocaleDateString()}`;
            case 'end_date':
                return `To: ${new Date(value).toLocaleDateString()}`;
            default:
                return `${key}: ${value}`;
        }
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Filter className="h-5 w-5" />
                        Filters
                        {getActiveFiltersCount() > 0 && (
                            <Badge variant="secondary">
                                {getActiveFiltersCount()} active
                            </Badge>
                        )}
                    </CardTitle>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setIsExpanded(!isExpanded)}
                        >
                            {isExpanded ? 'Hide' : 'Show'} Filters
                        </Button>
                        {getActiveFiltersCount() > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={clearFilters}
                            >
                                <X className="h-4 w-4 mr-1" />
                                Clear
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>

            {/* Active Filters Display */}
            {getActiveFiltersCount() > 0 && (
                <CardContent className="pt-0">
                    <div className="flex flex-wrap gap-2">
                        {Object.entries(filters).map(([key, value]) => {
                            if (!value || value === 'all') return null;
                            return (
                                <Badge key={key} variant="outline" className="gap-1">
                                    {getFilterLabel(key, value)}
                                    <X 
                                        className="h-3 w-3 cursor-pointer" 
                                        onClick={() => handleFilterChange(key as keyof FilterOptions, '')}
                                    />
                                </Badge>
                            );
                        })}
                    </div>
                </CardContent>
            )}

            {isExpanded && (
                <CardContent className="pt-0">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {/* Search */}
                        <div className="space-y-2">
                            <Label>Search</Label>
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search timesheets..."
                                    value={localFilters.search || ''}
                                    onChange={(e) => handleFilterChange('search', e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                        </div>

                        {/* Status */}
                        <div className="space-y-2">
                            <Label>Status</Label>
                            <Select 
                                value={localFilters.status || 'all'} 
                                onValueChange={(value) => handleFilterChange('status', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Status</SelectItem>
                                    <SelectItem value="draft">Draft</SelectItem>
                                    <SelectItem value="submitted">Submitted</SelectItem>
                                    <SelectItem value="approved">Approved</SelectItem>
                                    <SelectItem value="rejected">Rejected</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Member */}
                        <div className="space-y-2">
                            <Label>Member</Label>
                            <Select 
                                value={localFilters.user_id || 'all'} 
                                onValueChange={(value) => handleFilterChange('user_id', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Members</SelectItem>
                                    {users.map(user => (
                                        <SelectItem key={user.id} value={user.id.toString()}>
                                            {user.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Project */}
                        <div className="space-y-2">
                            <Label>Project</Label>
                            <Select 
                                value={localFilters.project_id || 'all'} 
                                onValueChange={(value) => handleFilterChange('project_id', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Projects</SelectItem>
                                    {projects.map(project => (
                                        <SelectItem key={project.id} value={project.id.toString()}>
                                            {project.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Start Date */}
                        <div className="space-y-2">
                            <Label>Start Date</Label>
                            <Input
                                type="date"
                                value={localFilters.start_date || ''}
                                onChange={(e) => handleFilterChange('start_date', e.target.value)}
                            />
                        </div>

                        {/* End Date */}
                        <div className="space-y-2">
                            <Label>End Date</Label>
                            <Input
                                type="date"
                                value={localFilters.end_date || ''}
                                onChange={(e) => handleFilterChange('end_date', e.target.value)}
                            />
                        </div>

                        {/* Billable */}
                        <div className="space-y-2">
                            <Label>Billable</Label>
                            <Select 
                                value={localFilters.is_billable || 'all'} 
                                onValueChange={(value) => handleFilterChange('is_billable', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="1">Billable Only</SelectItem>
                                    <SelectItem value="0">Non-Billable Only</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Min Hours */}
                        <div className="space-y-2">
                            <Label>Min Hours</Label>
                            <Input
                                type="number"
                                step="0.25"
                                min="0"
                                placeholder="0.00"
                                value={localFilters.min_hours || ''}
                                onChange={(e) => handleFilterChange('min_hours', e.target.value)}
                            />
                        </div>

                        {/* Max Hours */}
                        <div className="space-y-2">
                            <Label>Max Hours</Label>
                            <Input
                                type="number"
                                step="0.25"
                                min="0"
                                placeholder="24.00"
                                value={localFilters.max_hours || ''}
                                onChange={(e) => handleFilterChange('max_hours', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 mt-4">
                        <Button variant="outline" onClick={clearFilters}>
                            Clear All
                        </Button>
                        <Button onClick={applyFilters}>
                            Apply Filters
                        </Button>
                    </div>
                </CardContent>
            )}
        </Card>
    );
}