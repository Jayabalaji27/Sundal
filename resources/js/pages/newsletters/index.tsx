import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Filter, Search, Plus, Edit, Trash2, Download, ToggleLeft, ToggleRight } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { DatePicker } from '@/components/ui/date-picker';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

export default function Newsletters() {
  const { t } = useTranslation();
  const { auth, newsletters, filters: pageFilters = {}, flash } = usePage().props as any;
  
  // State
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [startDate, setStartDate] = useState<Date | undefined>(pageFilters.start_date ? new Date(pageFilters.start_date) : undefined);
  const [endDate, setEndDate] = useState<Date | undefined>(pageFilters.end_date ? new Date(pageFilters.end_date) : undefined);
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [showFilters, setShowFilters] = useState(false);
  
  // Modal state
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentNewsletter, setCurrentNewsletter] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');
  
  // Handle flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
    }
    if (flash?.error) {
      toast.error(flash.error);
    }
  }, [flash]);
  
  // Check if any filters are active
  const hasActiveFilters = () => {
    return selectedStatus !== 'all' || searchTerm !== '' || startDate !== undefined || endDate !== undefined;
  };
  
  // Count active filters
  const activeFilterCount = () => {
    return (selectedStatus !== 'all' ? 1 : 0) + 
           (searchTerm ? 1 : 0) + 
           (startDate ? 1 : 0) + 
           (endDate ? 1 : 0);
  };
  
  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters();
  };
  
  const applyFilters = () => {
    const params: any = { page: 1 };
    
    if (searchTerm) {
      params.search = searchTerm;
    }
    
    if (selectedStatus !== 'all') {
      params.status = selectedStatus;
    }
    
    if (startDate) {
      params.start_date = startDate.toISOString().split('T')[0];
    }
    
    if (endDate) {
      params.end_date = endDate.toISOString().split('T')[0];
    }
    
    if (pageFilters.per_page) {
      params.per_page = pageFilters.per_page;
    }
    
    router.get(route('newsletters.index'), params, { preserveState: true, preserveScroll: true });
  };
  
  const handleStatusFilter = (value: string) => {
    setSelectedStatus(value);
    
    const params: any = { page: 1 };
    
    if (searchTerm) {
      params.search = searchTerm;
    }
    
    if (value !== 'all') {
      params.status = value;
    }
    
    if (startDate) {
      params.start_date = startDate.toISOString().split('T')[0];
    }
    
    if (endDate) {
      params.end_date = endDate.toISOString().split('T')[0];
    }
    
    if (pageFilters.per_page) {
      params.per_page = pageFilters.per_page;
    }
    
    router.get(route('newsletters.index'), params, { preserveState: true, preserveScroll: true });
  };
  
  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    
    const params: any = { 
      sort_field: field, 
      sort_direction: direction, 
      page: 1 
    };
    
    if (searchTerm) {
      params.search = searchTerm;
    }
    
    if (selectedStatus !== 'all') {
      params.status = selectedStatus;
    }
    
    if (startDate) {
      params.start_date = startDate.toISOString().split('T')[0];
    }
    
    if (endDate) {
      params.end_date = endDate.toISOString().split('T')[0];
    }
    
    if (pageFilters.per_page) {
      params.per_page = pageFilters.per_page;
    }
    
    router.get(route('newsletters.index'), params, { preserveState: true, preserveScroll: true });
  };
  
  const handleAddNew = () => {
    setCurrentNewsletter(null);
    setFormMode('create');
    setIsFormModalOpen(true);
  };
  
  const handleEdit = (newsletter: any) => {
    setCurrentNewsletter(newsletter);
    setFormMode('edit');
    setIsFormModalOpen(true);
  };
  
  const handleDelete = (newsletter: any) => {
    setCurrentNewsletter(newsletter);
    setIsDeleteModalOpen(true);
  };
  
  const handleToggleStatus = (newsletter: any) => {
    toast.loading(t('Updating status...'));
    
    router.put(route('newsletters.toggle-status', newsletter.id), {}, {
      onSuccess: () => {
        toast.dismiss();
        if (flash?.success) {
          toast.success(flash.success);
        }
      },
      onError: () => {
        toast.dismiss();
        toast.error(t('Failed to update status'));
      }
    });
  };
  
  const handleFormSubmit = (formData: any) => {
    if (formMode === 'create') {
      toast.loading(t('Creating newsletter subscription...'));
      
      router.post(route('newsletters.store'), formData, {
        onSuccess: () => {
          setIsFormModalOpen(false);
          toast.dismiss();
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(t('Failed to create newsletter subscription'));
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating newsletter subscription...'));
      
      router.put(route('newsletters.update', currentNewsletter.id), formData, {
        onSuccess: () => {
          setIsFormModalOpen(false);
          toast.dismiss();
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(t('Failed to update newsletter subscription'));
        }
      });
    }
  };
  
  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting newsletter subscription...'));
    
    router.delete(route('newsletters.destroy', currentNewsletter.id), {
      onSuccess: () => {
        setIsDeleteModalOpen(false);
        toast.dismiss();
      },
      onError: () => {
        toast.dismiss();
        toast.error(t('Failed to delete newsletter subscription'));
      }
    });
  };
  
  const handleResetFilters = () => {
    setSelectedStatus('all');
    setSearchTerm('');
    setStartDate(undefined);
    setEndDate(undefined);
    setShowFilters(false);
    
    router.get(route('newsletters.index'), { 
      page: 1, 
      per_page: pageFilters.per_page 
    }, { preserveState: true, preserveScroll: true });
  };
  
  const handleExport = () => {
    const params = new URLSearchParams();
    
    if (searchTerm) params.append('search', searchTerm);
    if (selectedStatus !== 'all') params.append('status', selectedStatus);
    if (startDate) params.append('start_date', startDate.toISOString().split('T')[0]);
    if (endDate) params.append('end_date', endDate.toISOString().split('T')[0]);
    
    window.open(route('newsletters.export') + '?' + params.toString());
  };

  const pageActions = [
    {
      label: t('Export'),
      icon: <Download className="h-4 w-4 mr-2" />,
      variant: 'outline',
      onClick: handleExport
    },
    {
      label: t('Add Newsletter'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default',
      onClick: handleAddNew
    }
  ];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Newsletters') }
  ];

  const columns = [
    { 
      key: 'email', 
      label: t('Email'), 
      sortable: true,
      render: (value: string) => <span className="font-medium">{value}</span>
    },
    { 
      key: 'status', 
      label: t('Status'), 
      sortable: true,
      render: (value: string) => (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
          value === 'subscribed' 
            ? 'bg-green-100 text-green-800' 
            : 'bg-red-100 text-red-800'
        }`}>
          {value === 'subscribed' ? t('Subscribed') : t('Unsubscribed')}
        </span>
      )
    },
    { 
      key: 'source', 
      label: t('Source'), 
      render: (value: string) => <span className="capitalize">{value.replace('_', ' ')}</span>
    },
    { 
      key: 'created_at', 
      label: t('Created At'), 
      sortable: true,
      render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
    }
  ];

  return (
    <PageTemplate 
      title={t("Newsletter Management")} 
      url="/newsletters"
      actions={pageActions}
      breadcrumbs={breadcrumbs}
      noPadding
    >
      {/* Search and filters section */}
      <div className="bg-white rounded-lg shadow mb-4">
        <div className="p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
              <form onSubmit={handleSearch} className="flex gap-2">
                <div className="relative w-full sm:w-64">
                  <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder={t("Search by email...")}
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-9"
                  />
                </div>
                <Button type="submit" size="sm">
                  <Search className="h-4 w-4 mr-1.5" />
                  {t("Search")}
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
              <Label className="text-xs text-muted-foreground">{t("Per Page:")}</Label>
              <Select 
                value={pageFilters.per_page?.toString() || "10"} 
                onValueChange={(value) => {
                  const params: any = { page: 1, per_page: parseInt(value) };
                  
                  if (searchTerm) params.search = searchTerm;
                  if (selectedStatus !== 'all') params.status = selectedStatus;
                  if (startDate) params.start_date = startDate.toISOString().split('T')[0];
                  if (endDate) params.end_date = endDate.toISOString().split('T')[0];
                  
                  router.get(route('newsletters.index'), params, { preserveState: true, preserveScroll: true });
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
                  <Label>{t("Status")}</Label>
                  <Select value={selectedStatus} onValueChange={handleStatusFilter}>
                    <SelectTrigger className="w-40">
                      <SelectValue placeholder={t("All Status")} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">{t("All Status")}</SelectItem>
                      <SelectItem value="subscribed">{t("Subscribed")}</SelectItem>
                      <SelectItem value="unsubscribed">{t("Unsubscribed")}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                
                <div className="space-y-2">
                  <Label>{t("Start Date")}</Label>
                  <DatePicker
                    selected={startDate}
                    onSelect={setStartDate}
                    onChange={(date) => setStartDate(date)}
                  />
                </div>
                
                <div className="space-y-2">
                  <Label>{t("End Date")}</Label>
                  <DatePicker
                    selected={endDate}
                    onSelect={setEndDate}
                    onChange={(date) => setEndDate(date)}
                  />
                </div>
                
                <div className="flex gap-2">
                  <Button variant="default" size="sm" className="h-9" onClick={applyFilters}>
                    {t("Apply Filters")}
                  </Button>
                  
                  <Button 
                    variant="outline" 
                    size="sm"
                    className="h-9"
                    onClick={handleResetFilters}
                    disabled={!hasActiveFilters()}
                  >
                    {t("Reset Filters")}
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Content section */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-gray-50">
                {columns.map((column) => (
                  <th 
                    key={column.key} 
                    className="px-4 py-3 text-left font-medium text-gray-500 cursor-pointer hover:bg-gray-100"
                    onClick={() => column.sortable && handleSort(column.key)}
                  >
                    <div className="flex items-center">
                      {column.label}
                      {column.sortable && (
                        <span className="ml-1">
                          {pageFilters.sort_field === column.key ? (
                            pageFilters.sort_direction === 'asc' ? '↑' : '↓'
                          ) : ''}
                        </span>
                      )}
                    </div>
                  </th>
                ))}
                <th className="px-4 py-3 text-right font-medium text-gray-500">
                  {t("Actions")}
                </th>
              </tr>
            </thead>
            <tbody>
              {newsletters?.data?.map((newsletter: any) => (
                <tr key={newsletter.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800">
                  {columns.map((column) => (
                    <td key={`${newsletter.id}-${column.key}`} className="px-4 py-3">
                      {column.render ? column.render(newsletter[column.key], newsletter) : newsletter[column.key]}
                    </td>
                  ))}
                  <td className="px-4 py-3 text-right">
                    <div className="flex justify-end gap-1">
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button 
                            variant="ghost" 
                            size="icon" 
                            onClick={() => handleToggleStatus(newsletter)}
                            className={newsletter.status === 'subscribed' ? 'text-green-600 hover:text-green-800' : 'text-red-600 hover:text-red-800'}
                          >
                            {newsletter.status === 'subscribed' ? <ToggleRight className="h-4 w-4" /> : <ToggleLeft className="h-4 w-4" />}
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                          {newsletter.status === 'subscribed' ? t('Unsubscribe') : t('Subscribe')}
                        </TooltipContent>
                      </Tooltip>
                      
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button 
                            variant="ghost" 
                            size="icon" 
                            onClick={() => handleEdit(newsletter)}
                            className="text-amber-500 hover:text-amber-700"
                          >
                            <Edit className="h-4 w-4" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t("Edit")}</TooltipContent>
                      </Tooltip>
                      
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button 
                            variant="ghost" 
                            size="icon"
                            className="text-red-500 hover:text-red-700"
                            onClick={() => handleDelete(newsletter)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t("Delete")}</TooltipContent>
                      </Tooltip>
                    </div>
                  </td>
                </tr>
              ))}
              
              {(!newsletters?.data || newsletters.data.length === 0) && (
                <tr>
                  <td colSpan={columns.length + 1} className="px-4 py-8 text-center text-gray-500">
                    {t("No newsletter subscriptions found")}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination section */}
        <div className="p-4 border-t flex flex-wrap items-center justify-between gap-3">
          <div className="text-sm text-muted-foreground">
            {t("Showing")} <span className="font-medium">{newsletters?.from || 0}</span> {t("to")} <span className="font-medium">{newsletters?.to || 0}</span> {t("of")} <span className="font-medium">{newsletters?.total || 0}</span> {t("subscriptions")}
          </div>

          <div className="flex flex-wrap gap-1">
            {newsletters?.links?.map((link: any, i: number) => {
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
      </div>

      {/* Form Modal */}
      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        formConfig={{
          fields: [
            { name: 'email', label: t('Email'), type: 'email', required: true },
            { 
              name: 'status', 
              label: t('Status'), 
              type: 'select',
              required: true,
              options: [
                { value: 'subscribed', label: t('Subscribed') },
                { value: 'unsubscribed', label: t('Unsubscribed') }
              ]
            }
          ],
          modalSize: 'md'
        }}
        initialData={currentNewsletter}
        title={
          formMode === 'create' 
            ? t('Add Newsletter Subscription') 
            : t('Edit Newsletter Subscription')
        }
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentNewsletter?.email || ''}
        entityName="newsletter subscription"
      />
    </PageTemplate>
  );
}