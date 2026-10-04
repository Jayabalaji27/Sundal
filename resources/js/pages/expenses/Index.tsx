import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Filter, Eye, Edit, Copy, Trash2, LayoutGrid, List, Receipt, Calendar, User as UserIcon, CheckCircle, XCircle, Clock, AlertCircle, Zap, Download } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudTable } from '@/components/CrudTable';
import { hasPermission } from '@/utils/authorization';
import ExpenseFormModal from '@/components/expenses/ExpenseFormModal';
import ExpenseViewModal from '@/components/expenses/ExpenseViewModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { useTranslation } from 'react-i18next';



interface Expense {
    id: number;
    project: {
        id: number;
        title: string;
    };
    budget_category?: {
        id: number;
        name: string;
        color: string;
    };
    submitter: {
        id: number;
        name: string;
        avatar?: string;
    };
    amount: number;
    currency: string;
    expense_date: string;
    title: string;
    description?: string;
    vendor?: string;
    status: 'pending' | 'approved' | 'rejected' | 'requires_info';
    created_at: string;
    can_edit?: boolean;
    can_delete?: boolean;
}

export default function ExpenseIndex() {
    const { t } = useTranslation();
    const { expenses, projects, categories, filters, auth, project_name, userWorkspaceRole, workspace, budget_id, flash, permissions: pagePermissions } = usePage().props as any;
    const expensePermissions = pagePermissions;
    
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

    // Get project name from projects array if not directly provided
    const currentProjectName = project_name || (filters?.project_id ?
        projects?.find((p: any) => p.id.toString() === filters.project_id.toString())?.title
        : null);

    const [activeView, setActiveView] = useState(filters?.view || 'grid');
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [selectedProject, setSelectedProject] = useState(filters?.project_id || 'all');
    const [selectedCategory, setSelectedCategory] = useState(filters?.category_id || 'all');
    const [selectedStatus, setSelectedStatus] = useState(filters?.status || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [currentExpense, setCurrentExpense] = useState<Expense | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit'>('create');
    const [deleteExpense, setDeleteExpense] = useState<Expense | null>(null);



    // Central param builder — reads per_page/sort/view from server `filters` prop (source of truth)
    // stateOverrides lets us pass new values before setState updates the closure
    const buildParams = (
        overrides: Record<string, any> = {},
        stateOverrides: { search?: string; project?: string; category?: string; status?: string; view?: string } = {}
    ) => {
        const view     = stateOverrides.view     !== undefined ? stateOverrides.view     : activeView;
        const search   = stateOverrides.search   !== undefined ? stateOverrides.search   : searchTerm;
        const project  = stateOverrides.project  !== undefined ? stateOverrides.project  : selectedProject;
        const category = stateOverrides.category !== undefined ? stateOverrides.category : selectedCategory;
        const status   = stateOverrides.status   !== undefined ? stateOverrides.status   : selectedStatus;

        const params: any = { page: 1, view };
        if (search) params.search = search;
        if (project !== 'all') params.project_id = project;
        if (category !== 'all') params.category_id = category;
        if (status !== 'all') params.status = status;
        if (filters?.per_page) params.per_page = filters.per_page;
        if (filters?.sort_by) params.sort_by = filters.sort_by;
        if (filters?.sort_order) params.sort_order = filters.sort_order;
        return { ...params, ...overrides };
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('expenses.index'), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const applyFilters = () => {
        router.get(route('expenses.index'), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
    };

    // Add sorting functionality
    const handleSort = (field: string) => {
        const direction = filters?.sort_by === field && filters?.sort_order === 'asc' ? 'desc' : 'asc';
        router.get(route('expenses.index'), buildParams({ sort_by: field, sort_order: direction, page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const handleAction = (action: string, expenseOrId: Expense | number) => {
        let expense: Expense;
        
        if (typeof expenseOrId === 'number') {
            // Called from CrudTable with ID
            expense = expenses?.data?.find((e: Expense) => e.id === expenseOrId);
            if (!expense) return;
        } else {
            // Called from grid view with expense object
            expense = expenseOrId;
        }
        
        switch (action) {
            case 'view':
                setCurrentExpense(expense);
                setIsViewModalOpen(true);
                break;
            case 'edit':
                setCurrentExpense(expense);
                setModalMode('edit');
                setIsModalOpen(true);
                break;
            case 'duplicate':
                toast.loading(t('Duplicating expense...'));
                router.post(route('expenses.duplicate', expense.id), {}, {
                    onSuccess: () => {
                        toast.dismiss();
                    },
                    onError: () => {
                        toast.dismiss();
                        toast.error(t('Failed to duplicate expense'));
                    }
                });
                break;
            case 'delete':
                setDeleteExpense(expense);
                break;
        }
    };

    const handleAddNew = () => {
        setCurrentExpense(null);
        setModalMode('create');
        setIsModalOpen(true);
    };

    // /expenses/create (e.g. Dashboard → Submit Expense) lands here with ?create=1.
    useEffect(() => {
        const url = new URL(window.location.href);
        if (url.searchParams.get('create') === '1') {
            handleAddNew();
            url.searchParams.delete('create');
            window.history.replaceState(window.history.state, '', url.toString());
        }
    }, []);

    const getStatusColor = (status: string) => {
        const colors: Record<string, string> = {
            pending: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            approved: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            rejected: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            requires_info: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
        };
        return colors[status] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'approved': return <CheckCircle className="h-4 w-4 text-green-600" />;
            case 'rejected': return <XCircle className="h-4 w-4 text-red-600" />;
            case 'requires_info': return <AlertCircle className="h-4 w-4 text-blue-600" />;
            default: return <Clock className="h-4 w-4 text-yellow-600" />;
        }
    };

    const formatCurrency = (amount: string | number) => {
        if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
            const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
            return window.appSettings.formatCurrency(numericAmount, { showSymbol: true });
        }
        return amount || 0;
    };

    const pageActions = [];

    if (hasPermission(auth?.permissions || [], 'expense_generate_reports')) {
        pageActions.push({
            label: t('Export'),
            icon: <Download className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => {
                const params = new URLSearchParams();
                if (selectedStatus && selectedStatus !== 'all') params.set('status', selectedStatus);
                if (selectedProject && selectedProject !== 'all') params.set('project_id', selectedProject);
                window.location.href = `${route('expenses.export')}?${params.toString()}`;
            }
        });
    }

    if (hasPermission(auth?.permissions || [], 'expense_approval_view_any')) {
        pageActions.push({
            label: t('Approvals'),
            icon: <CheckCircle className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('expense-approvals.index'))
        });
    }

    if (expensePermissions?.create) {
        pageActions.push({
            label: t('Add Expense'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: handleAddNew
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Budget & Expenses'), href: route('budgets.dashboard') },
        ...(currentProjectName ? [
            { title: t('Projects'), href: route('projects.index') },
            { title: t('Budgets'), href: route('budgets.index') }
        ] : []),
        { title: currentProjectName ? `${currentProjectName} - ${t('Expenses')}` : t('Expenses') }
    ];

    return (
        <PageTemplate
            title={currentProjectName ? `${currentProjectName} - ${t('Expenses')}` : t('Expenses')}
            url="/expenses"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Overview Stats Grid */}
            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                {/* Total Expenses */}
                <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                    <div className="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center shrink-0">
                        <Receipt className="h-5 w-5 text-blue-600 dark:text-blue-100" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs text-gray-500 dark:text-gray-400">{t('Total Expenses')}</p>
                        <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">{expenses?.total || 0}</p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">{t('Total Records')}</p>
                    </div>
                </div>

                {/* Pending */}
                <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                    <div className="h-10 w-10 rounded-lg bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center shrink-0">
                        <Clock className="h-5 w-5 text-yellow-600 dark:text-yellow-100" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs text-gray-500 dark:text-gray-400">{t('Pending')}</p>
                        <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                            {expenses?.data?.filter((exp: Expense) => exp.status === 'pending').length || 0}
                        </p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">{t('Awaiting Approval')}</p>
                    </div>
                </div>

                {/* Approved */}
                <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                    <div className="h-10 w-10 rounded-lg bg-green-100 dark:bg-green-900 flex items-center justify-center shrink-0">
                        <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-100" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs text-gray-500 dark:text-gray-400">{t('Approved')}</p>
                        <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                            {expenses?.data?.filter((exp: Expense) => exp.status === 'approved').length || 0}
                        </p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">{t('Finalized')}</p>
                    </div>
                </div>

                {/* Rejected */}
                <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                    <div className="h-10 w-10 rounded-lg bg-red-100 dark:bg-red-900 flex items-center justify-center shrink-0">
                        <XCircle className="h-5 w-5 text-red-600 dark:text-red-100" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs text-gray-500 dark:text-gray-400">{t('Rejected')}</p>
                        <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                            {expenses?.data?.filter((exp: Expense) => exp.status === 'rejected').length || 0}
                        </p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">{t('Declined')}</p>
                    </div>
                </div>

                {/* Total Amount */}
                <div className="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200 p-4">
                    <div className="h-10 w-10 rounded-lg bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center shrink-0">
                        <Zap className="h-5 w-5 text-indigo-600 dark:text-indigo-100" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs text-gray-500 dark:text-gray-400">{t('Total Amount')}</p>
                        <p className="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                            {(() => {
                                if (!expenses?.data || expenses.data.length === 0) {
                                    return formatCurrency(0);
                                }
                                const total = expenses.data.reduce((sum: number, exp: Expense) => {
                                    return sum + (parseFloat(exp.amount?.toString()) || 0);
                                }, 0);
                                return formatCurrency(total);
                            })()}
                        </p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">{t('Total Cost')}</p>
                    </div>
                </div>
            </div>

            {/* Filters Row */}
            <div className="bg-white rounded-lg border shadow mb-4">
                <div className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search expenses...')}
                                        value={searchTerm}
                                        onChange={(e) => {
                                            setSearchTerm(e.target.value);
                                            clearTimeout(window.searchTimeout);
                                            window.searchTimeout = setTimeout(() => {
                                                router.get(route('expenses.index'), buildParams({ page: 1 }, { search: e.target.value }), { preserveState: false, preserveScroll: false });
                                            }, 500);
                                        }}
                                        className="w-full pl-9"
                                    />
                                </div>
                                <Button type="submit" size="sm">
                                    <Search className="h-4 w-4 mr-1.5" />
                                    {t('Search')}
                                </Button>
                            </form>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowFilters(!showFilters)}
                            >
                                <Filter className="h-4 w-4 mr-1.5" />
                                {t('Filters')}
                            </Button>
                        </div>

                        <div className="flex items-center gap-2">
                            <div className="border rounded-md p-0.5">
                                <Button
                                    size="sm"
                                    variant={activeView === 'list' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => { setActiveView('list'); router.get(route('expenses.index'), buildParams({ page: 1, view: 'list' }, { view: 'list' }), { preserveState: false, preserveScroll: false }); }}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant={activeView === 'grid' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => { setActiveView('grid'); router.get(route('expenses.index'), buildParams({ page: 1, view: 'grid' }, { view: 'grid' }), { preserveState: false, preserveScroll: false }); }}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>

                            <Label className="text-xs text-muted-foreground">{t('Per Page:')}</Label>
                            <Select
                                value={expenses?.per_page?.toString() || filters?.per_page?.toString() || "12"}
                                onValueChange={(value) => {
                                    router.get(route('expenses.index'), buildParams({ page: 1, per_page: parseInt(value) }), { preserveState: false, preserveScroll: false });
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
                        <div className="p-4 bg-gray-50 border rounded-md">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <Label>{t('Project')}</Label>
                                    <Select value={selectedProject} onValueChange={(value) => {
                                        setSelectedProject(value);
                                        router.get(route('expenses.index'), buildParams({ page: 1 }, { project: value }), { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Projects')} />
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

                                <div className="space-y-2">
                                    <Label>{t('Category')}</Label>
                                    <Select value={selectedCategory} onValueChange={(value) => {
                                        setSelectedCategory(value);
                                        router.get(route('expenses.index'), buildParams({ page: 1 }, { category: value }), { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Categories')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All</SelectItem>
                                            {categories?.map((category: any) => (
                                                <SelectItem key={category.id} value={category.id.toString()}>
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('Status')}</Label>
                                    <Select value={selectedStatus} onValueChange={(value) => {
                                        setSelectedStatus(value);
                                        router.get(route('expenses.index'), buildParams({ page: 1 }, { status: value }), { preserveState: false, preserveScroll: false });
                                    }}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Status')} />
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

                                <Button variant="outline" size="sm" onClick={() => {
                                    setSelectedProject('all');
                                    setSelectedCategory('all');
                                    setSelectedStatus('all');
                                    setSearchTerm('');
                                    setShowFilters(false);
                                    const params: any = { page: 1, view: activeView };
                                    if (filters?.per_page) params.per_page = filters.per_page;
                                    router.get(route('expenses.index'), params, { preserveState: false, preserveScroll: false });
                                }}>
                                    {t('Reset Filters')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Expense Content */}
                {activeView === 'grid' ? (
                    expenses?.data?.length === 0 ? (
                <div className="bg-white rounded-lg shadow p-8 text-center">
                    <Receipt className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                    <p className="text-gray-500 mb-4">{t('No expenses found')}</p>
                    {expensePermissions?.create && (
                        <Button onClick={handleAddNew}>
                            <Plus className="h-4 w-4 mr-2" />
                            {t('Add your first expense')}
                        </Button>
                    )}
                </div>
            ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            {expenses?.data?.map((expense: Expense) => (
                                <Card key={expense.id} className={`overflow-hidden hover:shadow-lg transition-all duration-300 `}>
                                    <CardHeader className="pb-2">
                                        <CardTitle 
                                            className="text-base font-semibold line-clamp-1 cursor-pointer hover:text-blue-600 transition-colors"
                                            onClick={() => handleAction('view', expense)}
                                        >
                                            {expense.title}
                                        </CardTitle>
                                    </CardHeader>

                                    <CardContent className="py-2 space-y-3">
                                        <div className="text-lg font-bold text-gray-900">
                                            {formatCurrency(expense.amount)}
                                        </div>

                                        <div className="flex items-center gap-2 text-xs">
                                            <span className="font-medium text-gray-600 truncate max-w-[120px]">{expense.project.title}</span>
                                            {expense.budget_category && (
                                                <>
                                                    <span className="text-gray-300">•</span>
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="text-gray-600 truncate max-w-[100px]">{expense.budget_category.name}</span>
                                                    </div>
                                                </>
                                            )}
                                        </div>

                                        {expense.description && (
                                            <p className="text-xs text-muted-foreground line-clamp-2">{expense.description}</p>
                                        )}

                                        <div className="flex items-center gap-2 text-xs pt-1 border-t text-gray-500">
                                            <div className="flex items-center gap-1">
                                                <Avatar className='h-5 w-5'>
                                                    <AvatarImage src={expense.submitter.avatar} />
                                                    <AvatarFallback>{expense.submitter.name.charAt(0)}</AvatarFallback>
                                                </Avatar>
                                                <span className="truncate">{expense.submitter.name}</span>
                                            </div>
                                            <span className="text-gray-300">•</span>
                                            <div className="flex items-center gap-1">
                                                <Calendar className="h-3 w-3" />
                                                <span>{window.appSettings.formatDateTime(new Date(expense.expense_date),false)}</span>
                                            </div>
                                        </div>
                                    </CardContent>

                                    <CardFooter className="flex justify-between items-center bg-gray-50/50 py-2 border-t mt-1 px-4">
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(expense.status)}`}>
                                            {formatText(expense.status)}
                                        </span>

                                        <div className="flex gap-1">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => handleAction('view', expense)}
                                                        className="text-blue-500 hover:text-blue-700 h-7 w-7"
                                                    >
                                                        <Eye className="h-3.5 w-3.5" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{t('View')}</TooltipContent>
                                            </Tooltip>
                                            {expensePermissions?.update && expense.status !== 'approved' && (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                handleAction('edit', expense);
                                                            }}
                                                            className="text-amber-500 hover:text-amber-700 h-7 w-7"
                                                        >
                                                            <Edit className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>{t('Edit')}</TooltipContent>
                                                </Tooltip>
                                            )}
                                            {expensePermissions?.delete && (
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-red-500 hover:text-red-700 h-7 w-7"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                handleAction('delete', expense);
                                                            }}
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
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
                                key: 'title',
                                label: t('Expense'),
                                sortable: true,
                                render: (value: string, row: any) => (
                                    <div>
                                        <div
                                            className="text-sm font-medium text-gray-900 cursor-pointer hover:text-blue-600 hover:underline"
                                            onClick={() => handleAction('view', row)}
                                        >
                                            {value}
                                        </div>
                                        {row.description && (
                                            <div className="text-sm text-gray-500 truncate max-w-xs">{row.description}</div>
                                        )}
                                    </div>
                                )
                            },
                            {
                                key: 'amount',
                                label: t('Amount'),
                                sortable: true,
                                render: (value: number, row: any) => (
                                    <div>
                                        <div className="text-sm font-medium text-gray-900">
                                            {formatCurrency(value)}
                                        </div>
                                        {row.vendor && (
                                            <div className="text-sm text-gray-500">{row.vendor}</div>
                                        )}
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
                                        {row.budget_category ? (
                                            <div className="flex items-center gap-1 mt-1">
                                                <span className="text-xs text-gray-500">{row.budget_category.name}</span>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-gray-400 mt-1 block">{t('Uncategorized')}</span>
                                        )}
                                    </div>
                                )
                            },
                            {
                                key: 'status',
                                label: t('Status'),
                                sortable: true,
                                render: (value: string) => (
                                    <div className="flex items-center gap-2">
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(value)}`}>
                                            {formatText(value)}
                                        </span>
                                    </div>
                                )
                            },
                            {
                                key: 'submitter.name',
                                label: t('Submitter'),
                                sortable: true,
                                render: (value: string, row: any) => (
                                    <div className="flex items-center gap-2">
                                        <Avatar className="h-6 w-6">
                                            <AvatarImage src={row.submitter.avatar} />
                                            <AvatarFallback className="text-xs">
                                                {row.submitter.name?.charAt(0)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span className="text-sm">{value}</span>
                                    </div>
                                )
                            },
                            {
                                key: 'expense_date',
                                label: t('Date'),
                                sortable: true,
                                render: (value: string) => (
                                    <span className="text-sm text-gray-900">
                                        {window.appSettings.formatDateTime(new Date(value),false)}
                                    </span>
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
                                condition: (row: any) => expensePermissions?.update && row?.status !== 'approved'
                            },
                            {
                                label: t('Duplicate'),
                                icon: 'Copy',
                                action: 'duplicate',
                                className: 'text-green-500 hover:text-green-700',
                                condition: () => expensePermissions?.create
                            },
                            {
                                label: t('Delete'),
                                icon: 'Trash2',
                                action: 'delete',
                                className: 'text-red-500 hover:text-red-700',
                                condition: () => expensePermissions?.delete
                            }
                        ]}
                        data={expenses?.data || []}
                        from={expenses?.from || 1}
                        onAction={handleAction}
                        sortField={filters?.sort_by}
                        sortDirection={filters?.sort_order}
                        onSort={handleSort}
                        permissions={auth?.permissions || []}
                    />
                     {/* Pagination - for list view */}
                        {expenses?.links && expenses.data?.length > 0 && (
                            <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
                                <div className="text-sm text-muted-foreground">
                                    {t('Showing')} <span className="font-medium">{expenses?.from || 0}</span> {t('to')} <span className="font-medium">{expenses?.to || 0}</span> {t('of')} <span className="font-medium">{expenses?.total || 0}</span> {t('expenses')}
                                </div>

                                <div className="flex gap-1">
                                    {expenses?.links?.map((link: any, i: number) => {
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
                                                    router.get(route('expenses.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
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

                )}
            {/* Pagination - for grid view  */}
            {activeView === 'grid' && expenses?.links && expenses.data?.length > 0 && (
                <div className="mt-6 bg-[#F0F0F1] dark:bg-gray-800 p-4 rounded-lg shadow flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        {t('Showing')} <span className="font-medium">{expenses?.from || 0}</span> {t('to')} <span className="font-medium">{expenses?.to || 0}</span> {t('of')} <span className="font-medium">{expenses?.total || 0}</span> {t('expenses')}
                    </div>

                    <div className="flex gap-1">
                        {expenses?.links?.map((link: any, i: number) => {
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
                                        router.get(route('expenses.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
                                    }}
                                >
                                    {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                </Button>
                            );
                        })}
                    </div>
                </div>
            )}

            <ExpenseFormModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                expense={currentExpense}
                projects={projects}
                mode={modalMode}
                redirectUrl={route('expenses.index')}
            />

            <ExpenseViewModal
                isOpen={isViewModalOpen}
                onClose={() => setIsViewModalOpen(false)}
                expense={currentExpense}
            />

            <CrudDeleteModal
                isOpen={!!deleteExpense}
                onClose={() => setDeleteExpense(null)}
                onConfirm={() => {
                    if (deleteExpense) {
                        toast.loading(t('Deleting expense...'));
                        router.delete(route('expenses.destroy', deleteExpense.id), {
                            onSuccess: () => {
                                toast.dismiss();
                                setDeleteExpense(null);
                            },
                            onError: () => {
                                toast.dismiss();
                                toast.error(t('Failed to delete expense'));
                                setDeleteExpense(null);
                            }
                        });
                    }
                }}
                itemName={deleteExpense?.title || ''}
                entityName={t('Expense')}
                additionalInfo={[
                    `${t('Amount')}: ${deleteExpense ? formatCurrency(deleteExpense.amount) : ''}`,
                    `${t('Project')}: ${deleteExpense?.project.title || ''}`,
                    `${t('Date')}: ${deleteExpense ? window.appSettings.formatDateTime(new Date(deleteExpense.expense_date),false) : ''}`
                ]}
            />

        </PageTemplate>
    );
}