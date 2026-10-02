import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Plus, Search, Filter, Eye, Edit, Trash2, Video, Calendar, Clock, Users, Copy } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { toast } from '@/components/custom-toast';
import { hasPermission } from '@/utils/authorization';
import { useTranslation } from 'react-i18next';
import ZoomMeetingModal from './ZoomMeetingModal';
import { formatDateTime } from '@/lib/utils';

export default function ZoomMeetingIndex() {
    const { t } = useTranslation();
    const { auth, meetings, projects, members, hasZoomConfig, filters: pageFilters = {}, permissions, flash } = usePage().props as any;
    
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
        
        router.get(route('zoom-meetings.index'), params, { preserveState: false, preserveScroll: false });
    };

    const handleJoinMeeting = (meeting: any) => {
        if (meeting.join_url) {
            window.open(meeting.join_url, '_blank');
        }
    };

    const handleStartMeeting = (meeting: any) => {
        if (meeting.start_url) {
            window.open(meeting.start_url, '_blank');
        }
    };

    const handleEditMeeting = (meeting: any) => {
        setCurrentMeeting(meeting);
        setIsEditModalOpen(true);
    };

    const handleDeleteMeeting = (meeting: any) => {
        setCurrentMeeting(meeting);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        router.delete(route('zoom-meetings.destroy', currentMeeting.id), {
            onSuccess: () => {
                setIsDeleteModalOpen(false);
            },
            onError: () => {
                toast.error('Failed to delete meeting');
            }
        });
    };

    const getStatusColor = (status: string) => {
        const colors = {
            scheduled: 'bg-blue-100 text-blue-800',
            started: 'bg-green-100 text-green-800',
            ended: 'bg-gray-100 text-gray-800',
            cancelled: 'bg-red-100 text-red-800',
        };
        return colors[status as keyof typeof colors] || colors.scheduled;
    };

    const hasActiveFilters = () => {
        return selectedStatus !== 'all' || selectedProject !== 'all' || searchTerm !== '';
    };

    const handleResetFilters = () => {
        setSelectedStatus('all');
        setSelectedProject('all');
        setSearchTerm('');
        setShowFilters(false);
        router.get(route('zoom-meetings.index'), { page: 1 }, { preserveState: false, preserveScroll: false });
    };

    // Show configuration message when Zoom is not configured
    if (!hasZoomConfig) {
        const canConfigureZoom = hasPermission(auth?.permissions, 'settings_zoom');

        return (
            <PageTemplate title={t('Zoom Meetings')} url="/zoom-meetings">
                <Card>
                    <CardContent className="p-6 text-center">
                        <Video className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                            {t('Zoom Integration Not Configured')}
                        </h3>
                        <p className="text-gray-500 mb-4">
                            {canConfigureZoom 
                                ? t('Please configure your Zoom API credentials in Settings to use this feature.')
                                : t('Zoom integration has not been configured yet. Please contact your admin or manager to set it up in Settings.')
                            }
                        </p>
                        <div className="flex items-center justify-center gap-2">
                            {canConfigureZoom && (
                                <Button onClick={() => router.get(route('settings') + '#zoom-settings')}>
                                    {t('Configure Zoom Settings')}
                                </Button>
                            )}
                            {hasPermission(auth?.permissions, 'google_meeting_view_any') && (
                                <Button variant="outline" onClick={() => router.get(route('google-meetings.index'))}>
                                    {t('Switch to Google Meet')}
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </PageTemplate>
        );
    }

    const pageActions = [];

    if (hasPermission(auth?.permissions, 'google_meeting_view_any')) {
        pageActions.push({
            label: t('Google Meet'),
            icon: <Video className="h-4 w-4 mr-2" />,
            variant: 'outline' as const,
            onClick: () => router.get(route('google-meetings.index')),
        });
    }

    if (hasPermission(auth?.permissions, 'zoom_meeting_create')) {
        pageActions.push({
            label: t('Create Meeting'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: () => {
                console.log('Create Meeting button clicked');
                setIsCreateModalOpen(true);
            }
        });
    }
    
    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Zoom Meetings') }
    ];

    return (
        <PageTemplate 
            title={t('Zoom Meetings')} 
            url="/zoom-meetings"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Overview Row */}
            <Card className="mb-4 hover:shadow-md transition-shadow">
                <CardContent className="p-4">
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div className="text-center">
                            <div className="text-xl font-bold text-blue-600">
                                {meetings?.total || 0}
                            </div>
                            <div className="text-xs text-gray-600">{t('Total Meetings')}</div>
                        </div>
                        <div className="text-center">
                            <div className="text-xl font-bold text-green-600">
                                {meetings?.data?.filter((meeting: any) => meeting.status === 'scheduled').length || 0}
                            </div>
                            <div className="text-xs text-gray-600">{t('Scheduled')}</div>
                        </div>
                        <div className="text-center">
                            <div className="text-xl font-bold text-orange-600">
                                {meetings?.data?.filter((meeting: any) => new Date(meeting.start_time) > new Date()).length || 0}
                            </div>
                            <div className="text-xs text-gray-600">{t('Upcoming')}</div>
                        </div>
                        <div className="text-center">
                            <div className="text-xl font-bold text-gray-600">
                                {meetings?.data?.filter((meeting: any) => meeting.status === 'ended').length || 0}
                            </div>
                            <div className="text-xs text-gray-600">{t('Completed')}</div>
                        </div>
                    </div>
                </CardContent>
            </Card>



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
                                </Button>
                            </div>
                        </div>
                    </div>
                    
                    {showFilters && (
                        <div className="w-full mt-3 p-4 bg-gray-50 border rounded-md">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">{t('Status')}</label>
                                    <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Status')} />
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
                                    <label className="text-sm font-medium">{t('Project')}</label>
                                    <Select value={selectedProject} onValueChange={setSelectedProject}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Projects')} />
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

            {/* Meetings List */}
            <div className="bg-white rounded-lg shadow overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{t('Meeting')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{t('Status')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{t('Date & Time')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{t('Duration')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{t('Project')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{t('Meeting URLs')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{t('Actions')}</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {meetings?.data?.length > 0 ? meetings.data.map((meeting: any) => (
                                <tr key={meeting.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div className="text-sm font-medium text-gray-900">
                                                {meeting.title}
                                            </div>
                                            {meeting.description && (
                                                <div className="text-sm text-gray-500 truncate max-w-xs">
                                                    {meeting.description}
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <Badge className={getStatusColor(meeting.status)} variant="secondary">
                                            {meeting.status}
                                        </Badge>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div className="flex items-center">
                                            <Clock className="h-4 w-4 mr-1" />
                                            {formatDateTime(meeting.start_time)}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {meeting.duration} minutes
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {meeting.project?.title || '-'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="flex gap-1">
                                            {meeting.join_url && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        navigator.clipboard.writeText(meeting.join_url);
                                                        toast.success('Join URL copied to clipboard');
                                                    }}
                                                    className="text-blue-600 border-blue-200 hover:bg-blue-50 h-7 px-2 text-xs"
                                                >
                                                    <Copy className="h-3 w-3 mr-1" />
                                                    Join URL
                                                </Button>
                                            )}
                                            {meeting.start_url && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        navigator.clipboard.writeText(meeting.start_url);
                                                        toast.success('Start URL copied to clipboard');
                                                    }}
                                                    className="text-green-600 border-green-200 hover:bg-green-50 h-7 px-2 text-xs"
                                                >
                                                    <Copy className="h-3 w-3 mr-1" />
                                                    Start URL
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div className="flex gap-1">
                                            {hasPermission(auth?.permissions, 'zoom_meeting_view') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => router.get(route('zoom-meetings.show', meeting.id))}
                                                    className="text-blue-500 hover:text-blue-700 h-8 w-8"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            )}
                                            {hasPermission(auth?.permissions, 'zoom_meeting_update') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleEditMeeting(meeting)}
                                                    className="text-amber-500 hover:text-amber-700 h-8 w-8"
                                                >
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                            )}
                                            {hasPermission(auth?.permissions, 'zoom_meeting_delete') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleDeleteMeeting(meeting)}
                                                    className="text-red-500 hover:text-red-700 h-8 w-8"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={7} className="px-6 py-12 text-center">
                                        <Video className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                                            No meetings found
                                        </h3>
                                        <p className="text-gray-500 mb-4">
                                            Get started by creating your first Zoom meeting.
                                        </p>
                                        {hasPermission(auth?.permissions, 'zoom_meeting_create') && (
                                            <Button onClick={() => setIsCreateModalOpen(true)}>
                                                <Plus className="h-4 w-4 mr-2" />
                                                Create Meeting
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
            
            {/* Pagination */}
            {meetings?.links && meetings.data.length > 0 && (
                <div className="mt-6 bg-white p-4 rounded-lg shadow flex flex-wrap items-center justify-between gap-3">
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
            <ZoomMeetingModal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                projects={projects || []}
                members={members || []}
            />

            {/* Edit Modal */}
            <ZoomMeetingModal
                isOpen={isEditModalOpen}
                onClose={() => setIsEditModalOpen(false)}
                meeting={currentMeeting}
                projects={projects || []}
                members={members || []}
            />

            {/* Delete Modal */}
            <EnhancedDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentMeeting?.title || ''}
                entityName={t('meeting')}
                warningMessage={t('This meeting will be permanently deleted from Zoom as well.')}
            />
        </PageTemplate>
    );
}