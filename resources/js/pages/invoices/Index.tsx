import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Filter, Eye, Edit, DollarSign, Trash2, FileText, Calendar, AlertTriangle, CreditCard, Send, Link, Download, CheckCircle, Clock, AlertCircle, Zap, TrendingUp, Wallet, Hash } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { CrudTable } from '@/components/CrudTable';
import { InvoicePaymentModal } from '@/components/invoices/invoice-payment-modal';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from 'react-i18next';

interface Invoice {
    id: number;
    invoice_number: string;
    project: {
        id: number;
        title: string;
    };
    client?: {
        id: number;
        name: string;
        avatar?: string;
    };
    title: string;
    total_amount: number;
    paid_amount: number;
    status: 'draft' | 'sent' | 'paid' | 'partial_paid' | 'overdue' | 'cancelled';
    invoice_date: string;
    due_date: string;
    is_overdue: boolean;
    days_overdue: number;
    balance_due: number;
    formatted_total: string;
    status_color: string;
    payment_token: string;
    created_at: string;
}

export default function InvoiceIndex() {
    const { t } = useTranslation();
    const { invoices, projects, clients, filters, auth, userWorkspaceRole, flash, emailNotificationsEnabled, totalAmount, outstandingAmount, paidAmount, outstandingCount, overdueCount, statusCounts } = usePage().props as any;

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);
    
    const [activeView, setActiveView] = useState('list');
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [selectedProject, setSelectedProject] = useState(filters?.project_id || 'all');
    const [selectedClient, setSelectedClient] = useState(filters?.client_id || 'all');
    const [selectedStatus, setSelectedStatus] = useState(filters?.status || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [invoiceToDelete, setInvoiceToDelete] = useState<Invoice | null>(null);
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [invoiceToPay, setInvoiceToPay] = useState<Invoice | null>(null);
    const [showMarkPaidModal, setShowMarkPaidModal] = useState(false);
    const [invoiceToMarkPaid, setInvoiceToMarkPaid] = useState<Invoice | null>(null);

    const buildParams = (
        overrides: Record<string, any> = {},
        stateOverrides: { search?: string; project?: string; client?: string; status?: string; view?: string } = {}
    ) => {
        const view    = stateOverrides.view    !== undefined ? stateOverrides.view    : activeView;
        const search  = stateOverrides.search  !== undefined ? stateOverrides.search  : searchTerm;
        const project = stateOverrides.project !== undefined ? stateOverrides.project : selectedProject;
        const client  = stateOverrides.client  !== undefined ? stateOverrides.client  : selectedClient;
        const status  = stateOverrides.status  !== undefined ? stateOverrides.status  : selectedStatus;

        const params: any = { page: 1, view };
        if (search) params.search = search;
        if (project !== 'all') params.project_id = project;
        if (client !== 'all') params.client_id = client;
        if (status !== 'all') params.status = status;
        if (filters?.per_page) params.per_page = filters.per_page;
        if (filters?.sort_by) params.sort_by = filters.sort_by;
        if (filters?.sort_order) params.sort_order = filters.sort_order;
        return { ...params, ...overrides };
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('invoices.index'), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const applyFilters = () => {
        router.get(route('invoices.index'), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const handleSort = (field: string) => {
        const direction = filters?.sort_by === field && filters?.sort_order === 'asc' ? 'desc' : 'asc';
        router.get(route('invoices.index'), buildParams({ sort_by: field, sort_order: direction, page: 1 }), { preserveState: false, preserveScroll: false });
    };

    const handleAction = (action: string, invoice: Invoice) => {
        switch (action) {
            case 'view':
                router.get(route('invoices.show', invoice.id));
                break;
            case 'edit':
                router.get(route('invoices.edit', invoice.id));
                break;

            case 'mark-paid':
                setInvoiceToMarkPaid(invoice);
                setShowMarkPaidModal(true);
                break;
            case 'pay':
                setInvoiceToPay(invoice);
                setShowPaymentModal(true);
                break;
            case 'send':
                toast.loading('Sending invoice...');
                router.post(route('invoices.send', invoice.id), {}, {
                    onSuccess: () => {
                        toast.dismiss();
                    },
                    onError: () => {
                        toast.dismiss();
                        toast.error('Failed to send invoice');
                    }
                });
                break;
            case 'copy-payment-link':
                const paymentUrl = route('invoices.payment', invoice.payment_token);
                navigator.clipboard.writeText(paymentUrl).then(() => {
                    toast.success(t('Payment link copied to clipboard'));
                }).catch(() => {
                    toast.error(t('Failed to copy payment link'));
                });
                break;
            case 'delete':
                setInvoiceToDelete(invoice);
                setIsDeleteModalOpen(true);
                break;
        }
    };

    // CrudTable configuration
    const columns = [
        {
            key: 'invoice_number',
            label: t('Invoice'),
            sortable: true,
            render: (value: string, row: Invoice) => (
                <div className="flex items-center gap-3">
                    <div>
                        <div 
                            className="text-sm font-semibold text-gray-900 cursor-pointer hover:text-blue-600 transition-colors"
                            onClick={() => handleAction('view', row)}
                        >
                            {value}
                        </div>
                        <div className="text-xs text-gray-500 truncate max-w-[160px]">
                            Created on: {window.appSettings.formatDateTime(new Date(row.created_at), false)}
                        </div>
                    </div>
                </div>
            )
        },
        {
            key: 'client',
            label: t('Client/Customer'),
            render: (_: any, row: Invoice) => row.client ? (
                <div className="flex items-center gap-2">
                    <Avatar className="h-7 w-7 shrink-0">
                        <AvatarImage src={row.client.avatar} className="object-cover" />
                        <AvatarFallback><img src="/images/avatar/avatar.png" className="h-full w-full object-cover rounded-full" /></AvatarFallback>
                    </Avatar>
                    <div>
                        <div className="text-sm font-medium text-gray-900">{row.client.name}</div>
                    </div>
                </div>
            ) : (
                <span className="text-sm text-gray-400">—</span>
            )
        },
        {
            key: 'project',
            label: t('Project'),
            render: (_: any, row: Invoice) => row.project ? (
                
                    <div className="text-sm font-medium text-gray-900">{row.project?.title || ''}</div>
                
            ) : (
                <span className="text-sm text-gray-400">—</span>
            )
        },
        {
            key: 'total_amount',
            label: t('Total Amount'),
            sortable: true,
            render: (value: number) => (
                <span className="text-sm font-semibold text-gray-900">
                    {formatCurrency(value)}
                </span>
            )
        },
        {
            key: 'paid_amount',
            label: t('Paid Amount'),
            render: (_: any, row: Invoice) => (
                row.paid_amount > 0
                    ? <span className="text-sm font-semibold text-green-600">{formatCurrency(row.paid_amount)}</span>
                    : <span className="text-sm text-gray-400">-</span>
            )
        },
        {
            key: 'balance_due',
            label: t('Due Amount'),
            render: (_: any, row: Invoice) => (
                row.balance_due > 0 && row.status !== 'paid' && row.status !== 'cancelled'
                    ? <span className="text-sm font-semibold text-red-500">{formatCurrency(row.balance_due)}</span>
                    : <span className="text-sm text-gray-400">-</span>
            )
        },
        {
            key: 'status',
            label: t('Status'),
            sortable: true,
            render: (value: string, row: Invoice) => (
                <div className="flex items-center gap-2">
                    {/* {row.is_overdue && <AlertTriangle className="h-4 w-4 text-red-500" />} */}
                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium whitespace-nowrap ${getStatusColor(value)}`}>
                        {value?.replace(/_/g, ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')}
                    </span>
                </div>
            )
        },
        {
            key: 'due_date',
            label: t('Due Date'),
            sortable: true,
            render: (value: string, row: Invoice) => (
                <div>
                    <div className={`flex items-center gap-1.5 text-sm whitespace-nowrap ${row.is_overdue ? 'text-red-600 font-medium' : 'text-gray-700'}`}>
                        <Calendar className="h-3.5 w-3.5 shrink-0" />
                        <span>{window.appSettings.formatDateTime(new Date(value), false)}</span>
                    </div>
                    {row.is_overdue && (
                        <div className="flex items-center gap-1 text-xs text-red-600 mt-0.5">
                            <AlertTriangle className="h-3 w-3" />
                            {row.days_overdue} days overdue
                        </div>
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
            className: 'text-blue-500 hover:text-blue-700'
        },
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            className: 'text-amber-500 hover:text-amber-700',
            condition: (row: Invoice) => ['owner', 'manager'].includes(userWorkspaceRole) && row.status === 'draft'
        },
        {
            label: t('Send'),
            icon: 'Send',
            action: 'send',
            className: 'text-blue-500 hover:text-blue-700',
            condition: (row: Invoice) => ['owner', 'manager'].includes(userWorkspaceRole) && row.status === 'draft' && emailNotificationsEnabled
        },
        {
            label: t('Copy Payment Link'),
            icon: 'Link',
            action: 'copy-payment-link',
            className: 'text-purple-500 hover:text-purple-700',
            condition: (row: Invoice) => userWorkspaceRole !== 'client' && row.status !== 'paid'
        },
        // {
        //     label: t('Mark as Paid'),
        //     icon: 'DollarSign',
        //     action: 'mark-paid',
        //     className: 'text-green-500 hover:text-green-700',
        //     condition: (row: Invoice) => userWorkspaceRole !== 'client' && (row.status === 'sent' || row.status === 'viewed' || row.status === 'overdue')
        // },
        {
            label: t('Pay Now'),
            icon: 'CreditCard',
            action: 'pay',
            className: 'text-blue-500 hover:text-blue-700',
            condition: (row: Invoice) => userWorkspaceRole === 'client' && row.status !== 'paid' && row.status !== 'cancelled'
        },
        {
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            className: 'text-red-500 hover:text-red-700',
            condition: (row: Invoice) => ['owner', 'manager'].includes(userWorkspaceRole) && row.status === 'draft'
        }
    ];

    const getStatusColor = (status: string) => {
        const colors = {
            draft: 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-500/30',
            sent: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            paid: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            partial_paid: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20',
            overdue: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            cancelled: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const formatCurrency = (amount: string | number) => {
        if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
            const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
            return window.appSettings.formatCurrency(numericAmount);
        }
        return amount || 0;
    };

    const handleDeleteConfirm = () => {
        if (invoiceToDelete) {
            toast.loading('Deleting invoice...');
            router.delete(route('invoices.destroy', invoiceToDelete.id), {
                onSuccess: () => {
                    toast.dismiss();
                    setIsDeleteModalOpen(false);
                    setInvoiceToDelete(null);
                },
                onError: () => {
                    toast.dismiss();
                    toast.error('Failed to delete invoice');
                    setIsDeleteModalOpen(false);
                    setInvoiceToDelete(null);
                }
            });
        }
    };

    const handleMarkPaidConfirm = () => {
        if (invoiceToMarkPaid) {
            toast.loading('Marking invoice as paid...');
            router.post(route('invoices.mark-paid', invoiceToMarkPaid.id), {}, {
                onSuccess: () => {
                    toast.dismiss();
                    setShowMarkPaidModal(false);
                    setInvoiceToMarkPaid(null);
                },
                onError: () => {
                    toast.dismiss();
                    toast.error('Failed to mark invoice as paid');
                    setShowMarkPaidModal(false);
                    setInvoiceToMarkPaid(null);
                }
            });
        }
    };

    const hasActiveFilters = () => {
        return selectedStatus !== 'all' || selectedProject !== 'all' || selectedClient !== 'all' || searchTerm !== '';
    };

    const activeFilterCount = () => {
        return (selectedStatus !== 'all' ? 1 : 0)+(selectedProject !== 'all' ? 1 : 0) + (selectedClient !== 'all' ? 1 : 0) + (searchTerm ? 1 : 0);
    };

    const pageActions = [];
    
    // Export - only for non-clients
    if (userWorkspaceRole !== 'client') {
        pageActions.push({
            label: t('Export'),
            icon: <Download className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: async () => {
                try {
                    // route()'s second argument is Ziggy route params, not a
                    // query string — it expects a plain object, not a
                    // URLSearchParams instance (whose entries aren't visible
                    // via Object.keys/enumeration the way Ziggy reads params).
                    const params: Record<string, string> = {};
                    if (searchTerm) params.search = searchTerm;
                    if (selectedProject !== 'all') params.project_id = selectedProject;
                    if (selectedClient !== 'all') params.client_id = selectedClient;
                    if (selectedStatus !== 'all') params.status = selectedStatus;

                    const response = await fetch(route('invoices.export', params), {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) {
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
                    a.download = `invoices_export_${new Date().toISOString().split('T')[0]}.xlsx`;
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
    
    if (['owner', 'manager','member'].includes(userWorkspaceRole)) {
        pageActions.push({
            label: t('Create Invoice'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: () => router.get(route('invoices.create'))
        });
    }

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Invoices') }
    ];

    return (
        <PageTemplate 
            title={t('Invoices')} 
            url="/invoices"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            {/* Stat Cards */}
            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4 mb-4">
                {[
                    {
                        title: t('Total Invoiced'),
                        value: formatCurrency(totalAmount || 0),
                        icon: DollarSign,
                        iconBg: 'bg-blue-100 dark:bg-blue-900',
                        iconColor: 'text-blue-600 dark:text-blue-300',
                    },
                    {
                        title: t('Total Paid'),
                        value: formatCurrency(paidAmount || 0),
                        icon: CheckCircle,
                        iconBg: 'bg-green-100 dark:bg-green-900',
                        iconColor: 'text-green-600 dark:text-green-300',
                    },
                    {
                        title: t('Total Unpaid'),
                        value: formatCurrency(outstandingAmount || 0),
                        icon: Wallet,
                        iconBg: 'bg-orange-100 dark:bg-orange-900',
                        iconColor: 'text-orange-600 dark:text-orange-300',
                    },
                    {
                        title: t('Unpaid Invoices'),
                        value: outstandingCount || 0,
                        icon: Hash,
                        iconBg: 'bg-purple-100 dark:bg-purple-900',
                        iconColor: 'text-purple-600 dark:text-purple-300',
                    },
                    {
                        title: t('Overdue'),
                        value: overdueCount || 0,
                        icon: AlertTriangle,
                        iconBg: 'bg-red-100 dark:bg-red-900',
                        iconColor: 'text-red-600 dark:text-red-300',
                    },
                ].map((card, index) => (
                    <Card key={index} className="shadow-sm hover:shadow-md transition-shadow duration-200 cursor-default">
                        <CardContent className="p-4">
                            <div className="flex items-center gap-3">
                                <div className={`h-11 w-11 rounded-full flex items-center justify-center shrink-0 ${card.iconBg}`}>
                                    <card.icon className={`h-5 w-5 ${card.iconColor}`} />
                                </div>
                                <div>
                                    <p className="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100">{card.value}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{card.title}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Status Tabs */}
            <div className="bg-white rounded-lg border shadow mb-4">

                {/* Row 1: Search + Per Page + Filters */}
                <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b">
                    <form onSubmit={handleSearch} className="flex gap-2">
                        <div className="relative w-full sm:w-72">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder={t('Search invoices...')}
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
                    <div className="flex items-center gap-2">
                    <Label className="text-xs text-muted-foreground">{t('Per Page')}:</Label>
                        <Select
                            value={filters?.per_page?.toString() || "10"}
                            onValueChange={(value) => {
                                router.get(route('invoices.index'), buildParams({ page: 1, per_page: parseInt(value) }), { preserveState: false, preserveScroll: false });
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
                        <Button
                            variant={hasActiveFilters() ? "default" : "outline"}
                            size="sm"
                            className="h-8 px-3"
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

                {/* Row 2: Status Tabs with icons + counts */}
                <div className="flex items-center gap-1 px-4 pt-1 pb-0 overflow-x-auto">
                    {[
                        { value: 'all', label: t('All Invoices'), icon: <FileText className="h-3.5 w-3.5" />, count: statusCounts?.all ?? 0 },
                        // Drafts are never shown to clients (backend excludes them entirely), so the tab is meaningless in their POV.
                        ...(userWorkspaceRole !== 'client' ? [{ value: 'draft', label: t('Draft'), icon: <Clock className="h-3.5 w-3.5" />, count: statusCounts?.draft ?? 0 }] : []),
                        { value: 'sent', label: t('Sent'), icon: <Send className="h-3.5 w-3.5" />, count: statusCounts?.sent ?? 0 },
                        { value: 'paid', label: t('Paid'), icon: <CheckCircle className="h-3.5 w-3.5" />, count: statusCounts?.paid ?? 0 },
                        { value: 'partial_paid', label: t('Partial Paid'), icon: <CreditCard className="h-3.5 w-3.5" />, count: statusCounts?.partial_paid ?? 0 },
                        { value: 'overdue', label: t('Overdue'), icon: <AlertTriangle className="h-3.5 w-3.5" />, count: statusCounts?.overdue ?? 0 },
                    ].map((tab) => (
                        <button
                            key={tab.value}
                            onClick={() => {
                                setSelectedStatus(tab.value);
                                router.get(route('invoices.index'), buildParams({ page: 1 }, { status: tab.value }), { preserveState: false, preserveScroll: false });
                            }}
                            className={`flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                                selectedStatus === tab.value
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            {tab.icon}
                            {tab.label}
                            <span className={`text-xs px-1.5 py-0.5 rounded-full font-medium ${
                                selectedStatus === tab.value ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-500'
                            }`}>
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </div>

                {/* Filters panel */}
                {showFilters && (
                    <div className="mx-4 mb-4 mt-3 p-4 bg-gray-50 border rounded-md">
                        <div className="flex flex-wrap gap-4 items-end">
                            <div className="space-y-2">
                                <Label>{t('Project')}</Label>
                                <Select value={selectedProject} onValueChange={(value) => {
                                    setSelectedProject(value);
                                    router.get(route('invoices.index'), buildParams({ page: 1 }, { project: value }), { preserveState: false, preserveScroll: false });
                                }}>
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
                            <Button variant="outline" size="sm" onClick={() => {
                                setSelectedProject('all');
                                setSelectedClient('all');
                                setSelectedStatus('all');
                                setSearchTerm('');
                                setShowFilters(false);
                                const params: any = { page: 1 };
                                if (filters?.per_page) params.per_page = filters.per_page;
                                router.get(route('invoices.index'), params, { preserveState: false, preserveScroll: false });
                            }}>
                                {t('Reset Filters')}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <style>{`.invoice-table th:last-child { text-align: center !important; }`}</style>
                    <div className="invoice-table">
                    <CrudTable
                        columns={columns}
                        actions={actions}
                        data={invoices?.data || []}
                        from={invoices?.from || 1}
                        onAction={handleAction}
                        sortField={filters?.sort_by}
                        sortDirection={filters?.sort_order}
                        onSort={handleSort}
                        permissions={auth?.permissions || []}
                    />
                    </div>
                    {/* Pagination - for list view */}
                    {invoices?.links && (
                        <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
                            <div className="text-sm text-muted-foreground">
                                Showing <span className="font-medium">{invoices?.from || 0}</span> to <span className="font-medium">{invoices?.to || 0}</span> of <span className="font-medium">{invoices?.total || 0}</span> invoices
                            </div>
                            
                            <div className="flex gap-1">
                                {invoices?.links?.map((link: any, i: number) => {
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
                                                router.get(route('invoices.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
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

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => {
                    setIsDeleteModalOpen(false);
                    setInvoiceToDelete(null);
                }}
                onConfirm={handleDeleteConfirm}
                itemName={invoiceToDelete?.invoice_number || ''}
                entityName="invoice"
            />
            
            {/* Payment Modal */}
            {invoiceToPay && (
                <InvoicePaymentModal
                    invoice={invoiceToPay}
                    open={showPaymentModal}
                    onClose={() => {
                        setShowPaymentModal(false);
                        setInvoiceToPay(null);
                    }}
                    onSuccess={() => {
                        setShowPaymentModal(false);
                        setInvoiceToPay(null);
                        router.reload();
                    }}
                />
            )}
            {/* Mark as Paid Confirmation Modal */}
            <Dialog open={showMarkPaidModal} onOpenChange={setShowMarkPaidModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Mark Invoice as Paid')}</DialogTitle>
                    </DialogHeader>
                    <p>{t('Are you sure you want to mark invoice')} {invoiceToMarkPaid?.invoice_number} {t('as paid')}?</p>
                    <div className="flex justify-end gap-2 mt-4">
                        <Button variant="outline" onClick={() => setShowMarkPaidModal(false)}>
                            {t('Cancel')}
                        </Button>
                        <Button onClick={handleMarkPaidConfirm}>
                            {t('Mark as Paid')}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </PageTemplate>
    );
}