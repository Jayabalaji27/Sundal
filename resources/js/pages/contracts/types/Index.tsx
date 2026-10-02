import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { FileText, Edit, Trash2, Lock, Search, X, ChevronDown, ChevronUp } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export default function ContractTypesIndex() {
    const { t } = useTranslation();
    const { auth, contractTypes, filters: pageFilters = {}, globalSettings } = usePage().props as any;
    const permissions = auth?.permissions || [];

    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [currentItem, setCurrentItem] = useState<any>(null);
    const [formMode, setFormMode] = useState<'create' | 'edit'>('create');
    const [formData, setFormData] = useState({ name: '', description: '', color: '#007bff', is_active: true });
    const [formErrors, setFormErrors] = useState<any>({});
    const [expandedDescriptions, setExpandedDescriptions] = useState<Set<number>>(new Set());

    const toggleDescription = (id: number) => {
        const next = new Set(expandedDescriptions);
        next.has(id) ? next.delete(id) : next.add(id);
        setExpandedDescriptions(next);
    };

    const canCreate = hasPermission(permissions, 'contract_type_create');
    const canEdit   = hasPermission(permissions, 'contract_type_update');
    const canDelete = hasPermission(permissions, 'contract_type_delete');

    const resetForm = () => {
        setFormData({ name: '', description: '', color: '#007bff', is_active: true });
        setFormErrors({});
        setFormMode('create');
        setCurrentItem(null);
    };

    const loadItemForEdit = (item: any) => {
        setFormData({
            name: item.name || '',
            description: item.description || '',
            color: item.color || '#007bff',
            is_active: item.is_active ?? true,
        });
        setFormMode('edit');
        setCurrentItem(item);
        setFormErrors({});
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('contract-types.index'), {
            page: 1,
            search: searchTerm || undefined,
            status: selectedStatus !== 'all' ? selectedStatus : undefined,
            per_page: pageFilters.per_page || 10,
            sort_field: pageFilters.sort_field || undefined,
            sort_direction: pageFilters.sort_direction || undefined,
        }, { preserveState: true, preserveScroll: true });
    };

    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedStatus('all');
        router.get(route('contract-types.index'), {
            page: 1,
            per_page: pageFilters.per_page || 10,
        }, { preserveState: true, preserveScroll: true });
    };

    const handleSort = (field: string) => {
        const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
        router.get(route('contract-types.index'), {
            sort_field: field,
            sort_direction: direction,
            page: 1,
            search: searchTerm || undefined,
            status: selectedStatus !== 'all' ? selectedStatus : undefined,
            per_page: pageFilters.per_page || 10,
        }, { preserveState: true, preserveScroll: true });
    };

    const handleAction = (action: string, item: any) => {
        switch (action) {
            case 'edit':
                loadItemForEdit(item);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                break;
            case 'delete':
                if (item.contracts_count > 0) {
                    toast.error(t('Cannot delete contract type that has contracts associated with it.'));
                    return;
                }
                setCurrentItem(item);
                setIsDeleteModalOpen(true);
                break;
            case 'toggle-status':
                handleToggleStatus(item);
                break;
        }
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setFormErrors({});

        const errors: any = {};
        if (!formData.name.trim()) errors.name = t('Name is required');
        if (Object.keys(errors).length > 0) {
            setFormErrors(errors);
            return;
        }

        if (formMode === 'create') {
            if (!globalSettings?.is_demo) toast.loading(t('Creating contract type...'));
            router.post(route('contract-types.store'), formData, {
                onSuccess: (page) => {
                    if (!globalSettings?.is_demo) toast.dismiss();
                    if (page.props.flash.success) {
                        toast.success(t(page.props.flash.success));
                        resetForm();
                    } else if (page.props.flash.error) {
                        toast.error(t(page.props.flash.error));
                    }
                },
                onError: (errors) => {
                    if (!globalSettings?.is_demo) toast.dismiss();
                    setFormErrors(errors);
                    toast.error(t('Please check the form for errors'));
                }
            });
        } else if (formMode === 'edit') {
            if (!globalSettings?.is_demo) toast.loading(t('Updating contract type...'));
            router.put(route('contract-types.update', currentItem.id), formData, {
                onSuccess: (page) => {
                    if (!globalSettings?.is_demo) toast.dismiss();
                    if (page.props.flash.success) {
                        toast.success(t(page.props.flash.success));
                        resetForm();
                    } else if (page.props.flash.error) {
                        toast.error(t(page.props.flash.error));
                    }
                },
                onError: (errors) => {
                    if (!globalSettings?.is_demo) toast.dismiss();
                    setFormErrors(errors);
                    toast.error(t('Please check the form for errors'));
                }
            });
        }
    };

    const handleDeleteConfirm = () => {
        if (!globalSettings?.is_demo) toast.loading(t('Deleting contract type...'));
        router.delete(route('contract-types.destroy', currentItem.id), {
            onSuccess: (page) => {
                setIsDeleteModalOpen(false);
                if (!globalSettings?.is_demo) toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: (errors) => {
                if (!globalSettings?.is_demo) toast.dismiss();
                toast.error(t('Failed to delete contract type'));
            }
        });
    };

    const handleToggleStatus = (item: any) => {
        const newStatus = item.is_active ? 'inactive' : 'active';
        if (!globalSettings?.is_demo) toast.loading(`${item.is_active ? t('Deactivating') : t('Activating')} ${t('contract type')}...`);
        router.put(route('contract-types.toggle-status', item.id), {}, {
            onSuccess: (page) => {
                if (!globalSettings?.is_demo) toast.dismiss();
                if (page.props.flash.success) {
                    toast.success(t(page.props.flash.success));
                } else if (page.props.flash.error) {
                    toast.error(t(page.props.flash.error));
                }
            },
            onError: () => {
                if (!globalSettings?.is_demo) toast.dismiss();
                toast.error(t('Failed to update contract type status'));
            }
        });
    };

    const isFilterActive = searchTerm || selectedStatus !== 'all';

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Contracts'), href: route('contracts.index') },
        { title: t('Contract Types') }
    ];

    return (
        <PageTemplate
            title={t('Contract Types')}
            description=""
            url="/contract-types"
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {/* Left: Form */}
                <div className="lg:col-span-1">
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 sticky top-4">
                        <div className="p-6 border-b border-gray-200 dark:border-gray-700">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                                {formMode === 'create' ? t('Add New Contract Type') : t('Edit Contract Type')}
                            </h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                {formMode === 'create'
                                    ? t('Fill in the details to create a new contract type')
                                    : t('Update the contract type details below')}
                            </p>
                        </div>

                        <form onSubmit={handleFormSubmit} className="p-6 space-y-4">
                            {/* Name */}
                            <div className="space-y-2">
                                <Label htmlFor="name">{t('Name')} <span className="text-red-500">*</span></Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder={t('Enter contract type name')}
                                    className={formErrors.name ? 'border-red-500' : ''}
                                    disabled={!canCreate && !canEdit}
                                />
                                {formErrors.name && <p className="text-sm text-red-500">{formErrors.name}</p>}
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="description">{t('Description')}</Label>
                                <Textarea
                                    id="description"
                                    value={formData.description}
                                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                    placeholder={t('Enter contract type description')}
                                    rows={3}
                                    disabled={!canCreate && !canEdit}
                                />
                            </div>

                            {/* Color */}
                            <div className="space-y-2">
                                <Label htmlFor="color">{t('Color')}</Label>
                                <div className="flex items-center gap-3">
                                    <Input
                                        id="color"
                                        type="color"
                                        value={formData.color}
                                        onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                                        className="h-10 w-14 cursor-pointer p-1"
                                        disabled={!canCreate && !canEdit}
                                    />
                                    <Input
                                        type="text"
                                        value={formData.color}
                                        onChange={(e) => setFormData({ ...formData, color: e.target.value })}
                                        className="flex-1 font-mono uppercase"
                                        disabled={!canCreate && !canEdit}
                                        placeholder="#000000"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                    />
                                </div>
                            </div>

                            {/* Status */}
                            <div className="space-y-2">
                                <Label htmlFor="status">{t('Status')} <span className="text-red-500">*</span></Label>
                                <Select
                                    value={formData.is_active ? 'active' : 'inactive'}
                                    onValueChange={(value) => setFormData({ ...formData, is_active: value === 'active' })}
                                    disabled={!canCreate && !canEdit}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Select status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">{t('Active')}</SelectItem>
                                        <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Actions */}
                            <div className="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                {(canCreate || canEdit) && (
                                    <Button type="submit" className="flex-1">
                                        {formMode === 'create' ? t('Add Contract Type') : t('Update Contract Type')}
                                    </Button>
                                )}
                                {formMode === 'edit' && (
                                    <Button type="button" variant="outline" onClick={resetForm}>
                                        {t('Cancel')}
                                    </Button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>

                {/* Right: List */}
                <div className="lg:col-span-2 space-y-4">

                    {/* Search & Filters */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div className="space-y-4">
                            <div className="flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder={t('Search contract types...')}
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleSearch(e)}
                                        className="pl-10"
                                    />
                                </div>
                                <Button onClick={handleSearch} variant="default">
                                    {t('Search')}
                                </Button>
                                {isFilterActive && (
                                    <Button onClick={handleResetFilters} variant="outline">
                                        <X className="h-4 w-4 mr-2" />
                                        {t('Reset')}
                                    </Button>
                                )}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <Select value={selectedStatus} onValueChange={(value) => {
                                    setSelectedStatus(value);
                                    router.get(route('contract-types.index'), {
                                        page: 1,
                                        search: searchTerm || undefined,
                                        status: value !== 'all' ? value : undefined,
                                        per_page: pageFilters.per_page || 12,
                                        sort_field: pageFilters.sort_field || undefined,
                                        sort_direction: pageFilters.sort_direction || undefined,
                                    }, { preserveState: true, preserveScroll: true });
                                }}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('All Statuses')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All Statuses')}</SelectItem>
                                        <SelectItem value="active">{t('Active')}</SelectItem>
                                        <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    {/* Data Table */}
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        {(contractTypes?.data || []).length > 0 ? (
                            <>
                                <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                        {t('Contract Types')}
                                    </h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        {t('Manage contract types used for contracts')}
                                    </p>
                                </div>

                                {/* Desktop Table */}
                                <div className="hidden lg:block">
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead>
                                                <tr className="bg-[#F0F0F1] dark:bg-gray-900 border-t">
                                                    <th
                                                        className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 tracking-wider cursor-pointer select-none"
                                                        onClick={() => handleSort('name')}
                                                    >
                                                        <div className="flex items-center gap-1">
                                                            {t('Name')}
                                                            {pageFilters.sort_field === 'name'
                                                                ? pageFilters.sort_direction === 'asc' ? ' ↑' : ' ↓'
                                                                : <span className="opacity-40">↕</span>}
                                                        </div>
                                                    </th>
                                                    <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 tracking-wider">
                                                        {t('Status')}
                                                    </th>
                                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 tracking-wider">
                                                        {t('Actions')}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                {(contractTypes?.data || []).map((item: any) => (
                                                    <tr key={item.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                        <td className="px-4 py-4">
                                                            <div className="flex items-center">
                                                                <div
                                                                    className="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center"
                                                                    style={{ backgroundColor: item.color + '20', color: item.color }}
                                                                >
                                                                    <FileText className="h-5 w-5" />
                                                                </div>
                                                                <div className="ml-3">
                                                                    <div className="text-sm font-medium text-gray-900 dark:text-white">
                                                                        {item.name}
                                                                    </div>
                                                                    {item.description && (
                                                                        <div className="text-sm text-gray-500 dark:text-gray-400 max-w-xs">
                                                                            <div className={expandedDescriptions.has(item.id) ? '' : 'line-clamp-2'}>
                                                                                {item.description}
                                                                            </div>
                                                                            {item.description.length > 60 && (
                                                                                <button
                                                                                    onClick={() => toggleDescription(item.id)}
                                                                                    className="inline-flex items-center mt-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                                                                >
                                                                                    {expandedDescriptions.has(item.id) ? (
                                                                                        <><ChevronUp className="h-3 w-3 mr-1" />{t('Show less')}</>
                                                                                    ) : (
                                                                                        <><ChevronDown className="h-3 w-3 mr-1" />{t('Show more')}</>
                                                                                    )}
                                                                                </button>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td className="px-3 py-4">
                                                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${
                                                                item.is_active
                                                                    ? 'bg-green-50 text-green-700 ring-green-600/20'
                                                                    : 'bg-red-50 text-red-700 ring-red-600/20'
                                                            }`}>
                                                                {item.is_active ? t('Active') : t('Inactive')}
                                                            </span>
                                                        </td>

                                                        <td className="px-4 py-4 text-right">
                                                            <div className="flex items-center justify-end space-x-2">
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => handleAction('edit', item)}
                                                                        className="h-8 w-8 p-0 text-amber-500"
                                                                        title={t('Edit')}
                                                                    >
                                                                        <Edit className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                {canEdit && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => handleAction('toggle-status', item)}
                                                                        className={`h-8 w-8 p-0 ${item.is_active ? 'text-orange-500' : 'text-green-600'}`}
                                                                        title={item.is_active ? t('Deactivate') : t('Activate')}
                                                                    >
                                                                        <Lock className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                {canDelete && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() => handleAction('delete', item)}
                                                                        className="h-8 w-8 p-0 text-red-500"
                                                                        title={t('Delete')}
                                                                        disabled={item.contracts_count > 0}
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Mobile Cards */}
                                <div className="lg:hidden space-y-4 p-4">
                                    {(contractTypes?.data || []).map((item: any) => (
                                        <div
                                            key={item.id}
                                            className="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4"
                                        >
                                            <div className="flex items-start justify-between mb-3">
                                                <div className="flex items-start space-x-3">
                                                    <div
                                                        className="flex-shrink-0 h-10 w-10 rounded-lg flex items-center justify-center"
                                                        style={{ backgroundColor: item.color + '20', color: item.color }}
                                                    >
                                                        <FileText className="h-5 w-5" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <h3 className="text-sm font-medium text-gray-900 dark:text-white">{item.name}</h3>
                                                        {item.description && (
                                                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                                                {item.description}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex items-center space-x-1">
                                                    {canEdit && (
                                                        <Button variant="ghost" size="sm" onClick={() => handleAction('edit', item)} className="h-8 w-8 p-0 text-amber-500">
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canEdit && (
                                                        <Button variant="ghost" size="sm" onClick={() => handleAction('toggle-status', item)} className={`h-8 w-8 p-0 ${item.is_active ? 'text-orange-500' : 'text-green-600'}`}>
                                                            <Lock className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDelete && (
                                                        <Button variant="ghost" size="sm" onClick={() => handleAction('delete', item)} className="h-8 w-8 p-0 text-red-500" disabled={item.contracts_count > 0}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                            <div>
                                                <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">{t('Status')}</p>
                                                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${
                                                    item.is_active
                                                        ? 'bg-green-50 text-green-700 ring-green-600/20'
                                                        : 'bg-red-50 text-red-700 ring-red-600/20'
                                                }`}>
                                                    {item.is_active ? t('Active') : t('Inactive')}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Pagination */}
                                {contractTypes?.links && (
                                    <div className="border-t border-gray-200 dark:border-gray-700 p-4 flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
                                        <div className="text-sm text-muted-foreground">
                                            {t('Showing')} <span className="font-medium">{contractTypes?.from || 0}</span> {t('to')} <span className="font-medium">{contractTypes?.to || 0}</span> {t('of')} <span className="font-medium">{contractTypes?.total || 0}</span> {t('contract types')}
                                        </div>
                                        <div className="flex gap-1">
                                            {contractTypes?.links?.map((link: any, i: number) => {
                                                const isTextLink = link.label === '&laquo; Previous' || link.label === 'Next &raquo;';
                                                const label = link.label.replace('&laquo; ', '').replace(' &raquo;', '');
                                                return (
                                                    <Button
                                                        key={i}
                                                        variant={link.active ? 'default' : 'outline'}
                                                        size={isTextLink ? 'sm' : 'icon'}
                                                        className={isTextLink ? 'px-3' : 'h-8 w-8'}
                                                        disabled={!link.url}
                                                        onClick={() => {
                                                            if (!link.url) return;
                                                            const pageNum = new URL(link.url).searchParams.get('page');
                                                            router.get(route('contract-types.index'), {
                                                                page: pageNum ? parseInt(pageNum) : 1,
                                                                per_page: pageFilters.per_page || 10,
                                                                search: searchTerm || undefined,
                                                                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                                                                sort_field: pageFilters.sort_field || undefined,
                                                                sort_direction: pageFilters.sort_direction || undefined,
                                                            }, { preserveState: true, preserveScroll: true });
                                                        }}
                                                    >
                                                        {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                                                    </Button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="p-12 text-center">
                                <div className="mx-auto w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                                    <FileText className="h-8 w-8 text-gray-400" />
                                </div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-2">
                                    {t('No contract types found')}
                                </h3>
                                <p className="text-gray-500 dark:text-gray-400 mb-6 max-w-sm mx-auto">
                                    {isFilterActive
                                        ? t('No contract types match your search criteria. Try adjusting your filters.')
                                        : t('Create contract types to categorize your contracts.')}
                                </p>
                                {!isFilterActive && canCreate && (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        {t('Use the form on the left to add your first contract type.')}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={currentItem?.name || ''}
                entityName={t('contract type')}
                warningMessage={t('This contract type will be permanently deleted.')}
                additionalInfo={[
                    t('This action cannot be undone'),
                    t('Make sure no contracts are using this type')
                ]}
            />
        </PageTemplate>
    );
}
