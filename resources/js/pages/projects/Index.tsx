import { useState, useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Progress } from '@/components/ui/progress';
import { ProjectCard } from '@/components/projects/project-card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Plus, Search, Eye, Edit, Trash2, LayoutGrid, List, CalendarDays, FileDown, Globe, MoreHorizontal, Sparkles } from 'lucide-react';
import { StatStrip } from '@/components/stat-strip';
import { FilterPopover } from '@/components/filter-popover';
import { consumeCreateIntent } from '@/utils/create-intent';
import { AiProjectModal } from '@/components/AiProjectModal';
import { EmptyState, EMPTY_STATES } from '@/components/EmptyState';
import { PageTemplate } from '@/components/page-template';
import { CrudFormModal } from '@/components/CrudFormModal';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import axios from 'axios';
import { toast } from '@/components/custom-toast';
import { CrudTable } from '@/components/CrudTable';
import { hasPermission } from '@/utils/authorization';
import { useTranslation } from 'react-i18next';

export default function ProjectIndex() {
    const { t } = useTranslation();
    const { auth, projects, stats = {}, members, clients, filters: pageFilters = {}, errors, flash } = usePage().props as any;
    const permissions = auth?.permissions || [];
    
    const formatText = (text: string) => {
        if (!text) return '';
        return text.replace(/_/g, ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
    };
    

    
    // Check user role for each project
    const canEditProject = (project: any) => {
        const isWorkspaceOwner = auth?.user?.id === project?.workspace?.owner_id;
        const isProjectClient = project?.clients?.some((client: any) => client.id === auth?.user?.id);
        return isWorkspaceOwner || isProjectClient;
    };
    
    const canDeleteProject = (project: any) => {
        return auth?.user?.id === project?.workspace?.owner_id;
    };
    
    const [activeView, setActiveView] = useState(pageFilters.view || 'grid');
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [selectedPriority, setSelectedPriority] = useState(pageFilters.priority || 'all');
    const overdueOnly = ['1', 'true', 1, true].includes(pageFilters.overdue);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [deleteSummary, setDeleteSummary] = useState<{ counts: Record<string, number>; blocked: boolean } | null>(null);
    const [currentItem, setCurrentItem] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
    const [isAiModalOpen, setIsAiModalOpen] = useState(false);

    // Handle flash messages
    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // Opened from a "Create > Project" link.
    useEffect(() => {
        if (consumeCreateIntent() && hasPermission(permissions, 'project_create')) handleAddNew();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const buildParams = (
        overrides: Record<string, any> = {},
        opts: { search?: string; status?: string; priority?: string; view?: string; overdue?: boolean } = {}
    ) => {
        const search = opts.search !== undefined ? opts.search : searchTerm;
        const status = opts.status !== undefined ? opts.status : selectedStatus;
        const priority = opts.priority !== undefined ? opts.priority : selectedPriority;
        const view = opts.view !== undefined ? opts.view : activeView;
        const overdue = opts.overdue !== undefined ? opts.overdue : overdueOnly;

        const params: any = { page: 1, view };
        if (search) params.search = search;
        if (status !== 'all') params.status = status;
        if (priority !== 'all') params.priority = priority;
        if (overdue) params.overdue = 1;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_field) params.sort_field = pageFilters.sort_field;
        if (pageFilters.sort_direction) params.sort_direction = pageFilters.sort_direction;
        return { ...params, ...overrides };
    };

    const navigate = (params: any) =>
        router.get(route('projects.index'), params, { preserveState: false, preserveScroll: false });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        navigate(buildParams({ page: 1 }));
    };

    // Search as you type. preserveState keeps the input (and its focus) while
    // the list reloads underneath it.
    const isFirstSearchRender = useRef(true);
    useEffect(() => {
        if (isFirstSearchRender.current) {
            isFirstSearchRender.current = false;
            return;
        }
        if (searchTerm === (pageFilters.search || '')) return;
        const timeout = setTimeout(() => {
            router.get(route('projects.index'), buildParams({ page: 1 }), { preserveState: true, preserveScroll: true, replace: true });
        }, 400);
        return () => clearTimeout(timeout);
    }, [searchTerm]);

    // Status shortcuts from the stat strip; picking the applied one again clears it.
    const showStatus = (status: string) => {
        const next = !overdueOnly && selectedStatus === status ? 'all' : status;
        setSelectedStatus(next);
        navigate(buildParams({ page: 1 }, { status: next, overdue: false }));
    };
    const showOverdue = () => {
        setSelectedStatus('all');
        navigate(buildParams({ page: 1 }, { status: 'all', overdue: !overdueOnly }));
    };

    const handleSort = (field: string) => {
        const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
        navigate(buildParams({ sort_field: field, sort_direction: direction, page: 1 }));
    };

    const handleStatusFilter = (value: string) => {
        setSelectedStatus(value);
        navigate(buildParams({ page: 1 }, { status: value }));
    };

    const handlePriorityFilter = (value: string) => {
        setSelectedPriority(value);
        navigate(buildParams({ page: 1 }, { priority: value }));
    };

    const handleViewChange = (view: string) => {
        setActiveView(view);
        navigate(buildParams({ page: 1 }, { view }));
    };
    
    const handleAction = (action: string, item: any) => {
        setCurrentItem(item);
        switch (action) {
            case 'view':
                router.get(route('projects.show', item.id));
                break;
            case 'edit':
                setFormMode('edit');
                setIsFormModalOpen(true);
                break;
            case 'delete':
                setDeleteSummary(null);
                setIsDeleteModalOpen(true);
                axios.get(route('projects.deletion-summary', item.id))
                    .then(({ data }) => setDeleteSummary(data))
                    .catch(() => setDeleteSummary(null));
                break;
        }
    };
    
    const handleAddNew = () => {
        setCurrentItem(null);
        setFormMode('create');
        setIsFormModalOpen(true);
    };
    
    const handleFormSubmit = (formData: any) => {
        
        if (formMode === 'create') {
            toast.loading('Creating project...');
            router.post(route('projects.store'), formData, {
                onSuccess: () => {
                    console.log('SUCCESS');
                    setIsFormModalOpen(false);
                    toast.dismiss();
                    if (flash?.success) {
                        toast.success(flash.success);
                    }
                },
                onError: (errors) => {
                    console.log('ERROR:', errors);
                    toast.dismiss();
                    if (errors?.error) {
                        toast.error(errors.error);
                    } else {
                        const errorMessages = Object.values(errors).flat();
                        if (errorMessages.length > 0) {
                            toast.error(errorMessages[0] as string);
                        }
                    }
                },
                onBefore: () => {
                    console.log('BEFORE REQUEST');
                },
                onStart: () => {
                    console.log('START REQUEST');
                },
                onFinish: () => {
                    console.log('FINISH REQUEST');
                }
            });
        } else if (formMode === 'edit') {
            toast.loading('Updating project...');
            router.put(route('projects.update', currentItem.id), formData, {
                onSuccess: () => {
                    setIsFormModalOpen(false);
                    toast.dismiss();
                    if (flash?.success) {
                        toast.success(flash.success);
                    }
                    router.get(route('projects.index'));
                },
                onError: (errors) => {
                    toast.dismiss();
                    if (flash?.error) {
                        toast.error(flash.error);
                    } else {
                        toast.error(`Failed to update project: ${Object.values(errors).join(', ')}`);
                    }
                }
            });
        }
    };
    
    const handleDeleteConfirm = () => {
        toast.loading('Deleting project...');
        router.delete(route('projects.destroy', currentItem.id), {
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
                    toast.error(`Failed to delete project: ${Object.values(errors).join(', ')}`);
                }
            }
        });
    };
    
    // The search box shows its own value, so only the hidden (popover) filters count here.
    const activeFilterCount = () => {
        return (selectedStatus !== 'all' ? 1 : 0) + (selectedPriority !== 'all' ? 1 : 0) + (overdueOnly ? 1 : 0);
    };
    const hasActiveFilters = () => activeFilterCount() > 0;

    const handleResetFilters = () => {
        setSelectedStatus('all');
        setSelectedPriority('all');
        const params: any = { page: 1, view: activeView };
        if (searchTerm) params.search = searchTerm;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        navigate(params);
    };

    const getStatusColor = (status: string) => {
        const colors = {
            planning: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            active: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            on_hold: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            completed: 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
            cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getPriorityColor = (priority: string) => {
        const colors = {
            low: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            medium: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            high: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20',
            urgent: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[priority as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    // A missing date used to render as 1970-01-01 (new Date(null)).
    const formatDate = (value?: string | null) => value ? window.appSettings.formatDateTime(new Date(value), false) : '';

    const isOverdue = (project: any) => {
        if (!project.deadline || ['completed', 'cancelled'].includes(project.status)) return false;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return new Date(project.deadline) < today;
    };

    const pageActions = [];

    // Get user workspace role from props
    const userWorkspaceRole = (usePage().props as any).userWorkspaceRole;
    
    // Export - for owners and managers (members and clients don't report on the portfolio)
    if (hasPermission(permissions, 'project_view_any') && !['client', 'member'].includes(userWorkspaceRole)) {
        pageActions.push({
            label: t('Export'),
            icon: <FileDown className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: async () => {
                try {
                    // route()'s second argument is Ziggy route params, not a
                    // query string — it expects a plain object, not a
                    // URLSearchParams instance (whose entries aren't visible
                    // via Object.keys/enumeration the way Ziggy reads params).
                    const params: Record<string, string> = {};
                    if (searchTerm) params.search = searchTerm;
                    if (selectedStatus !== 'all') params.status = selectedStatus;
                    if (selectedPriority !== 'all') params.priority = selectedPriority;

                    const response = await fetch(route('projects.export', params), {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) {
                        // The failure reason (permission error, no workspace,
                        // an export-package exception) matters more than a
                        // flat "Export failed" — surface whatever the server
                        // actually sent back instead of hiding it.
                        let message = t('Export failed');
                        try {
                            const errJson = await response.clone().json();
                            message = errJson.error || errJson.message || message;
                        } catch {
                            const text = await response.text();
                            if (text) message = text.replace(/<[^>]*>/g, '').trim().slice(0, 200) || message;
                        }
                        toast.error(message);
                        return;
                    }
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `projects_export_${new Date().toISOString().split('T')[0]}.xlsx`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    toast.success(t('Export completed successfully'));
                } catch (error) {
                    toast.error(t('Export failed'));
                }
            }
        });
    }
    
    // AI Generate is an add-on module: hidden when the plan locks it, by the
    // same rule (CheckModuleAccess) that blocks its endpoints.
    const canUseAiGenerate = hasPermission(permissions, 'project_create') && !auth?.modulesLocked;

    if (hasPermission(permissions, 'project_create')) {
        if (canUseAiGenerate) {
            pageActions.push({
                label: t('AI Generate'),
                icon: <Sparkles className="h-4 w-4 mr-2" />,
                variant: 'outline',
                onClick: () => setIsAiModalOpen(true)
            });
        }
        pageActions.push({
            label: t('Add Project'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: handleAddNew
        });
    }
    
    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Projects') }
    ];

    // CrudTable configuration
    const columns = [
        {
            key: 'title',
            label: t('Project'),
            sortable: true,
            render: (value: string, row: any) => (
                <div>
                    <div 
                        className="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600 transition-colors"
                        onClick={() => router.get(route('projects.show', row.id))}
                    >
                        {value}
                    </div>
                    <div className="text-sm text-gray-500 truncate max-w-xs">{row.description}</div>
                </div>
            )
        },
        {
            key: 'status',
            label: t('Status'),
            render: (value: string) => (
                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(value)}`}>
                    {formatText(value)}
                </span>
            )
        },
        {
            key: 'priority',
            label: t('Priority'),
            sortable: true,
            render: (value: string) => (
                <span className={`inline-flex items-center capitalize rounded-md px-2 py-1 text-xs font-medium ${getPriorityColor(value)}`}>
                    {value}
                </span>
            )
        },
        {
            key: 'progress',
            label: t('Progress'),
            render: (value: number) => (
                <div className="flex items-center">
                    <div className="w-16 bg-gray-200 rounded-full h-2 mr-2">
                        <div className="bg-blue-600 h-2 rounded-full" style={{width: `${value}%`}}></div>
                    </div>
                    <span className="text-sm text-gray-900">{value}%</span>
                </div>
            )
        },
        {
            key: 'members',
            label: t('Team'),
            render: (value: any[]) => (
                <div className="flex -space-x-1">
                    {value?.slice(0, 3).map((member: any, index: number) => (
                        <Tooltip key={index}>
                            <TooltipTrigger asChild>
                                <Avatar className="h-6 w-6 border-2 border-white cursor-pointer">
                                    <AvatarImage src={member.user?.avatar} />
                                    <AvatarFallback className="text-xs">
                                        {member.user?.name?.charAt(0)}
                                    </AvatarFallback>
                                </Avatar>
                            </TooltipTrigger>
                            <TooltipContent>
                                {member.user?.name}
                            </TooltipContent>
                        </Tooltip>
                    ))}
                    {value?.length > 3 && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <div className="h-6 w-6 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs cursor-pointer">
                                    +{value.length - 3}
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                {value.slice(3).map((m: any) => m.user?.name).join(', ')}
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>
            )
        },
        {
            key: 'deadline',
            label: t('Deadline'),
            sortable: true,
            render: (value: string, row: any) => value ? (
                <span className={isOverdue(row) ? 'font-medium text-red-600' : ''}>{formatDate(value)}</span>
            ) : <span className="text-muted-foreground">—</span>
        }
    ];

    const actions = [
        {
            label: t('View'),
            icon: 'Eye',
            action: 'view',
            className: 'text-blue-500 hover:text-blue-700',
            condition: () => hasPermission(permissions, 'project_view')
        },
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500 hover:text-amber-700',
            condition: () => hasPermission(permissions, 'project_update')
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500 hover:text-red-700',
            condition: () => hasPermission(permissions, 'project_delete')
        }
    ];
    
    return (
        <PageTemplate 
            title={t('Projects')} 
            url="/projects"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Headline numbers - each one filters the list */}
            <StatStrip
                className="mb-4"
                items={[
                    { key: 'all', label: t('All projects'), value: stats.total ?? 0, active: selectedStatus === 'all' && !overdueOnly, onClick: () => showStatus('all') },
                    { key: 'planning', label: t('Planning'), value: stats.planning ?? 0, tone: 'info', active: selectedStatus === 'planning' && !overdueOnly, onClick: () => showStatus('planning') },
                    { key: 'active', label: t('Active'), value: stats.active ?? 0, tone: 'success', active: selectedStatus === 'active' && !overdueOnly, onClick: () => showStatus('active') },
                    { key: 'completed', label: t('Completed'), value: stats.completed ?? 0, tone: 'violet', active: selectedStatus === 'completed' && !overdueOnly, onClick: () => showStatus('completed') },
                    { key: 'overdue', label: t('Overdue'), value: stats.overdue ?? 0, tone: 'danger', active: overdueOnly, onClick: showOverdue },
                ]}
            />

            {/* Toolbar: search, the rest of the filters behind one button, view switch */}
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <form onSubmit={handleSearch} className="relative w-full sm:w-72">
                    <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        placeholder={t('Search projects...')}
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="h-9 w-full pl-9"
                    />
                </form>

                <FilterPopover activeCount={activeFilterCount()} onReset={handleResetFilters}>
                        <div className="space-y-1.5">
                            <Label className="text-xs">{t('Status')}</Label>
                            <Select value={selectedStatus} onValueChange={handleStatusFilter}>
                                <SelectTrigger className="h-9 w-full">
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
                        <div className="space-y-1.5">
                            <Label className="text-xs">{t('Priority')}</Label>
                            <Select value={selectedPriority} onValueChange={handlePriorityFilter}>
                                <SelectTrigger className="h-9 w-full">
                                    <SelectValue placeholder={t('All Priority')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('All Priority')}</SelectItem>
                                    <SelectItem value="low">{t('Low')}</SelectItem>
                                    <SelectItem value="medium">{t('Medium')}</SelectItem>
                                    <SelectItem value="high">{t('High')}</SelectItem>
                                    <SelectItem value="urgent">{t('Urgent')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                </FilterPopover>

                <div className="ml-auto flex rounded-md border p-0.5">
                    <Button
                        size="sm"
                        variant={activeView === 'grid' ? 'secondary' : 'ghost'}
                        className="h-7 px-2"
                        onClick={() => handleViewChange('grid')}
                        aria-label={t('Grid view')}
                        title={t('Grid view')}
                    >
                        <LayoutGrid className="h-4 w-4" />
                    </Button>
                    <Button
                        size="sm"
                        variant={activeView === 'list' ? 'secondary' : 'ghost'}
                        className="h-7 px-2"
                        onClick={() => handleViewChange('list')}
                        aria-label={t('List view')}
                        title={t('List view')}
                    >
                        <List className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Projects Content */}
            {(projects?.data?.length === 0) && (
                <EmptyState
                    {...EMPTY_STATES.projects}
                    // View-only users can't create projects, so don't tell them to
                    {...(!hasPermission(permissions, 'project_create') && {
                        description: "You haven't been added to any projects yet.",
                    })}
                    action={hasPermission(permissions, 'project_create') ? { label: 'Create Your First Project', onClick: handleAddNew, icon: <Plus className="h-4 w-4" /> } : undefined}
                    secondaryAction={canUseAiGenerate ? { label: 'AI Generate', onClick: () => setIsAiModalOpen(true) } : undefined}
                />
            )}
            {(activeView === 'grid' || !activeView) && (projects?.data?.length ?? 0) > 0 ? (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                {projects?.data?.map((project: any) => (
                    <ProjectCard
                        key={project.id}
                        project={project}
                        canView={hasPermission(permissions, 'project_view')}
                        canEdit={hasPermission(permissions, 'project_update')}
                        canDelete={hasPermission(permissions, 'project_delete')}
                        showClient={userWorkspaceRole !== 'client'}
                        onAction={handleAction}
                    />
                ))}
                </div>
            ) : (projects?.data?.length ?? 0) > 0 && (
                <div className="overflow-hidden rounded-lg border bg-card">
                    <CrudTable
                        columns={columns}
                        actions={actions}
                        data={projects?.data || []}
                        from={projects?.from || 1}
                        onAction={handleAction}
                        sortField={pageFilters.sort_field}
                        sortDirection={pageFilters.sort_direction}
                        onSort={handleSort}
                        permissions={permissions}
                    />
                </div>
            )}

            {/* Pagination - only once there is more than one page's worth of projects */}
            {(projects?.total ?? 0) > 12 && (
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div className="flex items-center gap-3 text-muted-foreground">
                        <span>
                            {t('Showing')} {projects?.from || 0}–{projects?.to || 0} {t('of')} {projects?.total || 0}
                        </span>
                        <Select
                            value={pageFilters.per_page?.toString() || '12'}
                            onValueChange={(value) => navigate(buildParams({ page: 1, per_page: parseInt(value) }))}
                        >
                            <SelectTrigger className="h-8 w-[4.5rem]" aria-label={t('Per Page')}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="12">12</SelectItem>
                                <SelectItem value="24">24</SelectItem>
                                <SelectItem value="48">48</SelectItem>
                                <SelectItem value="100">100</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {(projects?.last_page ?? 1) > 1 && (
                        <div className="flex flex-wrap gap-1">
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
                                        onClick={() => {
                                            if (!link.url) return;
                                            const pageNum = new URL(link.url).searchParams.get('page');
                                            navigate(buildParams({ page: pageNum ? parseInt(pageNum) : 1 }));
                                        }}
                                    >
                                        {isTextLink ? t(label) : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                    </Button>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
            {/* Form Modal */}
            <CrudFormModal
                isOpen={isFormModalOpen}
                onClose={() => setIsFormModalOpen(false)}
                onSubmit={handleFormSubmit}
                submitButtonText={formMode === 'create' ? t('Create') : t('Update')}
                formConfig={{
                    fields: [
                        { name: 'title', label: t('Project Title'), type: 'text', required: true, placeholder: t('Enter project title') },
                        { name: 'description', label: t('Description'), type: 'textarea', placeholder: t('Enter project description') },
                        { 
                            name: 'status', 
                            label: t('Status'), 
                            type: 'select',
                            options: [
                                { value: 'planning', label: 'Planning' },
                                { value: 'active', label: 'Active' },
                                { value: 'on_hold', label: 'On Hold' },
                                { value: 'completed', label: 'Completed' },
                                { value: 'cancelled', label: 'Cancelled' }
                            ]
                        },
                        { 
                            name: 'priority', 
                            label: t('Priority'), 
                            type: 'select',
                            options: [
                                { value: 'low', label: 'Low' },
                                { value: 'medium', label: 'Medium' },
                                { value: 'high', label: 'High' },
                                { value: 'urgent', label: 'Urgent' }
                            ]
                        },
                        { name: 'start_date', label: t('Start Date'), type: 'date' },
                        { name: 'deadline', label: t('Deadline'), type: 'date' },
                        { name: 'estimated_hours', label: t('Estimated Hours'), type: 'number', min: 0, placeholder: t('e.g. 40') },
                        { name: 'is_public', label: '', type: 'checkbox', placeholder: t('Make project public') }
                    ],
                    modalSize: 'xl'
                }}
                initialData={currentItem || {
                    status: 'planning',
                    priority: 'medium',
                    is_public: false
                }}
                title={
                    formMode === 'create' 
                        ? t('Add Project') 
                        : formMode === 'edit' 
                            ? t('Edit Project') 
                            : t('View Project')
                }
                mode={formMode}
            />

            {/* Delete Modal */}
            <EnhancedDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentItem?.title || ''}
                entityName={t('project')}
                confirmDisabled={!deleteSummary || deleteSummary.blocked}
                warningMessage={
                    !deleteSummary
                        ? t('Checking linked records...')
                        : deleteSummary.blocked
                            ? t('This project has :count invoice(s). Delete them or move them to another project before deleting the project.').replace(':count', String(deleteSummary.counts.invoices))
                            : t('All project data will be permanently lost. This action cannot be undone.')
                }
                additionalInfo={
                    deleteSummary && !deleteSummary.blocked
                        ? ([
                            ['tasks', t('tasks')],
                            ['bugs', t('bugs')],
                            ['milestones', t('milestones')],
                            ['budgets', t('budgets')],
                            ['expenses', t('expenses')],
                            ['timesheet_entries', t('timesheet entries')],
                            ['attachments', t('attachments')],
                            ['notes', t('notes')],
                        ] as const)
                            .filter(([key]) => (deleteSummary.counts[key] ?? 0) > 0)
                            .map(([key, label]) => `${deleteSummary.counts[key]} ${label}`)
                        : []
                }
            />
            
            {/* AI Project Generator */}
            <AiProjectModal open={isAiModalOpen} onClose={() => setIsAiModalOpen(false)} />
        </PageTemplate>
    );
}