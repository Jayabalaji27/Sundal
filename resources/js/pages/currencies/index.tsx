// pages/currencies/index.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Search } from 'lucide-react';
import ViewPopup from './view';

export default function CurrenciesPage() {
  const { t } = useTranslation();
  const { auth, currencys, filters: pageFilters = {}, flash } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit'>('create');

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(route('currencies.index'), {
      page: 1,
      search: searchTerm || undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    router.get(route('currencies.index'), {
      sort_field: field,
      sort_direction: direction,
      page: 1,
      search: searchTerm || undefined,
      per_page: pageFilters.per_page
    }, { preserveState: true, preserveScroll: true });
  };

  const handleAction = (action: string, item: any) => {
    setCurrentItem(item);
    switch (action) {
      case 'view':
        setIsViewModalOpen(true);
        break;
      case 'edit':
        setFormMode('edit');
        setIsFormModalOpen(true);
        break;
      case 'delete':
        setIsDeleteModalOpen(true);
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
      toast.loading(t('Creating currency...'));
      router.post(route('currencies.store'), formData, {
        onSuccess: () => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (flash?.success) toast.success(flash.success);
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(t('Failed to create currency') + `: ${Object.values(errors).join(', ')}`);
        }
      });
    } else {
      toast.loading(t('Updating currency...'));
      router.put(route('currencies.update', currentItem.id), formData, {
        onSuccess: () => {
          setIsFormModalOpen(false);
          toast.dismiss();
          if (flash?.success) toast.success(flash.success);
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(t('Failed to update currency') + `: ${Object.values(errors).join(', ')}`);
        }
      });
    }
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting currency...'));
    router.delete(route('currencies.destroy', currentItem.id), {
      onSuccess: () => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
        if (flash?.success) toast.success(flash.success);
      },
      onError: (errors) => {
        toast.dismiss();
        toast.error(t('Failed to delete currency') + `: ${Object.values(errors).join(', ')}`);
      }
    });
  };

  const pageActions = [];
  if (hasPermission(permissions, 'currency_create')) {
    pageActions.push({
      label: t('Add Currency'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default',
      onClick: () => handleAddNew()
    });
  }

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Currency') }
  ];

  const columns = [
    { key: 'name', label: t('Name'), sortable: true },
    { key: 'code', label: t('Code'), sortable: true },
    { key: 'symbol', label: t('Symbol'), sortable: true },
    {
      key: 'is_default',
      label: t('Default'),
      render: (value: boolean) => (
        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
          value
            ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20'
            : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        }`}>
          {value ? t('Yes') : t('No')}
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
      requiredPermission: 'currency_view_any'
    },
    {
      label: t('Edit'),
      icon: 'Edit',
      action: 'edit',
      className: 'text-amber-500 hover:text-amber-700',
      requiredPermission: 'currency_update'
    },
    {
      label: t('Delete'),
      icon: 'Trash2',
      action: 'delete',
      className: 'text-red-500 hover:text-red-700',
      requiredPermission: 'currency_delete',
      condition: (row: any) => !row.is_default
    }
  ];

  return (
    <PageTemplate
      title={t('Currency')}
      url="/currencies"
      actions={pageActions}
      breadcrumbs={breadcrumbs}
      noPadding
    >
      {/* Search section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg border shadow mb-4">
        <div className="p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <form onSubmit={handleSearch} className="flex gap-2">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder={t('Search currencies...')}
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
              <Label className="text-xs text-muted-foreground">{t('Per Page:')}</Label>
              <Select
                value={pageFilters.per_page?.toString() || '10'}
                onValueChange={(value) => {
                  router.get(route('currencies.index'), {
                    page: 1,
                    per_page: parseInt(value),
                    search: searchTerm || undefined
                  }, { preserveState: true, preserveScroll: true });
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
        </div>
      </div>

      {/* Table section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={currencys?.data || []}
          from={currencys?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={{
            view: 'currency_view_any',
            create: 'currency_create',
            edit: 'currency_update',
            delete: 'currency_delete'
          }}
        />

        {/* Pagination */}
        <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
          <div className="text-sm text-muted-foreground">
            {t('Showing')} <span className="font-medium">{currencys?.from || 0}</span> {t('to')} <span className="font-medium">{currencys?.to || 0}</span> {t('of')} <span className="font-medium">{currencys?.total || 0}</span> {t('currencies')}
          </div>
          <div className="flex gap-1">
            {currencys?.links?.map((link: any, i: number) => {
              const isTextLink = link.label === '&laquo; Previous' || link.label === 'Next &raquo;';
              const label = link.label.replace('&laquo; ', '').replace(' &raquo;', '');
              return (
                <Button
                  key={i}
                  variant={link.active ? 'default' : 'outline'}
                  size={isTextLink ? 'sm' : 'icon'}
                  className={isTextLink ? 'px-3' : 'h-8 w-8'}
                  disabled={!link.url}
                  onClick={() => link.url && router.get(link.url)}
                >
                  {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                </Button>
              );
            })}
          </div>
        </div>
      </div>

      {/* View Modal */}
      <Dialog open={isViewModalOpen} onOpenChange={setIsViewModalOpen}>
        {currentItem && <ViewPopup record={currentItem} />}
      </Dialog>

      {/* Form Modal */}
      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        formConfig={{
          fields: [
            { name: 'name', label: t('Currency Name'), type: 'text', required: true, placeholder: t('e.g. US Dollar, Euro') },
            { name: 'code', label: t('Currency Code'), type: 'text', required: true, placeholder: t('e.g. USD, EUR, GBP') },
            { name: 'symbol', label: t('Currency Symbol'), type: 'text', required: true, placeholder: t('e.g. $, €, £') },
            { name: 'description', label: t('Description'), type: 'textarea', placeholder: t('Enter currency description') },
            { name: 'is_default', label: '', type: 'checkbox', placeholder: t('Set as Default Currency') }
          ],
          modalSize: 'lg'
        }}
        initialData={currentItem}
        title={formMode === 'create' ? t('Add Currency') : t('Edit Currency')}
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="currency"
      />
    </PageTemplate>
  );
}
