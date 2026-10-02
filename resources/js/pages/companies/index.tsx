// pages/companies/index.tsx
import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { Filter, Search, Plus, Eye, Edit, Trash2, KeyRound, Lock, Unlock, LayoutGrid, List, ExternalLink, Info, ArrowUpRight, CreditCard, Download, Upload, ChevronUp, ChevronDown, ChevronsUpDown, History } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from 'react-i18next';
import { DatePicker } from '@/components/ui/date-picker';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { UpgradePlanModal } from '@/components/UpgradePlanModal';
import { ImportModal } from '@/components/ImportModal';
import ViewPopup from './view';

export default function Companies() {
  const { t } = useTranslation();
  const { auth, companies, plans, filters: pageFilters = {}, isSaasMode = true, flash } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const getInitials = useInitials();
  
  // State
  const [activeView, setActiveView] = useState(pageFilters.view || 'list');
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [startDate, setStartDate] = useState<Date | undefined>(pageFilters.start_date ? new Date(pageFilters.start_date) : undefined);
  const [endDate, setEndDate] = useState<Date | undefined>(pageFilters.end_date ? new Date(pageFilters.end_date) : undefined);
  const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
  const [showFilters, setShowFilters] = useState(false);
  
  // Sync activeView with URL parameter when pageFilters change
  useEffect(() => {
    if (pageFilters.view && pageFilters.view !== activeView) {
      setActiveView(pageFilters.view);
    }
  }, [pageFilters.view]);
  
  // Modal state
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [isResetPasswordModalOpen, setIsResetPasswordModalOpen] = useState(false);
  const [isUpgradePlanModalOpen, setIsUpgradePlanModalOpen] = useState(false);
  const [isImportModalOpen, setIsImportModalOpen] = useState(false);
  const [isToggleStatusConfirmOpen, setIsToggleStatusConfirmOpen] = useState(false);
  const [isImpersonateConfirmOpen, setIsImpersonateConfirmOpen] = useState(false);
  const [companyToToggle, setCompanyToToggle] = useState<any>(null);
  const [companyToImpersonate, setCompanyToImpersonate] = useState<any>(null);
  const [currentCompany, setCurrentCompany] = useState<any>(null);
  const [availablePlans, setAvailablePlans] = useState<any[]>([]);
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
    const params: any = { page: 1, view: activeView };
    
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
    
    // Add per_page if it exists
    if (pageFilters.per_page) {
      params.per_page = pageFilters.per_page;
    }
    
    router.get(route('companies.index'), params, { preserveState: true, preserveScroll: true });
  };
  
  const handleDateFilter = (type: 'start' | 'end', date: Date | undefined) => {
    if (type === 'start') {
      setStartDate(date);
    } else {
      setEndDate(date);
    }
    
    const params: any = { page: 1, view: activeView };
    
    if (searchTerm) {
      params.search = searchTerm;
    }
    
    if (selectedStatus !== 'all') {
      params.status = selectedStatus;
    }
    
    if (type === 'start' && date) {
      params.start_date = date.toISOString().split('T')[0];
    } else if (type === 'start' && !date) {
      // Remove start_date if date is cleared
    } else if (startDate) {
      params.start_date = startDate.toISOString().split('T')[0];
    }
    
    if (type === 'end' && date) {
      params.end_date = date.toISOString().split('T')[0];
    } else if (type === 'end' && !date) {
      // Remove end_date if date is cleared
    } else if (endDate) {
      params.end_date = endDate.toISOString().split('T')[0];
    }
    
    // Add per_page if it exists
    if (pageFilters.per_page) {
      params.per_page = pageFilters.per_page;
    }
    
    router.get(route('companies.index'), params, { preserveState: true, preserveScroll: true });
  };
  
  const handleStatusFilter = (value: string) => {
    setSelectedStatus(value);
    
    const params: any = { page: 1, view: activeView };
    
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
    
    // Add per_page if it exists
    if (pageFilters.per_page) {
      params.per_page = pageFilters.per_page;
    }
    
    router.get(route('companies.index'), params, { preserveState: true, preserveScroll: true });
  };
  
  // Render sort icon function like in CrudTable
  const renderSortIcon = (column: any) => {
    if (!column.sortable) return null;

    if (pageFilters.sort_field === column.key) {
      return pageFilters.sort_direction === 'asc' ?
        <ChevronUp className="ml-1 h-4 w-4" /> :
        <ChevronDown className="ml-1 h-4 w-4" />;
    }

    // Always show the double arrow for sortable columns when not sorted
    return <ChevronsUpDown className="ml-1 h-4 w-4 opacity-50" />;
  };
  
  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    
    const params: any = { 
      sort_field: field, 
      sort_direction: direction, 
      page: 1,
      view: activeView
    };
    
    // Add search and filters
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
    
    // Add per_page if it exists
    if (pageFilters.per_page) {
      params.per_page = pageFilters.per_page;
    }
    
    router.get(route('companies.index'), params, { preserveState: true, preserveScroll: true });
  };
  
  const handleAction = (action: string, company: any) => {
    setCurrentCompany(company);
    
    switch (action) {
      case 'login-as':
        setCompanyToImpersonate(company);
        setIsImpersonateConfirmOpen(true);
        break;
      case 'company-info':
        setIsViewModalOpen(true);
        break;
      case 'upgrade-plan':
        handleUpgradePlan(company);
        break;
      case 'plan-requests':
        if (permissions.includes('view-plan-requests')) {
          router.get(route('companies.plan-requests', company.id));
        }
        break;
      case 'plan-orders':
        if (permissions.includes('view-plan-orders')) {
          router.get(route('companies.plan-orders', company.id));
        }
        break;
      case 'reset-password':
        setIsResetPasswordModalOpen(true);
        break;
      case 'toggle-status':
        setCompanyToToggle(company);
        setIsToggleStatusConfirmOpen(true);
        break;
      case 'edit':
        setFormMode('edit');
        setIsFormModalOpen(true);
        break;
      case 'delete':
        setIsDeleteModalOpen(true);
        break;
      default:
        break;
    }
  };
  
  const handleAddNew = () => {
    setCurrentCompany(null);
    setFormMode('create');
    setIsFormModalOpen(true);
  };
  
  const handleFormSubmit = (formData: any) => {
    if (formMode === 'create') {
      toast.loading(t('Creating company...'));
      
      router.post(route('companies.store'), formData, {
        onSuccess: () => {
          setIsFormModalOpen(false);
          toast.dismiss();
          // if (flash?.success) {
          //   toast.success(flash.success);
          // }
        },
        onError: (errors) => {
          toast.dismiss();
          if (flash?.error) {
            toast.error(flash.error);
          } else {
            toast.error(t('Failed to create company') + `: ${Object.values(errors).join(', ')}`);
          }
        }
      });
    } else if (formMode === 'edit') {
      toast.loading(t('Updating company...'));
      
      router.put(route('companies.update', currentCompany.id), formData, {
        onSuccess: () => {
          setIsFormModalOpen(false);
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
            toast.error(t('Failed to update company') + `: ${Object.values(errors).join(', ')}`);
          }
        }
      });
    }
  };
  
  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting company...'));
    
    router.delete(route("companies.destroy", currentCompany.id), {
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
          toast.error(t('Failed to delete company') + `: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };
  
  const handleResetPasswordConfirm = (data: { password: string }) => {
    toast.loading(t('Resetting password...'));
    
    router.put(route('companies.reset-password', currentCompany.id), data, {
      onSuccess: () => {
        setIsResetPasswordModalOpen(false);
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
          toast.error(t('Failed to reset password') + `: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };
  
  const handleToggleStatus = (company: any) => {
    toast.loading(t('Updating status...'));
    
    router.put(route('companies.toggle-status', company.id), {}, {
      onSuccess: () => {
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
          toast.error(t('Failed to update status') + `: ${Object.values(errors).join(', ')}`);
        }
      }
    });
  };

  const handleImpersonateConfirm = () => {
    if (companyToImpersonate) {
      window.open(route('impersonate.start', companyToImpersonate.id), '_blank');
    }
    setIsImpersonateConfirmOpen(false);
    setCompanyToImpersonate(null);
  };
  
  const handleResetFilters = () => {
    setSelectedStatus('all');
    setSearchTerm('');
    setStartDate(undefined);
    setEndDate(undefined);
    setShowFilters(false);
    
    router.get(route('companies.index'), { 
      page: 1, 
      per_page: pageFilters.per_page,
      view: activeView
    }, { preserveState: true, preserveScroll: true });
  };
  
  const handleUpgradePlan = (company: any) => {
    setCurrentCompany(company);
    
    // Fetch available plans
    toast.loading(t('Loading plans...'));
    
    fetch(route('companies.plans', company.id))
      .then(res => res.json())
      .then(data => {
        setAvailablePlans(data.plans);
        // Merge company details (including current_plan_duration) into currentCompany
        setCurrentCompany((prev: any) => ({ ...prev, ...data.company }));
        setIsUpgradePlanModalOpen(true);
        toast.dismiss();
      })
      .catch(err => {
        toast.dismiss();
        toast.error(t('Failed to load plans'));
      });
  };
  
  const handleUpgradePlanConfirm = (planId: number, duration: string) => {
    toast.loading(t('Upgrading plan...'));
    
    // Use Inertia router to handle the request
    router.put(route('companies.upgrade-plan', currentCompany.id), { 
      plan_id: planId,
      duration: duration
    }, {
      onSuccess: () => {
        setIsUpgradePlanModalOpen(false);
        toast.dismiss();
        if (flash?.success) {
          toast.success(flash.success);
        }
        router.reload();
      },
      onError: () => {
        toast.dismiss();
        if (flash?.error) {
          toast.error(flash.error);
        } else {
          toast.error(t('Failed to upgrade plan'));
        }
      }
    });
  };

  const handleUserLogsHistory = () => {
    // Navigate to user logs history page
    router.get(route('users.all-logs'));
  };

  // Define page actions
  const pageActions = [
    {
      icon: <History className="h-4 w-4" />,
      variant: 'outline',
      size: 'icon',
      tooltip: t('Login History'),
      onClick: () => handleUserLogsHistory()
    },
    {
      label: t('Export'),
      icon: <Download className="h-4 w-4 mr-2" />,
      variant: 'outline',
      onClick: async () => {
        try {
          const params = new URLSearchParams();
          if (searchTerm) params.append('search', searchTerm);
          if (selectedStatus !== 'all') params.append('status', selectedStatus);
          if (startDate) params.append('start_date', startDate.toISOString().split('T')[0]);
          if (endDate) params.append('end_date', endDate.toISOString().split('T')[0]);
          
          const response = await fetch(route('companies.export', 'companies') + '?' + params.toString());
          if (!response.ok) throw new Error('Export failed');
          const blob = await response.blob();
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `companies_export_${new Date().toISOString().split('T')[0]}.xlsx`;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);
          toast.success(t('Export completed successfully'));
        } catch (error) {
          toast.error(t('Export failed'));
        }
      }
    },
    {
      label: t('Import'),
      icon: <Upload className="h-4 w-4 mr-2" />,
      variant: 'outline',
      onClick: () => setIsImportModalOpen(true)
    },
    {
      label: t('Add Company'),
      icon: <Plus className="h-4 w-4 mr-2" />,
      variant: 'default',
      onClick: () => handleAddNew()
    }
    
  ];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Companies') }
  ];

  // Define table columns for list view
  const columns = [
    { 
      key: 'name', 
      label: t('Name'), 
      sortable: true,
      render: (value: any, row: any) => {
        return (
          <div className="flex items-center gap-3">
            {row.avatar ? (
              <img
                src={row.avatar}
                alt={row.name}
                className="h-10 w-10 rounded-full object-cover shadow-sm"
                onError={(e) => {
                  const target = e.target as HTMLImageElement;
                  target.src = (window as any).baseUrl + '/images/avatar/avatar.png';
                }}
              />
            ) : (
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white">
                {getInitials(row.name)}
              </div>
            )}
            <div>
              <button
                className="font-medium text-left hover:text-primary hover:underline cursor-pointer"
                onClick={() => handleAction('company-info', row)}
              >
                {row.name}
              </button>
              <div className="text-sm text-muted-foreground">{row.email}</div>
            </div>
          </div>
        );
      }
    },
    ...(isSaasMode ? [{ 
      key: 'plan_name', 
      label: t('Plan'),
      render: (value: string) => (
        <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 capitalize">
          {value}
        </span>
      )
    }] : []),
    { 
      key: 'created_at', 
      label: t('Created At'), 
      sortable: true,
      render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
    }
  ];

  return (
    <PageTemplate 
      title={t("Companies")} 
      url="/companies"
      actions={pageActions}
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
                    placeholder={t("Search companies...")}
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
              <div className="border rounded-md p-0.5 mr-2">
                <Button 
                  size="sm" 
                  variant={activeView === 'list' ? "default" : "ghost"}
                  className="h-7 px-2"
                  onClick={() => {
                    setActiveView('list');
                    const params: any = { view: 'list', page: 1 };
                    
                    // Only add parameters that have values
                    if (searchTerm) params.search = searchTerm;
                    if (selectedStatus !== 'all') params.status = selectedStatus;
                    if (startDate) params.start_date = startDate.toISOString().split('T')[0];
                    if (endDate) params.end_date = endDate.toISOString().split('T')[0];
                    if (pageFilters.per_page) params.per_page = pageFilters.per_page;
                    if (pageFilters.sort_field) params.sort_field = pageFilters.sort_field;
                    if (pageFilters.sort_direction) params.sort_direction = pageFilters.sort_direction;
                    
                    router.get(route('companies.index'), params, { preserveState: true, preserveScroll: true });
                  }}
                >
                  <List className="h-4 w-4" />
                </Button>
                <Button 
                  size="sm" 
                  variant={activeView === 'grid' ? "default" : "ghost"}
                  className="h-7 px-2"
                  onClick={() => {
                    setActiveView('grid');
                    const params: any = { view: 'grid', page: 1 };
                    
                    // Only add parameters that have values
                    if (searchTerm) params.search = searchTerm;
                    if (selectedStatus !== 'all') params.status = selectedStatus;
                    if (startDate) params.start_date = startDate.toISOString().split('T')[0];
                    if (endDate) params.end_date = endDate.toISOString().split('T')[0];
                    if (pageFilters.per_page) params.per_page = pageFilters.per_page;
                    if (pageFilters.sort_field) params.sort_field = pageFilters.sort_field;
                    if (pageFilters.sort_direction) params.sort_direction = pageFilters.sort_direction;
                    
                    router.get(route('companies.index'), params, { preserveState: true, preserveScroll: true });
                  }}
                >
                  <LayoutGrid className="h-4 w-4" />
                </Button>
              </div>
              
              <Label className="text-xs text-muted-foreground">{t("Per Page:")}</Label>
              <Select 
                value={pageFilters.per_page?.toString() || "10"} 
                onValueChange={(value) => {
                  const params: any = { page: 1, per_page: parseInt(value), view: activeView };
                  
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
                  
                  router.get(route('companies.index'), params, { preserveState: true, preserveScroll: true });
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
                  <Select 
                    value={selectedStatus} 
                    onValueChange={handleStatusFilter}
                  >
                    <SelectTrigger className="w-40">
                      <SelectValue placeholder={t("All Status")} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">{t("All Status")}</SelectItem>
                      <SelectItem value="active">{t("Active")}</SelectItem>
                      <SelectItem value="inactive">{t("Inactive")}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                
                <div className="space-y-2">
                  <Label>{t("Start Date")}</Label>
                  <DatePicker
                    selected={startDate}
                    onSelect={(date) => handleDateFilter('start', date)}
                    onChange={(date) => handleDateFilter('start', date)}
                  />
                </div>
                
                <div className="space-y-2">
                  <Label>{t("End Date")}</Label>
                  <DatePicker
                    selected={endDate}
                    onSelect={(date) => handleDateFilter('end', date)}
                    onChange={(date) => handleDateFilter('end', date)}
                  />
                </div>
                
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
          )}
        </div>
      </div>

      {/* Content section */}
      {activeView === 'list' ? (
        <div className="bg-white rounded-lg shadow overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F0F0F1] dark:bg-gray-800 border-b">
                  {columns.map((column) => (
                    <th 
                      key={column.key} 
                      className={`px-4 py-3 text-left font-medium text-gray-500 ${
                        column.sortable ? 'cursor-pointer select-none' : ''
                      }`}
                      onClick={() => column.sortable && handleSort(column.key)}
                    >
                      <div className="flex items-center">
                        {column.label}
                        {column.sortable && renderSortIcon(column)}
                      </div>
                    </th>
                  ))}
                  <th className="px-4 py-3 text-right font-medium text-gray-500">
                    {t("Actions")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {companies?.data?.map((company: any) => (
                  <tr key={company.id} className="border-b hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800">
                    {columns.map((column) => (
                      <td key={`${company.id}-${column.key}`} className="px-4 py-3">
                        {column.render ? column.render(company[column.key], company) : company[column.key]}
                      </td>
                    ))}
                    <td className="px-4 py-3 text-right">
                      <div className="flex justify-end gap-1">
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button 
                              variant="ghost" 
                              size="icon" 
                              onClick={() => handleAction('login-as', company)}
                              className="text-blue-500 hover:text-blue-700"
                            >
                              <ArrowUpRight className="h-4 w-4" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>{t("Login as Company")}</TooltipContent>
                        </Tooltip>
                        
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button 
                              variant="ghost" 
                              size="icon" 
                              onClick={() => handleAction('company-info', company)}
                              className="text-blue-500 hover:text-blue-700"
                            >
                              <Info className="h-4 w-4" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>{t("Company Info")}</TooltipContent>
                        </Tooltip>
                        
                        {isSaasMode && (
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Button 
                                variant="ghost" 
                                size="icon" 
                                onClick={() => handleAction('upgrade-plan', company)}
                                className="text-amber-500 hover:text-amber-700"
                              >
                                <CreditCard className="h-4 w-4" />
                              </Button>
                            </TooltipTrigger>
                            <TooltipContent>{t("Upgrade Plan")}</TooltipContent>
                          </Tooltip>
                        )}
                        

                        
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button 
                              variant="ghost" 
                              size="icon" 
                              onClick={() => handleAction('reset-password', company)}
                              className="text-blue-500 hover:text-blue-700"
                            >
                              <KeyRound className="h-4 w-4" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>{t("Reset Password")}</TooltipContent>
                        </Tooltip>
                        
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button 
                              variant="ghost" 
                              size="icon" 
                              onClick={() => handleAction('toggle-status', company)}
                              className="text-amber-500 hover:text-amber-700"
                            >
                              {company.status === 'active' ? <Lock className="h-4 w-4" /> : <Unlock className="h-4 w-4" />}
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>{company.status === 'active' ? t("Disable Login") : t("Enable Login")}</TooltipContent>
                        </Tooltip>
                        
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button 
                              variant="ghost" 
                              size="icon" 
                              onClick={() => handleAction('edit', company)}
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
                              onClick={() => handleAction('delete', company)}
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
                
                {(!companies?.data || companies.data.length === 0) && (
                  <tr>
                    <td colSpan={columns.length + 1} className="px-4 py-8 text-center text-gray-500">
                      {t("No companies found")}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination section */}
          <div className="p-4 border-t flex flex-wrap items-center justify-between gap-3 bg-[#F0F0F1] dark:bg-gray-800">
            <div className="text-sm text-muted-foreground">
              {t("Showing")} <span className="font-medium">{companies?.from || 0}</span> {t("to")} <span className="font-medium">{companies?.to || 0}</span> {t("of")} <span className="font-medium">{companies?.total || 0}</span> {t("companies")}
            </div>

            <div className="flex flex-wrap gap-1">
              {companies?.links?.map((link: any, i: number) => {
                // Check if the link is "Next" or "Previous" to use text instead of icon
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
                      if (link.url) {
                        const url = new URL(link.url, window.location.origin);
                        url.searchParams.set('view', activeView);
                        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
                      }
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
        <div>
          {/* Grid View */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {companies?.data?.map((company: any) => (
              <Card key={company.id} className="group relative overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300">
                {/* Status Badge */}
                <div className="absolute top-4 right-4 z-10">
                  <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${
                    company.status === 'active'
                      ? 'bg-green-50 text-green-700 ring-green-600/20'
                      : 'bg-red-50 text-red-700 ring-red-600/20'
                  }`}>
                    {company.status === 'active' ? t('Active') : t('Inactive')}
                  </span>
                </div>

                {/* Card Content */}
                <div className="p-6">
                  {/* Company Header */}
                  <div className="flex items-start space-x-4 mb-6">
                    <div className="relative">
                      {company.avatar ? (
                        <img
                          src={company.avatar}
                          alt={company.name}
                          className="h-14 w-14 rounded-full object-cover shadow-sm"
                          onError={(e) => {
                            const target = e.target as HTMLImageElement;
                            target.src = (window as any).baseUrl + '/images/avatar/avatar.png';
                          }}
                        />
                      ) : (
                        <div className="h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center text-xl font-bold text-primary shadow-sm">
                          {getInitials(company.name)}
                        </div>
                      )}
                    </div>
                    <div className="flex-1 min-w-0 pr-12">
                      <button
                        className="text-lg font-semibold text-gray-900 dark:text-white mb-1 truncate text-left w-full block hover:text-primary transition-colors cursor-pointer"
                        onClick={() => handleAction('company-info', company)}
                      >
                        {company.name}
                      </button>
                      <p className="text-sm text-gray-500 dark:text-gray-400 truncate">
                        {company.email}
                      </p>
                    </div>
                  </div>

                  {/* Plan Information */}
                  {isSaasMode && (
                    <div className="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 mb-6">
                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center">
                          <CreditCard className="h-4 w-4 text-primary mr-2" />
                          <span className="text-sm font-medium text-gray-900 dark:text-white capitalize">
                            {company.plan_name}
                          </span>
                        </div>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleAction('upgrade-plan', company)}
                          className="h-6 px-2 text-xs text-primary hover:text-primary hover:bg-primary/10"
                        >
                          {t("Upgrade")}
                        </Button>
                      </div>
                      {company.plan_expiry_date && (
                        <div className="text-xs text-gray-500 dark:text-gray-400">
                          {t("Expires")}: {window.appSettings?.formatDateTime(company.plan_expiry_date, false) || new Date(company.plan_expiry_date).toLocaleDateString()}
                        </div>
                      )}
                    </div>
                  )}

                  {/* Quick Actions */}
                  <div className="flex items-center justify-between">
                    <div className="flex space-x-1">
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button 
                            variant="ghost" 
                            size="sm" 
                            onClick={() => handleAction('login-as', company)}
                            className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                          >
                            <ArrowUpRight className="h-4 w-4" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t("Login as Company")}</TooltipContent>
                      </Tooltip>
                      
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button 
                            variant="ghost" 
                            size="sm" 
                            onClick={() => handleAction('company-info', company)}
                            className="h-8 w-8 p-0 text-gray-600 hover:text-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
                          >
                            <Info className="h-4 w-4" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t("Company Info")}</TooltipContent>
                      </Tooltip>
                      
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <Button 
                            variant="ghost" 
                            size="sm" 
                            onClick={() => handleAction('edit', company)}
                            className="h-8 w-8 p-0 text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-900/20"
                          >
                            <Edit className="h-4 w-4" />
                          </Button>
                        </TooltipTrigger>
                        <TooltipContent>{t("Edit")}</TooltipContent>
                      </Tooltip>
                    </div>
                    
                    {/* More Actions Dropdown */}
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-gray-400 hover:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800">
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="1"></circle>
                            <circle cx="12" cy="5" r="1"></circle>
                            <circle cx="12" cy="19" r="1"></circle>
                          </svg>
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" className="w-48 z-50" sideOffset={5}>
                        {isSaasMode && permissions.includes('view-plan-requests') && (
                          <DropdownMenuItem onClick={() => handleAction('plan-requests', company)}>
                            <CreditCard className="h-4 w-4 mr-2" />
                            <span>{t("My Plan Requests")}</span>
                          </DropdownMenuItem>
                        )}
                        {isSaasMode && permissions.includes('view-plan-orders') && (
                          <DropdownMenuItem onClick={() => handleAction('plan-orders', company)}>
                            <CreditCard className="h-4 w-4 mr-2" />
                            <span>{t("My Plan Orders")}</span>
                          </DropdownMenuItem>
                        )}
                        <DropdownMenuItem onClick={() => handleAction('reset-password', company)}>
                          <KeyRound className="h-4 w-4 mr-2" />
                          <span>{t("Reset Password")}</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => handleAction('toggle-status', company)}>
                          {company.status === 'active' ? 
                            <Lock className="h-4 w-4 mr-2" /> : 
                            <Unlock className="h-4 w-4 mr-2" />
                          }
                          <span>{company.status === 'active' ? t("Disable Login") : t("Enable Login")}</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onClick={() => handleAction('delete', company)} className="text-red-600 focus:text-red-600">
                          <Trash2 className="h-4 w-4 mr-2" />
                          <span>{t("Delete")}</span>
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>
              </Card>
            ))}
            
            {(!companies?.data || companies.data.length === 0) && (
              <div className="col-span-full">
                <div className="text-center py-12">
                  <div className="mx-auto h-24 w-24 text-gray-300 dark:text-gray-600 mb-4">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" className="w-full h-full">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                  </div>
                  <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-2">{t("No companies found")}</h3>
                  <p className="text-gray-500 dark:text-gray-400 mb-6">{t("Get started by creating your first company")}</p>
                  <Button onClick={handleAddNew} className="inline-flex items-center">
                    <Plus className="h-4 w-4 mr-2" />
                    {t("Add Company")}
                  </Button>
                </div>
              </div>
            )}
          </div>
          
          {/* Pagination for grid view */}
          <div className="mt-6 bg-[#F0F0F1] dark:bg-gray-800 p-4 rounded-lg shadow flex flex-wrap items-center justify-between gap-3">
            <div className="text-sm text-muted-foreground">
              {t("Showing")} <span className="font-medium">{companies?.from || 0}</span> {t("to")} <span className="font-medium">{companies?.to || 0}</span> {t("of")} <span className="font-medium">{companies?.total || 0}</span> {t("companies")}
            </div>

            <div className="flex flex-wrap gap-1">
              {companies?.links?.map((link: any, i: number) => {
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
                      if (link.url) {
                        const url = new URL(link.url, window.location.origin);
                        url.searchParams.set('view', activeView);
                        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
                      }
                    }}
                  >
                    {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                  </Button>
                );
              })}
            </div>
          </div>
        </div>
      )}

      {/* Form Modal */}
      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={(data) => {
          // If login_enabled is false, remove password field
          if (data.login_enabled === false) {
            delete data.password;
          }
          // Set status based on login_enabled
          data.status = data.login_enabled ? 'active' : 'inactive';
          
          // Remove login_enabled field as it's not needed in the backend
          delete data.login_enabled;
          handleFormSubmit(data);
        }}
        submitButtonText={formMode === 'create' ? t('Create') : t('Update')}
        formConfig={{
          fields: [
            { name: 'name', label: t('Company Name'), type: 'text', required: formMode !== 'view', placeholder: t('Enter company name') },
            { name: 'email', label: t('Email'), type: 'email', required: formMode !== 'view', placeholder: t('Enter company email') },
            { 
              name: 'login_enabled', 
              label: t('Enable Login'), 
              type: 'switch', 
              defaultValue: true,
              conditional: (mode: string) => mode === 'create'
            },
            { 
              name: 'password', 
              label: t('Password'), 
              type: 'password', 
              placeholder: t('Enter password'),
              required: true,
              conditional: (mode: string, formData: any) => {
                return mode === 'create' && formData.login_enabled;
              }
            },
          ],
          modalSize: 'lg'
        }}
        initialData={{
          ...currentCompany,
          login_enabled: currentCompany?.status === 'active'
        }}
        title={
          formMode === 'create' 
            ? t('Add Company') 
            : formMode === 'edit' 
              ? t('Edit Company') 
              : t('View Company')
        }
        mode={formMode}
      />

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentCompany?.name || ''}
        entityName="company"
      />

      {/* Reset Password Modal */}
      <CrudFormModal
        isOpen={isResetPasswordModalOpen}
        onClose={() => setIsResetPasswordModalOpen(false)}
        onSubmit={handleResetPasswordConfirm}
        submitButtonText={t('Reset Password')}
        formConfig={{
          fields: [
            { name: 'password', label: t('New Password'), type: 'password', required: true, placeholder: t('Enter new password') },
          ],
          modalSize: 'sm'
        }}
        initialData={{}}
        title={t('Reset Password for') + ` ${currentCompany?.name || t('Company')}`}
        mode="edit"
      />
      
      {/* Upgrade Plan Modal */}
      <UpgradePlanModal
        isOpen={isUpgradePlanModalOpen}
        onClose={() => setIsUpgradePlanModalOpen(false)}
        onConfirm={handleUpgradePlanConfirm}
        plans={availablePlans}
        currentPlanId={currentCompany?.plan_id}
        currentPlanDuration={currentCompany?.current_plan_duration}
        companyName={currentCompany?.name || ''}
      />
      {/* Import Modal */}
      <ImportModal
        isOpen={isImportModalOpen}
        onClose={() => setIsImportModalOpen(false)}
        type="companies"
        title="Companies"
      />

      {/* View Modal */}
      <Dialog open={isViewModalOpen} onOpenChange={setIsViewModalOpen}>
        {currentCompany && (
          <ViewPopup
            record={currentCompany}
            isSaasMode={isSaasMode}
            onImpersonate={() => {
              setIsViewModalOpen(false);
              handleAction('login-as', currentCompany);
            }}
          />
        )}
      </Dialog>

      {/* Toggle Status Confirmation */}
      <Dialog open={isToggleStatusConfirmOpen} onOpenChange={setIsToggleStatusConfirmOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>{companyToToggle?.status === 'active' ? t('Suspend Company') : t('Reactivate Company')}</DialogTitle>
            <DialogDescription>
              {companyToToggle?.status === 'active'
                ? t('Are you sure you want to suspend {{name}}? They will not be able to log in.', { name: companyToToggle?.name })
                : t('Are you sure you want to reactivate {{name}}?', { name: companyToToggle?.name })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => { setIsToggleStatusConfirmOpen(false); setCompanyToToggle(null); }}>
              {t('Cancel')}
            </Button>
            <Button
              variant={companyToToggle?.status === 'active' ? 'destructive' : 'default'}
              onClick={() => {
                setIsToggleStatusConfirmOpen(false);
                if (companyToToggle) handleToggleStatus(companyToToggle);
                setCompanyToToggle(null);
              }}
            >
              {companyToToggle?.status === 'active' ? t('Suspend') : t('Reactivate')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Impersonate Confirmation */}
      <Dialog open={isImpersonateConfirmOpen} onOpenChange={setIsImpersonateConfirmOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>{t('Impersonate Company')}</DialogTitle>
            <DialogDescription>
              {t('You are about to log in as {{name}}. This will open in a new tab. Use the "Back to Admin" link to return.', { name: companyToImpersonate?.name })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => { setIsImpersonateConfirmOpen(false); setCompanyToImpersonate(null); }}>
              {t('Cancel')}
            </Button>
            <Button onClick={handleImpersonateConfirm}>
              {t('Impersonate')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}