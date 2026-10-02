import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Search, Filter, Eye, Edit, FolderOpen, CheckCircle, PauseCircle, Zap, BarChart2 } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudTable } from '@/components/CrudTable';
import { hasPermission } from '@/utils/authorization';
import { useTranslation } from 'react-i18next';

export default function ProjectReportsIndex() {
    const { t } = useTranslation();
    const { auth, projects, users, filters: pageFilters = {} } = usePage().props as any;
    const permissions = auth?.permissions || [];
    
    const formatText = (text: string) => {
        return text.replace(/_/g, ' ').split(' ').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    };
    
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [selectedUser, setSelectedUser] = useState(pageFilters.user_id || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [currentProject, setCurrentProject] = useState<any>(null);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };
    
    const applyFilters = () => {
        const params: any = { page: 1 };
        
        if (searchTerm) params.search = searchTerm;
        if (selectedStatus !== 'all') params.status = selectedStatus;
        if (selectedUser !== 'all') params.user_id = selectedUser;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
        
        router.get(route('project-reports.index'), params, { preserveState: false, preserveScroll: false });
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
        if (selectedUser !== 'all') params.user_id = selectedUser;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        
        router.get(route('project-reports.index'), params, { preserveState: true, preserveScroll: true });
    };
    
    const handleStatusFilter = (value: string) => {
        setSelectedStatus(value);
        const params: any = { page: 1 };
        if (searchTerm) params.search = searchTerm;
        if (value !== 'all') params.status = value;
        if (selectedUser !== 'all') params.user_id = selectedUser;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
        router.get(route('project-reports.index'), params, { preserveState: false, preserveScroll: false });
    };
    
    const handleUserFilter = (value: string) => {
        setSelectedUser(value);
        const params: any = { page: 1 };
        if (searchTerm) params.search = searchTerm;
        if (selectedStatus !== 'all') params.status = selectedStatus;
        if (value !== 'all') params.user_id = value;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
        router.get(route('project-reports.index'), params, { preserveState: false, preserveScroll: false });
    };
    
    const hasActiveFilters = () => {
        return selectedStatus !== 'all' || selectedUser !== 'all' || searchTerm !== '';
    };
    
    const activeFilterCount = () => {
        return (selectedStatus !== 'all' ? 1 : 0) + (selectedUser !== 'all' ? 1 : 0) + (searchTerm ? 1 : 0);
    };
    
    const handleResetFilters = () => {
        setSelectedStatus('all');
        setSelectedUser('all');
        setSearchTerm('');
        setShowFilters(false);
        const params: any = { page: 1 };
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
        router.get(route('project-reports.index'), params, { preserveState: false, preserveScroll: false });
    };
    
    // Handle actions for CrudTable
    const handleAction = (action: string, project: any) => {
        switch (action) {
            case 'view':
                router.get(route('project-reports.show', project.id));
                break;
            case 'edit':
                handleEditProject(project);
                break;
        }
    };
    
    const handleEditProject = (project: any) => {
        setCurrentProject(project);
        setIsEditModalOpen(true);
    };
    
    const handleFormSubmit = (formData: any) => {
        router.put(route('projects.update', currentProject.id), formData, {
            onSuccess: () => {
                setIsEditModalOpen(false);
                router.reload();
            }
        });
    };

    const getStatusColor = (status: string) => {
        const colors = {
            'active': 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            'planning': 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            'on_hold': 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            'completed': 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
            'cancelled': 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const formatDate = (dateString: string) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return isNaN(date.getTime()) ? '-' : window.appSettings.formatDateTime(new Date(date),false);
    };
    
    const getRoleColor = (role: string) => {
        const colors = {
            'owner': 'bg-purple-500',
            'manager': 'bg-blue-500', 
            'member': 'bg-green-500',
            'client': 'bg-orange-500'
        };
        return colors[role as keyof typeof colors] || 'bg-gray-500';
    };
    
    // CrudTable configuration
    const columns = [
        {
            key: 'title',
            label: t('Project'),
            sortable: true,
            render: (value: string, row: any) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">
                        {value || row.name}
                    </div>
                    <div className="text-sm text-gray-500 truncate max-w-xs">{row.description}</div>
                </div>
            )
        },
        {
            key: 'start_date',
            label: t('Start Date'),
            sortable: true,
            render: (value: string) => (
                <span className="text-sm text-gray-500">
                    {formatDate(value)}
                </span>
            )
        },
        {
            key: 'deadline',
            label: t('Due Date'),
            sortable: true,
            render: (value: string, row: any) => (
                <span className="text-sm text-gray-500">
                    {formatDate(row.deadline || row.end_date)}
                </span>
            )
        },
        {
            key: 'members',
            label: t('Members'),
            render: (value: any[], row: any) => {
                const allMembers = [...row.members || [], ...row.clients || []];
                if (allMembers.length === 0) return <span className="text-sm text-gray-500">-</span>;
                
                return (
                    <div className="flex -space-x-1">
                        {allMembers.slice(0, 3).map((member: any, index: number) => {
                            const isClient = row.clients?.some((c: any) => c.id === member.id);
                            const role = isClient ? 'client' : (member.role || 'member');
                            const name = isClient ? (member.name || '?') : (member.user?.name || '?');
                            const avatar = isClient ? member.avatar : member.user?.avatar;
                            return (
                                <Tooltip key={index}>
                                    <TooltipTrigger asChild>
                                        <Avatar className="h-6 w-6 border-2 border-white cursor-pointer">
                                            <AvatarImage src={avatar} />
                                            <AvatarFallback className={`text-[10px] font-semibold text-white ${getRoleColor(role)}`}>
                                                {name.charAt(0).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <div className="text-center">
                                            <div>{name}</div>
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            );
                        })}
                        {allMembers.length > 3 && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <div className="h-6 w-6 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs cursor-pointer">
                                        +{allMembers.length - 3}
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {allMembers.slice(3).map((m: any) => {
                                            const isCl = row.clients?.some((c: any) => c.id === m.id);
                                            return isCl ? m.name : m.user?.name;
                                        }).join(', ')}
                                </TooltipContent>
                            </Tooltip>
                        )}
                    </div>
                );
            }
        },
        {
            key: 'progress',
            label: t('Progress'),
            render: (value: number, row: any) => {
                const progress = value || row.progress_percentage || 0;
                return (
                    <div className="flex items-center">
                        <div className="w-16 bg-gray-200 rounded-full h-2 mr-2">
                            <div className="bg-blue-600 h-2 rounded-full" style={{width: `${progress}%`}}></div>
                        </div>
                        <span className="text-sm text-gray-900">{progress}%</span>
                    </div>
                );
            }
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
        }
    ];

    const actions = [
        {
            label: t('View'),
            icon: 'Eye',
            action: 'view',
            className: 'text-blue-500 hover:text-blue-700',
            condition: () => hasPermission(permissions, 'project_report_view')
        },
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500 hover:text-amber-700',
            condition: () => hasPermission(permissions, 'project_update')
        }
    ];
    
    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Project Reports') }
    ];

    return (
        <PageTemplate 
            title={t('Project Reports')} 
            url="/project-reports"
            actions={[]}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Search and filters section */}
            <div className="bg-white rounded-lg border shadow mb-4">
                <div className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search projects...')}
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
                            <Label className="text-xs text-muted-foreground">{t('Per Page')}:</Label>
                            <Select 
                                value={projects?.per_page?.toString() || pageFilters.per_page?.toString() || "10"} 
                                onValueChange={(value) => {
                                    const params: any = { page: 1, per_page: parseInt(value) };
                                    if (searchTerm) params.search = searchTerm;
                                    if (selectedStatus !== 'all') params.status = selectedStatus;
                                    if (selectedUser !== 'all') params.user_id = selectedUser;
                                    if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
                                    if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
                                    router.get(route('project-reports.index'), params, { preserveState: false, preserveScroll: false });
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
                                    <Select value={selectedStatus} onValueChange={handleStatusFilter}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Status')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Status')}</SelectItem>
                                            <SelectItem value="planning">{t('Planning')}</SelectItem>
                                            <SelectItem value="active">{t('Active')}</SelectItem>
                                            <SelectItem value="on_hold">{t('On Hold')}</SelectItem>
                                            <SelectItem value="completed">{t('Completed')}</SelectItem>
                                            <SelectItem value="cancelled">{t('Cancelled')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                
                                <div className="space-y-2">
                                    <Label>{t('User')}</Label>
                                    <Select value={selectedUser} onValueChange={handleUserFilter}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Users')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Users')}</SelectItem>
                                            {users?.map((user: any) => (
                                                <SelectItem key={user.id} value={user.id.toString()}>
                                                    {user.name}
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

            {/* Projects Table */}
            <div className="bg-white rounded-lg shadow overflow-hidden">
                <CrudTable
                    columns={columns}
                    actions={actions}
                    data={projects?.data || []}
                    from={projects?.from || 1}
                    onAction={handleAction}
                    sortField={pageFilters.sort_by}
                    sortDirection={pageFilters.sort_order}
                    onSort={handleSort}
                    permissions={permissions}
                />
                {/* Pagination */}
                {projects?.links && (
                    <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
                        <div className="text-sm text-muted-foreground">
                            {t('Showing')} <span className="font-medium">{projects?.from || 0}</span> {t('to')} <span className="font-medium">{projects?.to || 0}</span> {t('of')} <span className="font-medium">{projects?.total || 0}</span> {t('projects')}
                        </div>
                        
                        <div className="flex gap-1">
                            {projects?.links?.map((link: any, i: number) => {
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
            </div>
            
            {/* Edit Project Modal */}
            <CrudFormModal
                isOpen={isEditModalOpen}
                onClose={() => setIsEditModalOpen(false)}
                onSubmit={handleFormSubmit}
                formConfig={{
                    fields: [
                        { name: 'title', label: t('Project Title'), type: 'text', required: true, placeholder: t('Enter project title') },
                        { name: 'description', label: t('Description'), type: 'textarea', placeholder: t('Enter project description') },
                        { 
                            name: 'status', 
                            label: t('Status'), 
                            type: 'select',
                            required: true,
                            placeholder: t('Select status'),
                            options: [
                                { value: 'planning', label: t('Planning') },
                                { value: 'active', label: t('Active') },
                                { value: 'on_hold', label: t('On Hold') },
                                { value: 'completed', label: t('Completed') },
                                { value: 'cancelled', label: t('Cancelled') }
                            ]
                        },
                        { 
                            name: 'priority', 
                            label: t('Priority'), 
                            type: 'select',
                            required: true,
                            placeholder: t('Select priority'),
                            options: [
                                { value: 'low', label: t('Low') },
                                { value: 'medium', label: t('Medium') },
                                { value: 'high', label: t('High') },
                                { value: 'urgent', label: t('Urgent') }
                            ]
                        },
                        { name: 'start_date', label: t('Start Date'), type: 'date', required:true, placeholder: t('Select start date') },
                        { name: 'deadline', label: t('Deadline'), type: 'date', required:true, placeholder: t('Select deadline') },
                        { name: 'estimated_hours', label: t('Estimated Hours'), type: 'number', min: 0, placeholder: t('Enter estimated hours') },
                        { name: 'is_public', label: t('Make project public'), type: 'checkbox' }
                    ],
                    modalSize: 'xl'
                }}
                initialData={currentProject}
                title={t('Edit Project')}
                mode="edit"
            />
        </PageTemplate>
    );
}