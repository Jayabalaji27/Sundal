import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Video, Clock, Edit, Trash2, Eye, Copy, Search, Filter, ExternalLink, Play } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { CrudTable } from '@/components/CrudTable';
import { toast } from '@/components/custom-toast';
import { hasPermission } from '@/utils/authorization';
import { useTranslation } from 'react-i18next';
import GoogleMeetingModal from './GoogleMeetingModal';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

export default function GoogleMeetingIndex() {
    const { t } = useTranslation();
    const { auth, meetings, projects, members, hasGoogleMeetConfig, filters: pageFilters = {}, permissions, flash, googleCalendarEnabled } = usePage().props as any;
    
    const formatText = (text: string) => {
        return text.replace(/_/g, ' ').split(' ').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    };
    
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [selectedProject, setSelectedProject] = useState(pageFilters.project_id || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentMeeting, setCurrentMeeting] = useState<any>(null);

    // Handle flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };
    
    const applyFilters = () => {
        const params: any = { page: 1 };
        
        if (searchTerm) params.search = searchTerm;
        if (selectedStatus !== 'all') params.status = selectedStatus;
        if (selectedProject !== 'all') params.project_id = selectedProject;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
        
        router.get(route('google-meetings.index'), params, { preserveState: false, preserveScroll: false });
    };

    const hasActiveFilters = () => {
        return selectedStatus !== 'all' || selectedProject !== 'all' || searchTerm !== '';
    };

    const activeFilterCount = () => {
        return (selectedStatus !== 'all' ? 1 : 0) + (selectedProject !== 'all' ? 1 : 0) + (searchTerm ? 1 : 0);
    };

    const handleResetFilters = () => {
        setSelectedStatus('all');
        setSelectedProject('all');
        setSearchTerm('');
        setShowFilters(false);
        const params: any = { page: 1 };
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        router.get(route('google-meetings.index'), params, { preserveState: false, preserveScroll: false });
    };

    // Handle actions for CrudTable
    const handleAction = (action: string, meeting: any) => {
        switch (action) {
            case 'view':
                router.get(route('google-meetings.show', meeting.id));
                break;
            case 'edit':
                setCurrentMeeting(meeting);
                setIsEditModalOpen(true);
                break;
            case 'delete':
                setCurrentMeeting(meeting);
                setIsDeleteModalOpen(true);
                break;
        }
    };

    const handleDeleteConfirm = () => {
        toast.loading('Deleting meeting...');
        router.delete(route('google-meetings.destroy', currentMeeting.id), {
            onSuccess: () => {
                setIsDeleteModalOpen(false);
                toast.dismiss();
                if (flash?.success) {
                    toast.success(flash.success);
                }
            },
            onError: (errors) => {
                toast.dismiss();
                if (flash?.error) {
                    toast.error(flash.error);
                } else {
                    toast.error('Failed to delete meeting');
                }
            }
        });
    };

    const getStatusColor = (status: string) => {
        const colors = {
            scheduled: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            started: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            ended: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
            cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
        };
        return colors[status as keyof typeof colors] || colors.scheduled;
    };

    // Add sorting functionality
    const handleSort = (field: string) => {
        const direction = pageFilters.sort_by === field && pageFilters.sort_order === 'asc' ? 'desc' : 'asc';
        
        const params: any = { 
            sort_by: field, 
            sort_order: direction, 
            page: 1 
        };
        
        // Preserve existing filters
        if (searchTerm) params.search = searchTerm;
        if (selectedStatus !== 'all') params.status = selectedStatus;
        if (selectedProject !== 'all') params.project_id = selectedProject;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        
        router.get(route('google-meetings.index'), params, { preserveState: true, preserveScroll: true });
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Google Meetings') }
    ];

    // Show configuration message when Google Meet is not configured (skip in demo mode)
    const { is_demo } = usePage().props as any;
    if (!hasGoogleMeetConfig && !is_demo) {
        const canConfigureGoogleMeet = hasPermission(auth?.permissions, 'settings_google_meet');
        
        return (
            <PageTemplate title={t('Google Meetings')} breadcrumbs={breadcrumbs} url="/google-meetings">
                <Card>
                    <CardContent className="p-6 text-center">
                        <Video className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                            {t('Google Meet Integration Not Configured')}
                        </h3>
                        <p className="text-gray-500 mb-4">
                            {canConfigureGoogleMeet 
                                ? t('Please configure your Google Meet API credentials in Settings to use this feature.')
                                : t('Google Meet integration has not been configured yet. Ask your workspace owner to configure Google Meet.')
                            }
                        </p>
                        <div className="flex items-center justify-center gap-2">
                            {canConfigureGoogleMeet && (
                                <Button onClick={() => router.get(route('settings') + '#google-meet-settings')}>
                                    {t('Configure Google Meet Settings')}
                                </Button>
                            )}
                            {hasPermission(auth?.permissions, 'zoom_meeting_view_any') && (
                                <Button variant="outline" onClick={() => router.get(route('zoom-meetings.index'))}>
                                    {t('Switch to Zoom Meetings')}
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </PageTemplate>
        );
    }

    const pageActions = [];

    if (hasPermission(auth?.permissions, 'zoom_meeting_view_any')) {
        pageActions.push({
            label: t('Zoom Meetings'),
            icon: <Video className="h-4 w-4 mr-2" />,
            variant: 'outline' as const,
            onClick: () => router.get(route('zoom-meetings.index')),
        });
    }

    if (hasPermission(auth?.permissions, 'google_meeting_create')) {
        pageActions.push({
            label: t('Create Meeting'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: () => {
                setIsCreateModalOpen(true);
            }
        });
    }
    
    // const breadcrumbs = [
    //     { title: t('Dashboard'), href: route('dashboard') },
    //     { title: t('Google Meetings') }
    // ];

    // CrudTable configuration
    const columns = [
        {
            key: 'title',
            label: t('Meeting'),
            sortable: true,
            render: (value: string, row: any) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">
                        {value}
                    </div>
                    {row.description && (
                        <div className="text-sm text-gray-500 truncate max-w-xs">
                            {row.description}
                        </div>
                    )}
                </div>
            )
        },
        {
            key: 'status',
            label: t('Status'),
            sortable: true,
            render: (value: string) => (
                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(value)}`}>
                    {formatText(value)}
                </span>
            )
        },
        {
            key: 'start_time',
            label: t('Date & Time'),
            sortable: true,
            render: (value: string) => (
                <div className="flex items-center">
                    <Clock className="h-4 w-4 mr-1" />
                    {value ? window.appSettings.formatDateTime(new Date(value), false) : ''}
                </div>
            )
        },
        {
            key: 'duration',
            label: t('Duration'),
            sortable: true,
            render: (value: number) => `${value} minutes`
        },
        {
            key: 'project.title',
            label: t('Project'),
            render: (value: string) => value || '-'
        },
        {
            key: 'meeting_urls',
            label: t('URL'),
            render: (value: any, row: any) => (
                <div className="flex gap-1">
                    {row.join_url && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        window.open(row.join_url, '_blank');
                                        // navigator.clipboard.writeText(row.join_url);
                                        // toast.success('Join URL copied to clipboard');
                                    }}
                                    className="bg-purple-100 text-purple-700 border-purple-300 hover:bg-purple-200 w-8 h-8 p-0 flex items-center justify-center size-7"
                                >
                                    <ExternalLink className="h-2.5 w-2.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{t('Join')}</TooltipContent>
                        </Tooltip>
                    )}
                    {row.start_url && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        window.open(row.start_url, '_blank');
                                        // navigator.clipboard.writeText(row.start_url);
                                        // toast.success('Start URL copied to clipboard');
                                    }}
                                    className="bg-green-100 text-green-700 border-green-300 hover:bg-green-200 w-8 h-8 p-0 flex items-center justify-center size-7"
                                >
                                    <Play className="h-2.5 w-2.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{t('Join')}</TooltipContent>
                        </Tooltip>
                    )}
                </div>
            )
        }
    ];

    const actions = [
        {
            label: t('View'),
            icon: 'Eye',
            action: 'view',
            className: 'text-blue-500 hover:text-blue-700',
            condition: () => hasPermission(auth?.permissions, 'google_meeting_view')
        },
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500 hover:text-amber-700',
            condition: () => hasPermission(auth?.permissions, 'google_meeting_update')
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500 hover:text-red-700',
            condition: () => hasPermission(auth?.permissions, 'google_meeting_delete')
        }
    ];

    return (
        <PageTemplate 
            title={t('Google Meetings')} 
            url="/google-meetings"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Search and filters section */}
            <div className="bg-white rounded-lg shadow mb-4">
                <div className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search meetings...')}
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="w-full pl-9"
                                    />
                                </div>
                                <Button type="submit" size="sm">
                                    <Search className="h-4 w-4 mr-1.5" />
                                    {t('Search')}
                                </Button>
                            </form>
                            
                            <div className="ml-2">
                                <Button 
                                    variant={hasActiveFilters() ? "default" : "outline"}
                                    size="sm" 
                                    className="h-8 px-2 py-1"
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter className="h-3.5 w-3.5 mr-1.5" />
                                    {showFilters ? t('Hide Filters') : t('Filters')}
                                    {hasActiveFilters() && (
                                        <span className="ml-1 bg-primary-foreground text-primary rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                            {activeFilterCount()}
                                        </span>
                                    )}
                                </Button>
                            </div>
                        </div>
                        
                        <div className="flex items-center gap-2">
                            <Label className="text-xs text-muted-foreground">Per Page:</Label>
                            <Select 
                                value={pageFilters.per_page?.toString() || '10'} 
                                onValueChange={(value) => {
                                    const params: any = { page: 1, per_page: parseInt(value) };
                                    if (searchTerm) params.search = searchTerm;
                                    if (selectedStatus !== 'all') params.status = selectedStatus;
                                    if (selectedProject !== 'all') params.project_id = selectedProject;
                                    if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
                                    if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
                                    router.get(route('google-meetings.index'), params, { preserveState: false, preserveScroll: false });
                                }}
                            >
                                <SelectTrigger className="w-16 h-8">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="10">10</SelectItem>
                                    <SelectItem value="25">25</SelectItem>
                                    <SelectItem value="50">50</SelectItem>
                                    <SelectItem value="100">100</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    
                    {showFilters && (
                        <div className="w-full mt-3 p-4 bg-gray-50 border rounded-md">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <Label>{t('Status')}</Label>
                                    <Select value={selectedStatus} onValueChange={(value) => {
                                        setSelectedStatus(value);
                                        const params: any = { page: 1 };
                                        if (searchTerm) params.search = searchTerm;
                                        if (value !== 'all') params.status = value;
                                        if (selectedProject !== 'all') params.project_id = selectedProject;
                                        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
                                        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
                                        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
                                        router.get(route('google-meetings.index'), params, { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder="All Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Status')}</SelectItem>
                                            <SelectItem value="scheduled">Scheduled</SelectItem>
                                            <SelectItem value="started">Started</SelectItem>
                                            <SelectItem value="ended">Ended</SelectItem>
                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                
                                <div className="space-y-2">
                                    <Label>{t('Project')}</Label>
                                    <Select value={selectedProject} onValueChange={(value) => {
                                        setSelectedProject(value);
                                        const params: any = { page: 1 };
                                        if (searchTerm) params.search = searchTerm;
                                        if (selectedStatus !== 'all') params.status = selectedStatus;
                                        if (value !== 'all') params.project_id = value;
                                        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
                                        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
                                        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
                                        router.get(route('google-meetings.index'), params, { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder="All Projects" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Projects')}</SelectItem>
                                            {projects?.map((project: any) => (
                                                <SelectItem key={project.id} value={project.id.toString()}>
                                                    {project.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    className="h-9"
                                    onClick={handleResetFilters}
                                    disabled={!hasActiveFilters()}
                                >
                                    {t('Reset Filters')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Meetings Table */}
            <div className="bg-white rounded-lg shadow">
                <CrudTable
                    columns={columns}
                    actions={actions}
                    data={meetings?.data || []}
                    from={meetings?.from || 1}
                    onAction={handleAction}
                    sortField={pageFilters.sort_by}
                    sortDirection={pageFilters.sort_order}
                    onSort={handleSort}
                    permissions={auth?.permissions || []}
                />
            </div>

            {/* Empty State */}
            {meetings?.data?.length === 0 && (
                <div className="bg-white rounded-lg shadow p-8 text-center">
                    <Video className="h-12 w-12 mx-auto mb-4 text-gray-400" />
                    <h3 className="text-lg font-semibold mb-2">{t('No meetings found')}</h3>
                    <p className="text-gray-500 mb-4">
                        {t('Get started by creating your first Google Meet meeting.')}
                    </p>
                    {hasPermission(auth?.permissions, 'google_meeting_create') && (
                        <Button onClick={() => setIsCreateModalOpen(true)}>
                            <Plus className="h-4 w-4 mr-2" />
                            {t('Create Meeting')}
                        </Button>
                    )}
                </div>
            )}
            
            {/* Pagination */}
            {meetings?.links && meetings.data.length > 0 && (
                <div className="mt-6 bg-[#F0F0F1] dark:bg-gray-800 p-4 rounded-lg shadow flex flex-wrap items-center justify-between gap-3">
                    <div className="text-sm text-muted-foreground">
                        {t('Showing')} <span className="font-medium">{meetings?.from || 0}</span> {t('to')} <span className="font-medium">{meetings?.to || 0}</span> {t('of')} <span className="font-medium">{meetings?.total || 0}</span> {t('meetings')}
                    </div>

                    <div className="flex flex-wrap gap-1">
                        {meetings?.links?.map((link: any, i: number) => {
                            const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                            const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");
                            
                            return (
                                <Button
                                    key={i}
                                    variant={link.active ? 'default' : 'outline'}
                                    size={isTextLink ? "sm" : "icon"}
                                    className={isTextLink ? "px-3" : "h-8 w-8"}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url)}
                                >
                                    {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                </Button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Create Modal */}
            <GoogleMeetingModal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                projects={projects || []}
                members={members || []}
                googleCalendarEnabled={googleCalendarEnabled}
            />

            {/* Edit Modal */}
            <GoogleMeetingModal
                isOpen={isEditModalOpen}
                onClose={() => setIsEditModalOpen(false)}
                meeting={currentMeeting}
                projects={projects || []}
                members={members || []}
                googleCalendarEnabled={googleCalendarEnabled}
            />

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentMeeting?.title || ''}
                entityName={t('meeting')}
                warningMessage={t('This meeting will be permanently deleted from Google Meet as well.')}
            />
        </PageTemplate>
    );
}