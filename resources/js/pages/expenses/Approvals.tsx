import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Check, X, AlertCircle, Clock, Search, Filter, LayoutGrid, List, Receipt, CheckCircle, XCircle, DollarSign } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { CrudTable } from '@/components/CrudTable';
import { hasPermission } from '@/utils/authorization';

interface Props {
    expenses: any;
    stats: any;
    projects: any[];
    filters: any;
    permissions?: any;
}

export default function Approvals({ expenses, stats, projects, filters, permissions }: Props) {
    const { t } = useTranslation();
    const { flash, permissions: pagePermissions } = usePage().props as any;
    const approvalPermissions = permissions || pagePermissions;
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [selectedStatus, setSelectedStatus] = useState(filters?.status || 'all');
    const [selectedProject, setSelectedProject] = useState(filters?.project_id || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [viewMode, setViewMode] = useState(filters?.view || 'cards');
    
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
    }, [flash]);
    
    // Central param builder
    const buildParams = (
        overrides: Record<string, any> = {},
        stateOverrides: { search?: string; status?: string; project?: string; view?: string } = {}
    ) => {
        const view    = stateOverrides.view    !== undefined ? stateOverrides.view    : viewMode;
        const search  = stateOverrides.search  !== undefined ? stateOverrides.search  : searchTerm;
        const status  = stateOverrides.status  !== undefined ? stateOverrides.status  : selectedStatus;
        const project = stateOverrides.project !== undefined ? stateOverrides.project : selectedProject;

        const params: any = { page: 1, view };
        if (search) params.search = search;
        if (status !== 'all') params.status = status;
        if (project !== 'all') params.project_id = project;
        if (filters?.per_page) params.per_page = filters.per_page;
        if (filters?.sort_by) params.sort_by = filters.sort_by;
        if (filters?.sort_order) params.sort_order = filters.sort_order;
        return { ...params, ...overrides };
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('expense-approvals.index'), buildParams({ page: 1 }), { preserveState: false });
    };
    
    const applyFilters = () => {
        router.get(route('expense-approvals.index'), buildParams({ page: 1 }), { preserveState: false });
    };
    
    // Add sorting functionality
    const handleSort = (field: string) => {
        const direction = filters?.sort_by === field && filters?.sort_order === 'asc' ? 'desc' : 'asc';
        router.get(route('expense-approvals.index'), buildParams({ sort_by: field, sort_order: direction, page: 1 }), { preserveState: false });
    };
    
    const resetFilters = () => {
        setSearchTerm('');
        setSelectedStatus('all');
        setSelectedProject('all');
        const params: any = { page: 1, view: viewMode };
        if (filters?.per_page) params.per_page = filters.per_page;
        router.get(route('expense-approvals.index'), params, { preserveState: false });
    };
    
    const hasActiveFilters = () => {
        return searchTerm || selectedStatus !== 'all' || selectedProject !== 'all';
    };
    // Handle actions for CrudTable
    const handleAction = (action: string, expenseId: number) => {
        processApproval(expenseId, action);
    };
    
    const processApproval = (expenseId: number, action: string) => {
        const actionText = action === 'approve' ? 'Approving' : action === 'reject' ? 'Rejecting' : 'Processing';
        toast.loading(`${actionText} expense...`);
        
        const routeName = action === 'approve' ? 'expense-approvals.approve' : 
                         action === 'reject' ? 'expense-approvals.reject' : 
                         'expense-approvals.request-info';
        
        const data: any = {};
        
        // For rejection, we can provide a default note or leave it empty
        if (action === 'reject') {
            data.notes = 'Expense rejected by approver';
        } else if (action === 'request_info') {
            data.notes = 'Additional information required';
        } else {
            data.notes = '';
        }
        
        router.post(route(routeName, expenseId), data, {
            onSuccess: () => {
                toast.dismiss();
            },
            onError: (errors) => {
                toast.dismiss();
                console.error('Expense approval error:', errors);
                
                // Show specific error message if available
                const errorMessage = errors?.message || `Failed to ${action} expense`;
                toast.error(errorMessage);
            }
        });
    };



    const breadcrumbs = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: t('Budget & Expenses'), href: route('budgets.dashboard') },
        { title: 'Expense Approvals' }
    ];

    return (
        <PageTemplate title={t('Expense Approvals')} breadcrumbs={breadcrumbs} noPadding>
            <div className="space-y-6">
                {/* Overview Row */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Pending Approval */}
                    <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                        <div className="h-10 w-10 rounded-lg bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center shrink-0">
                            <Clock className="h-5 w-5 text-yellow-600 dark:text-yellow-100" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Pending Approval</p>
                            <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">{stats.pending_count}</p>
                            <p className="text-xs text-gray-400 dark:text-gray-500">Awaiting Review</p>
                        </div>
                    </div>

                    {/* Requires Info */}
                    <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                        <div className="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center shrink-0">
                            <AlertCircle className="h-5 w-5 text-blue-600 dark:text-blue-100" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Requires Info</p>
                            <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">{stats.requires_info_count}</p>
                            <p className="text-xs text-gray-400 dark:text-gray-500">Needs Attention</p>
                        </div>
                    </div>

                    {/* Approved Today */}
                    <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                        <div className="h-10 w-10 rounded-lg bg-green-100 dark:bg-green-900 flex items-center justify-center shrink-0">
                            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-100" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Approved Today</p>
                            <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">{stats.approved_today}</p>
                            <p className="text-xs text-gray-400 dark:text-gray-500">Processed</p>
                        </div>
                    </div>

                    {/* Pending Amount */}
                    <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                        <div className="h-10 w-10 rounded-lg bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center shrink-0">
                            <DollarSign className="h-5 w-5 text-indigo-600 dark:text-indigo-100" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Pending Amount</p>
                            <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">{formatCurrency(stats.pending_amount || 0)}</p>
                            <p className="text-xs text-gray-400 dark:text-gray-500">Total Value</p>
                        </div>
                    </div>
                </div>

                {/* Filters Row */}
                <div className="bg-white rounded-lg border shadow">
                    <div className="p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <form onSubmit={handleSearch} className="flex gap-2">
                                    <div className="relative w-full sm:w-64">
                                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                                        <Input
                                            placeholder="Search expenses..."
                                            value={searchTerm}
                                            onChange={(e) => {
                                                setSearchTerm(e.target.value);
                                                clearTimeout(window.searchTimeout);
                                                window.searchTimeout = setTimeout(() => {
                                                    router.get(route('expense-approvals.index'), buildParams({ page: 1 }, { search: e.target.value }), { preserveState: false });
                                                }, 500);
                                            }}
                                            className="pl-9"
                                        />
                                    </div>
                                    <Button type="submit" size="sm">
                                        <Search className="h-4 w-4 mr-1.5" />
                                        Search
                                    </Button>
                                </form>
                                
                                <Button 
                                    variant={hasActiveFilters() ? "default" : "outline"}
                                    size="sm"
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter className="h-4 w-4 mr-1.5" />
                                    Filters
                                    {hasActiveFilters() && (
                                        <span className="ml-1 bg-primary-foreground text-primary rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                            {(searchTerm ? 1 : 0) + (selectedStatus !== 'all' ? 1 : 0) + (selectedProject !== 'all' ? 1 : 0)}
                                        </span>
                                    )}
                                </Button>
                            </div>
                            
                            <div className="flex items-center gap-2">
                                <div className="border rounded-md p-0.5">
                                    <Button 
                                        size="sm" 
                                        variant={viewMode === 'cards' ? "default" : "ghost"}
                                        className="h-7 px-2"
                                        onClick={() => { setViewMode('cards'); router.get(route('expense-approvals.index'), buildParams({ page: 1, view: 'cards' }, { view: 'cards' }), { preserveState: false }); }}
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </Button>
                                    <Button 
                                        size="sm" 
                                        variant={viewMode === 'table' ? "default" : "ghost"}
                                        className="h-7 px-2"
                                        onClick={() => { setViewMode('table'); router.get(route('expense-approvals.index'), buildParams({ page: 1, view: 'table' }, { view: 'table' }), { preserveState: false }); }}
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                </div>
                                <Label className="text-xs text-muted-foreground">Per Page:</Label>
                                <Select value={expenses.per_page?.toString() || filters?.per_page?.toString() || "12"} onValueChange={(value) => {
                                    router.get(route('expense-approvals.index'), buildParams({ page: 1, per_page: parseInt(value) }), { preserveState: false });
                                }}>
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
                            <div className="p-4 bg-gray-50 rounded-md">
                                <div className="flex gap-4 items-end">
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Status</label>
                                        <Select value={selectedStatus} onValueChange={(value) => {
                                            setSelectedStatus(value);
                                            router.get(route('expense-approvals.index'), buildParams({ page: 1 }, { status: value }), { preserveState: false });
                                        }}>
                                            <SelectTrigger className="w-40">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All</SelectItem>
                                                <SelectItem value="pending">Pending</SelectItem>
                                                <SelectItem value="approved">Approved</SelectItem>
                                                <SelectItem value="rejected">Rejected</SelectItem>
                                                <SelectItem value="requires_info">Requires Info</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    <div className="space-y-2">
                                        <label className="text-sm font-medium">Project</label>
                                        <Select value={selectedProject} onValueChange={(value) => {
                                            setSelectedProject(value);
                                            router.get(route('expense-approvals.index'), buildParams({ page: 1 }, { project: value }), { preserveState: false });
                                        }}>
                                            <SelectTrigger className="w-48">
                                                <SelectValue placeholder="All Projects" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All</SelectItem>
                                                {projects?.map((project: any) => (
                                                    <SelectItem key={project.id} value={project.id.toString()}>
                                                        {project.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    <Button variant="outline" size="sm" onClick={resetFilters} disabled={!hasActiveFilters()}>
                                        Reset Filters
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
                
                {expenses && expenses.data && expenses.data.length > 0 ? (
                    viewMode === 'cards' ? (
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                {expenses.data.map((expense: any) => (
                                    <Card key={expense.id} className="hover:shadow-lg transition-shadow flex flex-col">
                                        <CardHeader className="pb-4">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <CardTitle
                                                        className="text-lg mb-2 cursor-pointer hover:text-blue-600 transition-colors"
                                                        onClick={() => router.get(route('expenses.show', expense.id))}
                                                    >
                                                        {expense.title}
                                                    </CardTitle>
                                                    <div className="text-sm text-gray-500 mb-2">
                                                        <span className="font-medium">{expense.project?.title}</span>
                                                    </div>
                                                    <div className="text-xs text-gray-400">
                                                        Submitted by {expense.submitter?.name} • {window.appSettings.formatDateTime(new Date(expense.expense_date),false)}
                                                    </div>
                                                </div>
                                                <div className="text-right ml-4">
                                                    <div className="text-lg font-bold text-gray-900 mb-2">
                                                        {formatCurrency(expense.amount)}
                                                    </div>
                                                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                                                        expense.status === 'requires_info' ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20' : 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20'
                                                    }`}>
                                                        {formatText(expense.status)}
                                                    </span>
                                                </div>
                                            </div>
                                        </CardHeader>
                                        
                                        <CardContent className="pt-0 space-y-4 flex-1 flex flex-col">
                                            <div className="flex-1">
                                                {expense.description && (
                                                    <div>
                                                        <p className="text-sm text-gray-600 line-clamp-2">{expense.description}</p>
                                                    </div>
                                                )}
                                                
                                                {expense.budget_category && (
                                                    <div className="mt-2">
                                                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            {expense.budget_category.name}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex gap-1 mt-auto">
                                                {approvalPermissions?.approve && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                onClick={() => processApproval(expense.id, 'approve')}
                                                                className="h-8 w-8 text-green-600 hover:text-green-700 hover:bg-green-50"
                                                            >
                                                                <Check className="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Approve</TooltipContent>
                                                    </Tooltip>
                                                )}
                                                {approvalPermissions?.reject && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                onClick={() => processApproval(expense.id, 'reject')}
                                                                className="h-8 w-8 text-red-600 hover:text-red-700 hover:bg-red-50"
                                                            >
                                                                <X className="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Reject</TooltipContent>
                                                    </Tooltip>
                                                )}
                                                {approvalPermissions?.request_info && expense.status === 'pending' && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                onClick={() => processApproval(expense.id, 'request_info')}
                                                                className="h-8 w-8 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                            >
                                                                <AlertCircle className="h-4 w-4" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Request additional information</TooltipContent>
                                                    </Tooltip>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                            
                            {/* Pagination for cards */}
                            <div className="bg-[#F0F0F1] dark:bg-gray-800 rounded-lg shadow p-4 flex items-center justify-between">
                                <div className="text-sm text-gray-600">
                                    Showing {expenses.from} to {expenses.to} of {expenses.total} expenses
                                </div>
                                
                                <div className="flex gap-1">
                                    {expenses.links?.map((link: any, i: number) => {
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
                                                router.get(route('expense-approvals.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false });
                                            }}
                                            >
                                                {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-white rounded-lg shadow overflow-hidden">
                            <CrudTable
                            columns={[
                                {
                                    key: 'title',
                                    label: t('Expense'),
                                    sortable: true,
                                    render: (value: string, row: any) => (
                                        <div>
                                            <div
                                                className="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600 hover:underline"
                                                onClick={() => router.get(route('expenses.show', row.id))}
                                            >
                                                {value}
                                            </div>
                                            <div className="text-sm text-gray-500">by {row.submitter?.name}</div>
                                        </div>
                                    )
                                },
                                {
                                    key: 'project.title',
                                    label: t('Project & Category'),
                                    sortable: true,
                                    render: (value: string, row: any) => (
                                        <div>
                                            <div className="text-sm font-medium text-gray-900">{value}</div>
                                            {row.budget_category && (
                                                <div className="flex items-center gap-1 mt-1">
                                                    <span className="text-xs text-gray-500">{row.budget_category.name}</span>
                                                </div>
                                            )}
                                        </div>
                                    )
                                },
                                {
                                    key: 'amount',
                                    label: t('Amount'),
                                    sortable: true,
                                    render: (value: number) => (
                                        <span className="text-sm font-medium text-gray-900">
                                            {formatCurrency(value)}
                                        </span>
                                    )
                                },
                                {
                                    key: 'status',
                                    label: t('Status'),
                                    sortable: true,
                                    render: (value: string) => (
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                                            value === 'requires_info' ? 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20' : 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20'
                                        }`}>
                                            {formatText(value)}
                                        </span>
                                    )
                                },
                                {
                                    key: 'expense_date',
                                    label: t('Date'),
                                    sortable: true,
                                    render: (value: string) => (
                                        <span className="text-sm text-gray-500">
                                            {window.appSettings.formatDateTime(new Date(value),false)}
                                        </span>
                                    )
                                }
                            ]}
                            actions={[
                                {
                                    label: t('Approve'),
                                    icon: 'Check',
                                    action: 'approve',
                                    className: 'text-green-600 hover:text-green-700',
                                    condition: () => approvalPermissions?.approve
                                },
                                {
                                    label: t('Reject'),
                                    icon: 'X',
                                    action: 'reject',
                                    className: 'text-red-600 hover:text-red-700',
                                    condition: () => approvalPermissions?.reject
                                },
                                {
                                    label: t('Request Info'),
                                    icon: 'AlertCircle',
                                    action: 'request_info',
                                    className: 'text-blue-600 hover:text-blue-700',
                                    condition: (row: any) => approvalPermissions?.request_info && row.status === 'pending'
                                }
                            ]}
                            data={expenses.data || []}
                            from={expenses.from || 1}
                            onAction={handleAction}
                            sortField={filters?.sort_by}
                            sortDirection={filters?.sort_order}
                            onSort={handleSort}
                            permissions={[]}
                        />
                        {expenses?.links && expenses.data?.length > 0 && (
                            <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
                                <div className="text-sm text-gray-600">
                                    Showing {expenses.from} to {expenses.to} of {expenses.total} expenses
                                </div>
                                <div className="flex gap-1">
                                    {expenses.links?.map((link: any, i: number) => {
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
                                                    router.get(route('expense-approvals.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false });
                                                }}
                                            >
                                                {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                        </div>
                    )
                ) : (
                    <div className="bg-white rounded-lg shadow p-8 text-center">
                        <Clock className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                        <p className="text-gray-500 mb-4">No pending approvals</p>
                        <p className="text-sm text-gray-400">All expenses have been processed</p>
                    </div>
                )}
            </div>
        </PageTemplate>
    );
}