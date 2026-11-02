import React, { useState, useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { SearchInput } from '@/components/ui/search-input';
import { StatusBadge } from '@/components/ui/status-badge';
import { StatStrip } from '@/components/stat-strip';
import { EmptyState } from '@/components/EmptyState';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Search, Filter, Eye, Edit, Trash2, FileText, Calendar, AlertTriangle, CreditCard, Send, Link, Download, CheckCircle, Clock, AlertCircle, Zap, TrendingUp } from 'lucide-react';
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

    // Search as you type; preserveState keeps the field (and its focus) while the list reloads.
    const isFirstSearchRender = useRef(true);
    useEffect(() => {
        if (isFirstSearchRender.current) {
            isFirstSearchRender.current = false;
            return;
        }
        if (searchTerm === (filters?.search || '')) return;
        const timeout = setTimeout(() => {
            router.get(route('invoices.index'), buildParams({ page: 1 }), { preserveState: true, preserveScroll: true, replace: true });
        }, 400);
        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchTerm]);

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

    const isClient = userWorkspaceRole === 'client';
    const canPay = (row: Invoice) => row.status !== 'paid' && row.status !== 'cancelled' && row.balance_due > 0;

    // CrudTable configuration
    const columns = [
        {
            key: 'invoice_number',
            label: t('Invoice'),
            sortable: true,
            render: (value: string, row: Invoice) => (
                <div className="flex items-center gap-3">
                    <div>
                        <button
                            type="button"
                            className="cursor-pointer rounded-sm text-left text-sm font-semibold text-ds-fg transition-colors hover:text-ds-fg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus"
                            onClick={() => handleAction('view', row)}
                        >
                            {value}
                        </button>
                        <div className="max-w-[180px] truncate text-xs text-ds-fg-muted">
                            {t('Created on {{date}}', { date: window.appSettings.formatDateTime(new Date(row.created_at), false) })}
                        </div>
                    </div>
                </div>
            )
        },
        // A client's invoices are all theirs, so they don't need the client column.
        ...(isClient ? [] : [{
            key: 'client',
            label: t('Client'),
            render: (_: any, row: Invoice) => row.client ? (
                <div className="flex items-center gap-2">
                    <Avatar className="h-7 w-7 shrink-0">
                        <AvatarImage src={row.client.avatar} className="object-cover" />
                        <AvatarFallback><img src="/images/avatar/avatar.png" className="h-full w-full object-cover rounded-full" /></AvatarFallback>
                    </Avatar>
                    <div>
                        <div className="text-sm font-medium text-ds-fg">{row.client.name}</div>
                    </div>
                </div>
            ) : (
                <span className="text-sm text-ds-fg-placeholder">—</span>
            )
        }]),
        {
            key: 'project',
            label: t('Project'),
            render: (_: any, row: Invoice) => row.project ? (
                
                    <div className="text-sm text-ds-fg-secondary">{row.project?.title || ''}</div>

            ) : (
                <span className="text-sm text-ds-fg-placeholder">—</span>
            )
        },
        {
            // Total, with what's still due underneath: one column instead of three.
            key: 'total_amount',
            label: t('Amount'),
            sortable: true,
            render: (value: number, row: Invoice) => (
                <div className="whitespace-nowrap">
                    <div className="text-sm font-semibold tabular-nums text-ds-fg">{formatCurrency(value)}</div>
                    {row.balance_due > 0 && row.status !== 'paid' && row.status !== 'cancelled' ? (
                        <div className="text-xs font-medium tabular-nums text-ds-danger-fg">{formatCurrency(row.balance_due)} {t('due')}</div>
                    ) : row.paid_amount > 0 ? (
                        <div className="text-xs text-ds-success-fg">{t('Paid')}</div>
                    ) : null}
                </div>
            )
        },
        {
            key: 'status',
            label: t('Status'),
            sortable: true,
            render: (value: string, row: Invoice) => (
                <div className="flex items-center gap-2">
                    {/* {row.is_overdue && <AlertTriangle className="h-4 w-4 text-red-500" />} */}
                    <StatusBadge tone={STATUS_TONE[value] ?? 'neutral'}>
                        {t(value?.replace(/_/g, ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' '))}
                    </StatusBadge>
                </div>
            )
        },
        {
            key: 'due_date',
            label: t('Due Date'),
            sortable: true,
            render: (value: string, row: Invoice) => (
                <div>
                    <div className={`flex items-center gap-1.5 whitespace-nowrap text-sm ${row.is_overdue ? 'font-medium text-ds-danger-fg' : 'text-ds-fg-secondary'}`}>
                        <Calendar className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                        <span>{window.appSettings.formatDateTime(new Date(value), false)}</span>
                    </div>
                    {row.is_overdue && (
                        <div className="mt-0.5 flex items-center gap-1 text-xs text-ds-danger-fg">
                            <AlertTriangle className="h-3 w-3" aria-hidden="true" />
                            {t('{{count}} days overdue', { count: row.days_overdue })}
                        </div>
                    )}
                </div>
            )
        },
        // Paying is why a client opens this page, so it's a visible button, not a row icon.
        ...(isClient ? [{
            key: 'pay',
            label: '',
            render: (_: any, row: Invoice) => canPay(row) ? (
                <Button size="sm" onClick={() => handleAction('pay', row)}>
                    <CreditCard />
                    {t('Pay now')}
                </Button>
            ) : null
        }] : [])
    ];

    const actions = [
        {
            label: t('View'),
            icon: 'Eye',
            action: 'view',
        },
        {
            label: t('Edit'),
            icon: 'Edit',
            action: 'edit',
            condition: (row: Invoice) => ['owner', 'manager'].includes(userWorkspaceRole) && row.status === 'draft'
        },
        {
            label: t('Send'),
            icon: 'Send',
            action: 'send',
            condition: (row: Invoice) => ['owner', 'manager'].includes(userWorkspaceRole) && row.status === 'draft' && emailNotificationsEnabled
        },
        {
            label: t('Copy Payment Link'),
            icon: 'Link',
            action: 'copy-payment-link',
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
            label: t('Delete'),
            icon: 'Trash2',
            action: 'delete',
            // Same permission as the delete route: managers create invoices, the owner deletes them.
            condition: (row: Invoice) => (auth?.permissions || []).includes('invoice_delete') && row.status === 'draft'
        }
    ];

    const STATUS_TONE: Record<string, 'neutral' | 'info' | 'success' | 'warning' | 'danger'> = {
        draft: 'neutral', sent: 'info', paid: 'success', partial_paid: 'warning', overdue: 'danger', cancelled: 'neutral',
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
            icon: <Download className="h-4 w-4" />,
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
            icon: <Plus className="h-4 w-4" />,
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
            <StatStrip
                className="mb-4"
                items={[
                    { key: 'total', label: t('Total Invoiced'), value: formatCurrency(totalAmount || 0) },
                    { key: 'paid', label: t('Total Paid'), value: formatCurrency(paidAmount || 0), tone: 'success' },
                    { key: 'unpaid', label: t('Total Unpaid'), value: formatCurrency(outstandingAmount || 0), tone: 'warning' },
                    { key: 'unpaid-count', label: t('Unpaid Invoices'), value: outstandingCount || 0, tone: 'info' },
                    { key: 'overdue', label: t('Overdue'), value: overdueCount || 0, tone: 'danger' },
                ]}
            />

            {/* Status Tabs */}
            <div className="mb-4 rounded-xl border border-ds-border bg-ds-surface shadow-ds-xs">

                {/* Row 1: Search + Per Page + Filters */}
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ds-border-subtle px-4 py-3">
                    <form onSubmit={handleSearch} className="w-full sm:w-72" role="search">
                        <SearchInput
                            placeholder={t('Search invoices...')}
                            value={searchTerm}
                            onChange={setSearchTerm}
                        />
                    </form>
                    <div className="flex items-center gap-2">
                    <Label className="text-xs font-normal text-ds-fg-muted">{t('Per Page')}:</Label>
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
                            variant={hasActiveFilters() ? 'secondary' : 'outline'}
                            onClick={() => setShowFilters(!showFilters)}
                            aria-expanded={showFilters}
                            aria-controls="invoice-filters"
                        >
                            <Filter className="h-4 w-4" />
                            {showFilters ? t('Hide Filters') : t('Filters')}
                            {hasActiveFilters() && (
                                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs text-primary-foreground">
                                    {activeFilterCount()}
                                    <span className="sr-only"> {t('applied')}</span>
                                </span>
                            )}
                        </Button>
                    </div>
                </div>

                {/* Row 2: Status Tabs with icons + counts */}
                <div className="flex items-center gap-1 overflow-x-auto px-4 pt-1 pb-0" role="group" aria-label={t('Status')}>
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
                            type="button"
                            aria-pressed={selectedStatus === tab.value}
                            onClick={() => {
                                setSelectedStatus(tab.value);
                                router.get(route('invoices.index'), buildParams({ page: 1 }, { status: tab.value }), { preserveState: false, preserveScroll: false });
                            }}
                            className={`flex cursor-pointer items-center gap-1.5 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ds-border-focus ${
                                selectedStatus === tab.value
                                    ? 'border-brand-600 text-ds-fg-accent'
                                    : 'border-transparent text-ds-fg-muted hover:border-ds-border-strong hover:text-ds-fg'
                            }`}
                        >
                            {tab.icon}
                            {tab.label}
                            <span className={`rounded-full px-1.5 py-0.5 text-xs font-medium tabular-nums ${
                                selectedStatus === tab.value ? 'bg-brand-soft text-brand-soft-fg' : 'bg-ds-neutral-bg text-ds-fg-muted'
                            }`}>
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </div>

                {/* Filters panel */}
                {showFilters && (
                    <div id="invoice-filters" className="mx-4 mb-4 mt-3 rounded-lg border border-ds-border-subtle bg-ds-surface-subtle p-4">
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
                            <Button variant="ghost" onClick={() => {
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
                <div className="overflow-hidden rounded-xl border border-ds-border bg-ds-surface shadow-ds-xs">
                    {(invoices?.data?.length ?? 0) === 0 ? (
                        hasActiveFilters() ? (
                            <EmptyState
                                icon={Search}
                                title={t('No invoices found')}
                                description={t('Try another search, or clear the filters.')}
                                secondaryAction={{
                                    label: t('Reset Filters'),
                                    onClick: () => {
                                        setSelectedProject('all'); setSelectedClient('all'); setSelectedStatus('all'); setSearchTerm('');
                                        const params: Record<string, number> = { page: 1 };
                                        if (filters?.per_page) params.per_page = filters.per_page;
                                        router.get(route('invoices.index'), params, { preserveState: false, preserveScroll: false });
                                    },
                                }}
                            />
                        ) : (
                            <EmptyState
                                icon={FileText}
                                title={t('No invoices yet')}
                                description={isClient
                                    ? t('Invoices sent to you will appear here.')
                                    : t('Create your first invoice and send it directly to your client.')}
                                action={['owner', 'manager', 'member'].includes(userWorkspaceRole)
                                    ? { label: t('Create Invoice'), icon: <Plus className="h-4 w-4" />, onClick: () => router.get(route('invoices.create')) }
                                    : undefined}
                            />
                        )
                    ) : (
                    <div>
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
                    )}
                    {/* Pagination - for list view */}
                    {invoices?.links && (invoices?.data?.length ?? 0) > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-ds-border-subtle bg-ds-surface-subtle px-4 py-3">
                            <div className="text-sm text-ds-fg-muted">
                                {t('Showing')} <span className="font-medium text-ds-fg">{invoices?.from || 0}</span> {t('to')} <span className="font-medium text-ds-fg">{invoices?.to || 0}</span> {t('of')} <span className="font-medium text-ds-fg">{invoices?.total || 0}</span> {t('invoices')}
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
                                            className={isTextLink ? "px-3" : "size-8"}
                                            aria-current={link.active ? 'page' : undefined}
                                            disabled={!link.url}
                                            onClick={() => {
                                                if (!link.url) return;
                                                const pageNum = new URL(link.url).searchParams.get('page');
                                                router.get(route('invoices.index'), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
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

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => {
                    setIsDeleteModalOpen(false);
                    setInvoiceToDelete(null);
                }}
                onConfirm={handleDeleteConfirm}
                itemName={invoiceToDelete?.invoice_number || ''}
                entityName={t('invoice')}
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