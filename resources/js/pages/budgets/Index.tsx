import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Filter, Eye, Edit, Trash2, LayoutGrid, List, DollarSign, AlertTriangle } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudTable } from '@/components/CrudTable';
import { hasPermission } from '@/utils/authorization';
import BudgetFormModal from '@/components/budgets/BudgetFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { useTranslation } from 'react-i18next';

interface Budget {
    id: number;
    project: {
        id: number;
        title: string;
    };
    total_budget: number;
    currency: string;
    period_type: string;
    status: string;
    total_spent: number;
    remaining_budget: number;
    utilization_percentage: number;
    categories: Array<{
        id: number;
        name: string;
        allocated_amount: number;
        color: string;
        total_spent: number;
        utilization_percentage: number;
    }>;
    created_at: string;
}

export default function BudgetIndex() {
    const { t } = useTranslation();
    const { budgets, auth, userWorkspaceRole, flash, permissions: pagePermissions, allProjects, filters: pageFilters = {} } = usePage().props as any;
    const budgetPermissions = pagePermissions;
    
    const formatText = (text: string) => {
        return text.replace(/_/g, ' ').split(' ').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    };

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        if (flash?.info) {
            toast.info(flash.info);
        }
    }, [flash]);

    const [activeView, setActiveView] = useState(pageFilters.view || 'grid');
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [selectedProject, setSelectedProject] = useState(pageFilters.project_id || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [isFormModalOpen, setIsFormModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentBudget, setCurrentBudget] = useState<Budget | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');

    const buildParams = (
        overrides: Record<string, any> = {},
        stateOverrides: { search?: string; status?: string; project?: string; view?: string } = {}
    ) => {
        const view    = stateOverrides.view    !== undefined ? stateOverrides.view    : activeView;
        const search  = stateOverrides.search  !== undefined ? stateOverrides.search  : searchTerm;
        const status  = stateOverrides.status  !== undefined ? stateOverrides.status  : selectedStatus;
        const project = stateOverrides.project !== undefined ? stateOverrides.project : selectedProject;

        const params: any = { page: 1, view };
        if (search) params.search = search;
        if (status !== 'all') params.status = status;
        if (project !== 'all') params.project_id = project;
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        if (pageFilters.sort_by) params.sort_by = pageFilters.sort_by;
        if (pageFilters.sort_order) params.sort_order = pageFilters.sort_order;
        return { ...params, ...overrides };
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('budgets.index'), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const applyFilters = () => {
        router.get(route('budgets.index'), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
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
        const params: any = { page: 1, view: activeView };
        if (pageFilters.per_page) params.per_page = pageFilters.per_page;
        router.get(route('budgets.index'), params, { preserveState: false, preserveScroll: false });
    };

    const handleSort = (field: string) => {
        const direction = pageFilters.sort_by === field && pageFilters.sort_order === 'asc' ? 'desc' : 'asc';
        router.get(route('budgets.index'), buildParams({ sort_by: field, sort_order: direction, page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const handleAction = (action: string, budgetOrId: Budget | number) => {
        let budget: Budget;
        
        if (typeof budgetOrId === 'number') {
            // Called from CrudTable with ID
            budget = budgets?.data?.find((b: Budget) => b.id === budgetOrId);
            if (!budget) return;
        } else {
            // Called from grid view with budget object
            budget = budgetOrId;
        }
        
        setCurrentBudget(budget);
        switch (action) {
            case 'view':
                router.get(route('budgets.show', budget.id));
                break;
            case 'edit':
                setModalMode('edit');
                setIsFormModalOpen(true);
                break;
            case 'delete':
                setIsDeleteModalOpen(true);
                break;
        }
    };

    const handleAddNew = () => {
        setCurrentBudget(null);
        setModalMode('create');
        setIsFormModalOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (currentBudget) {
            toast.loading(t('Deleting budget...'));
            router.delete(route('budgets.destroy', currentBudget.id), {
                onSuccess: () => {
                    toast.dismiss();
                    setIsDeleteModalOpen(false);
                },
                onError: () => {
                    toast.dismiss();
                    toast.error(t('Failed to delete budget'));
                    setIsDeleteModalOpen(false);
                }
            });
        }
    };

    const getStatusColor = (status: string) => {
        const colors = {
            active: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            completed: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getUtilizationColor = (percentage: number) => {
        if (percentage >= 90) return 'text-red-600';
        if (percentage >= 75) return 'text-yellow-600';
        return 'text-green-600';
    };

    const formatCurrency = (amount: string | number) => {
        if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
            const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
            return window.appSettings.formatCurrency(numericAmount);
        }
        return amount || 0;
    };

    const pageActions = [];

    if (budgetPermissions?.create) {
        pageActions.push({
            label: t('Create Budget'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: handleAddNew
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Budget & Expenses'), href: route('budgets.dashboard') },
        { title: t('Budgets') }
    ];

    return (
        <PageTemplate
            title={t('Budget')}
            url="/budgets"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Search and filters */}
            <div className="bg-white rounded-lg border shadow mb-4">
                <div className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search budgets...')}
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

                        <div className="flex items-center gap-2">
                            <div className="border rounded-md p-0.5">
                                <Button
                                    size="sm"
                                    variant={activeView === 'list' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => { setActiveView('list'); router.get(route('budgets.index'), buildParams({ page: 1, view: 'list' }, { view: 'list' }), { preserveState: false, preserveScroll: false }); }}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant={activeView === 'grid' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => { setActiveView('grid'); router.get(route('budgets.index'), buildParams({ page: 1, view: 'grid' }, { view: 'grid' }), { preserveState: false, preserveScroll: false }); }}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>

                            <Label className="text-xs text-muted-foreground">{t('Per Page:')}</Label>
                            <Select
                                value={budgets?.per_page?.toString() || "12"}
                                onValueChange={(value) => {
                                    router.get(route('budgets.index'), buildParams({ page: 1, per_page: parseInt(value) }), { preserveState: false, preserveScroll: false });
                                }}
                            >
                                <SelectTrigger className="w-16 h-8">
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
                    </div>

                    {showFilters && (
                        <div className="w-full mt-3 p-4 bg-gray-50 border rounded-md">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <Label>{t('Status')}</Label>
                                    <Select value={selectedStatus} onValueChange={(value) => {
                                        setSelectedStatus(value);
                                        router.get(route('budgets.index'), buildParams({ page: 1 }, { status: value }), { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Status')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Status')}</SelectItem>
                                            <SelectItem value="active">{t('Active')}</SelectItem>
                                            <SelectItem value="completed">{t('Completed')}</SelectItem>
                                            <SelectItem value="cancelled">{t('Cancelled')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('Project')}</Label>
                                    <Select value={selectedProject} onValueChange={(value) => {
                                        setSelectedProject(value);
                                        router.get(route('budgets.index'), buildParams({ page: 1 }, { project: value }), { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder="All Projects" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Projects')}</SelectItem>
                                            {allProjects?.map((project: any) => (
                                                <SelectItem key={project.id} value={project.id.toString()}>
                                                    {project.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <Button variant="outline" size="sm" className="h-9" onClick={handleResetFilters} disabled={!hasActiveFilters()}>
                                    {t('Reset Filters')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Budget Content */}
            {activeView === 'grid' ? (
                budgets?.data?.length === 0 ? (
                <div className="bg-white rounded-lg shadow p-8 text-center">
                    <DollarSign className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p className="text-gray-500 mb-4">{t('No budgets found')}</p>
                    {budgetPermissions?.create && (
                        <Button onClick={handleAddNew}>
                            <Plus className="h-4 w-4 mr-2" />
                            {t('Create your first budget')}
                        </Button>
                    )}
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    {budgets?.data?.map((budget: Budget) => (
                        <Card key={budget.id} className="overflow-hidden hover:shadow-md transition-shadow flex flex-col">
                            {/* Header */}
                            <CardHeader className="pb-2">
                                <div className="flex justify-between items-start gap-2">
                                    <CardTitle
                                        className="text-base line-clamp-1 cursor-pointer hover:text-primary transition-colors"
                                        onClick={() => handleAction('view', budget)}
                                    >
                                        {budget.project?.title}
                                    </CardTitle>
                                    <span className={`inline-flex shrink-0 items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(budget.status)}`}>
                                        {formatText(budget.status)}
                                    </span>
                                </div>
                                <div className="flex items-center gap-1.5 mt-1">
                                    <span className="text-xs text-muted-foreground capitalize">
                                        {formatText(budget.period_type)} {t('budget')}
                                    </span>
                                    {(budget.utilization_percentage || 0) >= 90 && (
                                        <span className="flex items-center gap-1 text-xs text-red-600 font-medium">
                                            <AlertTriangle className="h-3 w-3" />{t('Critical')}
                                        </span>
                                    )}
                                    {(budget.utilization_percentage || 0) >= 75 && (budget.utilization_percentage || 0) < 90 && (
                                        <span className="flex items-center gap-1 text-xs text-yellow-600 font-medium">
                                            <AlertTriangle className="h-3 w-3" />{t('High Usage')}
                                        </span>
                                    )}
                                </div>
                            </CardHeader>

                            <CardContent className="py-2 flex-1">
                                <div className="space-y-3">
                                    {/* Amounts */}
                                    <div className="grid grid-cols-3 gap-2">
                                        <div className="text-center">
                                            <p className="text-xs text-muted-foreground">{t('Total')}</p>
                                            <p className="text-xs font-semibold text-gray-900 dark:text-white truncate">{formatCurrency(budget.total_budget)}</p>
                                        </div>
                                        <div className="text-center border-x border-gray-100 dark:border-gray-700">
                                            <p className="text-xs text-muted-foreground">{t('Spent')}</p>
                                            <p className="text-xs font-semibold text-orange-600 truncate">{formatCurrency(budget.total_spent)}</p>
                                        </div>
                                        <div className="text-center">
                                            <p className="text-xs text-muted-foreground">{t('Left')}</p>
                                            <p className={`text-xs font-semibold truncate ${(budget.remaining_budget || 0) < 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                {formatCurrency(budget.remaining_budget)}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Utilization */}
                                    <div className="space-y-1">
                                        <div className="flex justify-between text-xs">
                                            <span className="text-muted-foreground">{t('Utilization')}</span>
                                            <span className={`font-medium ${getUtilizationColor(budget.utilization_percentage || 0)}`}>
                                                {(budget.utilization_percentage || 0).toFixed(1)}%
                                            </span>
                                        </div>
                                        <Progress value={budget.utilization_percentage} className="h-1.5" />
                                    </div>

                                    {/* Categories */}
                                    <div className="flex items-center justify-between">
                                        <div className="flex -space-x-1">
                                            {budget.categories?.slice(0, 4).map((category, index) => (
                                                <Tooltip key={index}>
                                                    <TooltipTrigger asChild>
                                                        <div
                                                            className="w-6 h-6 rounded-full border-2 border-white flex items-center justify-center text-xs font-medium text-white cursor-default"
                                                            style={{ backgroundColor: category.color }}
                                                        >
                                                            {category.name.charAt(0)}
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p className="font-medium">{category.name}</p>
                                                        <p>{t('Allocated')}: {formatCurrency(category.allocated_amount)}</p>
                                                        <p>{t('Spent')}: {formatCurrency(category.total_spent)}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            ))}
                                            {budget.categories?.length > 4 && (
                                                <div className="w-6 h-6 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs text-gray-600">
                                                    +{budget.categories.length - 4}
                                                </div>
                                            )}
                                        </div>
                                        <span className="text-xs text-muted-foreground">
                                            {budget.categories?.length || 0} {t('categories')}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>

                            <CardFooter className="flex justify-between items-center pt-2 pb-2 border-t">
                                <span className="text-xs text-muted-foreground">
                                    {window.appSettings.formatDateTime(new Date(budget.created_at),false)}
                                </span>
                                <div className="flex gap-1">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button variant="ghost" size="icon" onClick={() => handleAction('view', budget)} className="text-blue-500 hover:text-blue-700 h-8 w-8">
                                                <Eye className="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>{t('View')}</TooltipContent>
                                    </Tooltip>
                                    {budgetPermissions?.update && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="ghost" size="icon" onClick={() => handleAction('edit', budget)} className="text-amber-500 hover:text-amber-700 h-8 w-8">
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{t('Edit')}</TooltipContent>
                                        </Tooltip>
                                    )}
                                    {budgetPermissions?.delete && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button variant="ghost" size="icon" onClick={() => handleAction('delete', budget)} className="text-red-500 hover:text-red-700 h-8 w-8">
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{t('Delete')}</TooltipContent>
                                        </Tooltip>
                                    )}
                                </div>
                            </CardFooter>
                        </Card>
                    ))}
                </div>
                )
            ) : (
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <CrudTable
                        columns={[
                            {
                                key: 'project.title',
                                label: t('Project'),
                                sortable: true,
                                render: (value: string, row: any) => (
                                    <div>
                                        <div 
                                            className="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600 transition-colors"
                                            onClick={() => router.get(route('budgets.show', row.id))}
                                        >
                                            {value}
                                        </div>
                                        {/* <div className="text-sm text-gray-500 capitalize">{row.period_type} {t('budget')}</div> */}
                                    </div>
                                )
                            },
                            {
                                key: 'period_type',
                                label: t('Period'),
                                sortable: true,
                                render: (value: string) => (
                                    <span className="text-sm capitalize">
                                        {formatText(value)} {t('budget')}
                                    </span>
                                )
                            },
                            {
                                key: 'total_budget',
                                label: t('Budget'),
                                sortable: true,
                                render: (value: number, row: any) => (
                                    <div>
                                        <div className="text-sm font-medium text-gray-900">
                                            {formatCurrency(value)}
                                        </div>
                                        <div className="text-sm text-gray-500">
                                            {t('Spent')}: {formatCurrency(row.total_spent)}
                                        </div>
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
                                key: 'utilization_percentage',
                                label: t('Utilization'),
                                render: (value: number) => (
                                    <div className="flex items-center">
                                        <div className="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                            <div
                                                className="bg-blue-600 h-2 rounded-full"
                                                style={{width: `${Math.min(value || 0, 100)}%`}}
                                            />
                                        </div>
                                        <span className={`text-sm ${getUtilizationColor(value || 0)}`}>
                                            {(value || 0).toFixed(1)}%
                                        </span>
                                    </div>
                                )
                            },
                            {
                                key: 'categories',
                                label: t('Categories'),
                                render: (value: any[], row: any) => (
                                    <div className="flex -space-x-1">
                                        {value?.slice(0, 4).map((category, index) => (
                                            <Tooltip key={index}>
                                                <TooltipTrigger asChild>
                                                    <div
                                                        className="w-6 h-6 rounded-full border-2 border-white flex items-center justify-center text-xs font-medium text-white"
                                                        style={{ backgroundColor: category.color }}
                                                    >
                                                        {category.name.charAt(0)}
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    {category.name}: {formatCurrency(category.allocated_amount)}
                                                </TooltipContent>
                                            </Tooltip>
                                        ))}
                                        {value?.length > 4 && (
                                            <div className="w-6 h-6 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs">
                                                +{value.length - 4}
                                            </div>
                                        )}
                                    </div>
                                )
                            }
                        ]}
                        actions={[
                            {
                                label: t('View'),
                                icon: 'Eye',
                                action: 'view',
                                className: 'text-blue-500 hover:text-blue-700',
                                condition: () => true
                            },
                            {
                                label: t('Edit'),
                                icon: 'Edit',
                                action: 'edit',
                                className: 'text-amber-500 hover:text-amber-700',
                                condition: () => budgetPermissions?.update
                            },
                            {
                                label: t('Delete'),
                                icon: 'Trash2',
                                action: 'delete',
                                className: 'text-red-500 hover:text-red-700',
                                condition: () => budgetPermissions?.delete
                            }
                        ]}
                        data={budgets?.data || []}
                        from={budgets?.from || 1}
                        onAction={handleAction}
                        sortField={pageFilters.sort_by}
                        sortDirection={pageFilters.sort_order}
                        onSort={handleSort}
                        permissions={auth?.permissions || []}
                    />
                    
                    {/* Pagination for list view */}
                    {budgets?.links && (
                        <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
                            <div className="text-sm text-muted-foreground">
                                {t('Showing')} <span className="font-medium">{budgets?.from || 0}</span> {t('to')} <span className="font-medium">{budgets?.to || 0}</span> {t('of')} <span className="font-medium">{budgets?.total || 0}</span> {t('budgets')}
                            </div>
                            
                            <div className="flex gap-1">
                                {budgets?.links?.map((link: any, i: number) => {
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
                                                router.get(route('budgets.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
                                            }}
                                        >
                                            {isTextLink ? t(label) : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                        </Button>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Pagination for grid view */}
            {activeView === 'grid' && budgets?.links && (
                <div className="mt-6 bg-[#F0F0F1] dark:bg-gray-800 p-4 rounded-lg shadow flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        {t('Showing')} <span className="font-medium">{budgets?.from || 0}</span> {t('to')} <span className="font-medium">{budgets?.to || 0}</span> {t('of')} <span className="font-medium">{budgets?.total || 0}</span> {t('budgets')}
                    </div>

                    <div className="flex gap-1">
                        {budgets?.links?.map((link: any, i: number) => {
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
                                        router.get(route('budgets.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
                                    }}
                                >
                                    {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                </Button>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Modals */}
            <BudgetFormModal
                isOpen={isFormModalOpen}
                onClose={() => {
                    setIsFormModalOpen(false);
                    setCurrentBudget(null);
                }}
                budget={currentBudget}
                mode={modalMode}
            />

            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentBudget?.project?.title || ''}
                entityName="budget"
            />
        </PageTemplate>
    );
}