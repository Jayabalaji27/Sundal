import { PageTemplate } from '@/components/page-template';
import { CrudTable } from '@/components/CrudTable';
import { planRequestsConfig } from '@/config/crud/plan-requests';
import { useEffect, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Filter, Search, ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import { getImagePath } from '@/utils/helpers';

export default function PlanRequestsPage() {
  const { t } = useTranslation();
  const { flash, planRequests, filters: pageFilters = {}, auth, isMyRequests = false } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const userRole = auth.user?.type || auth.user?.role;
  
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
  const [filterValues, setFilterValues] = useState<Record<string, any>>(() => {
    const initial: Record<string, any> = {};
    planRequestsConfig.filters?.forEach(filter => {
      initial[filter.key] = pageFilters[filter.key] || 'all';
    });
    return initial;
  });
  const [showFilters, setShowFilters] = useState(false);
  // Approve/Reject take effect immediately, so they go through a confirm dialog.
  const [pendingDecision, setPendingDecision] = useState<{ action: 'approve' | 'reject'; item: any } | null>(null);
  
  useEffect(() => {
    const isDemo = (window as any).isDemo || false;
    if (flash?.success && !isDemo) {
      toast.success(flash.success);
    }
    if (flash?.error) {
      toast.error(flash.error);
    }
  }, [flash]);

  useEffect(() => {
    const initialFilters: Record<string, any> = {};
    planRequestsConfig.filters?.forEach(filter => {
      initialFilters[filter.key] = pageFilters[filter.key] || 'all';
    });
    setFilterValues(initialFilters);
  }, []);

  const confirmDecision = () => {
    if (!pendingDecision) return;
    const { action, item } = pendingDecision;
    setPendingDecision(null);
    if (action === 'approve') {
      router.post(route("plan-requests.approve", item.id), {}, {
        onError: () => {
          toast.error(t('Failed to approve plan request'));
        }
      });
    } else {
      router.post(route("plan-requests.reject", item.id), {}, {
        onError: () => {
          toast.error(t('Failed to reject plan request'));
        }
      });
    }
  };

  const handleAction = (action: string, item: any) => {
    const isDemo = (window as any).isDemo || false;
    if (action === 'approve' || action === 'reject') {
      setPendingDecision({ action, item });
    } else if (action === 'cancel') {
      router.delete(route("my-plan-requests.cancel", item.id), {
        onError: () => {
          toast.error(t('Failed to cancel plan request'));
        }
      });
    } else if (action === 'delete') {
      router.delete(route("plan-requests.destroy", item.id), {
        onError: () => {
          toast.error(t('Failed to delete plan request'));
        }
      });
    }
  };

  const routeName = isMyRequests ? 'my-plan-requests.index' : 'plan-requests.index';

  const buildParams = (
    overrides: Record<string, any> = {},
    filterOverrides: Record<string, any> = {}
  ) => {
    const params: any = { page: 1 };
    if (searchTerm) params.search = searchTerm;
    const merged = { ...filterValues, ...filterOverrides };
    Object.entries(merged).forEach(([k, v]) => {
      if (v && v !== 'all' && v !== '') params[k] = v;
    });
    if (pageFilters.per_page) params.per_page = pageFilters.per_page;
    if (pageFilters.sort_field) params.sort_field = pageFilters.sort_field;
    if (pageFilters.sort_direction) params.sort_direction = pageFilters.sort_direction;
    return { ...params, ...overrides };
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(route(routeName), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
  };

  const applyFilters = () => {
    router.get(route(routeName), buildParams({ page: 1 }), { preserveState: false, preserveScroll: false });
  };

  const handleFilterChange = (key: string, value: any) => {
    const newFilters = { ...filterValues, [key]: value };
    setFilterValues(newFilters);
    router.get(route(routeName), buildParams({ page: 1 }, { [key]: value }), { preserveState: false, preserveScroll: false });
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    router.get(route(routeName), buildParams({ sort_field: field, sort_direction: direction, page: 1 }), { preserveState: false, preserveScroll: false });
  };

  const handleResetFilters = () => {
    const resetFilters: Record<string, any> = {};
    planRequestsConfig.filters?.forEach(filter => {
      resetFilters[filter.key] = 'all';
    });
    setFilterValues(resetFilters);
    setSearchTerm('');
    setShowFilters(false);
    const params: any = { page: 1 };
    if (pageFilters.per_page) params.per_page = pageFilters.per_page;
    router.get(route(routeName), params, { preserveState: false, preserveScroll: false });
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

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Plans'), href: route('plans.index')},
    { title: t('Plan Requests') }
  ];

  const hasActiveFilters = () => {
    return Object.entries(filterValues).some(([key, value]) => {
      return value && value !== 'all' && value !== '';
    }) || searchTerm !== '';
  };

  const activeFilterCount = () => {
    return Object.entries(filterValues).filter(([key, value]) => {
      return value && value !== 'all' && value !== '';
    }).length + (searchTerm ? 1 : 0);
  };

  const getTableActions = () => {
    if (isMyRequests || userRole !== 'superadmin') {
      return [];
    } else {
      return [
        {
          label: t('Approve'),
          icon: 'Check',
          action: 'approve',
          className: 'text-green-600',
          condition: (item: any) => item.status === 'pending'
        },
        {
          label: t('Reject'),
          icon: 'X',
          action: 'reject',
          className: 'text-red-600',
          condition: (item: any) => item.status === 'pending'
        },
        {
          label: t('Delete'),
          icon: 'Trash2',
          action: 'delete',
          className: 'text-red-600',
          condition: (item: any) => item.status === 'cancelled'
        }
      ];
    }
  };

  const filteredActions = getTableActions();

  const customColumns = planRequestsConfig.table.columns.map(col => {
    if (col.key === 'user.name') {
      return {
        ...col,
        label: t('Company'),
        render: (_, row) => {
          const avatarUrl = row.user.avatar;
          return (
            <div className="flex items-center gap-3">
              <img
                src={avatarUrl}
                alt={row.user?.name || 'User'}
                className="h-10 w-10 rounded-full object-cover"
                onError={(e) => {
                  const target = e.target as HTMLImageElement;
                  target.src = getImagePath('users/avatar.png');
                }}
              />
              <div className="font-medium">{row.user?.name || '-'}</div>
            </div>
          );
        }
      };
    } else if (col.key === 'plan.name') {
      return {
        ...col,
        label: t(col.label),
        render: (_, row) => {
          const planName = row.plan?.name;
          if (!planName) return '-';

          return (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 capitalize">
                {planName}
            </span>
          );
        }
      };
    } else if (col.key === 'status') {
      return {
        ...col,
        label: t(col.label),
        render: (value) => {
          const statusColors: Record<string, string> = {
            pending:  'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
            approved: 'bg-green-50 text-green-700 ring-green-600/20',
            rejected: 'bg-red-50 text-red-700 ring-red-600/20'
          };
          return (
            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset capitalize ${statusColors[value] || 'bg-gray-50 text-gray-700 ring-gray-600/20'}`}>
              {t(value)}
            </span>
          );
        }
      };
    } else {
      return {
        ...col,
        label: t(col.label)
      };
    }
  });

  return (
    <PageTemplate 
      title={t('Plan Requests')} 
      url="/plan-requests"
      breadcrumbs={breadcrumbs}
      noPadding
    >
      <div className="bg-white rounded-lg border shadow mb-4">
        <div className="p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
              <form onSubmit={handleSearch} className="flex gap-2">
                <div className="relative w-full sm:w-64">
                  <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder={t('Search plan requests...')}
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
              
              {planRequestsConfig.filters && planRequestsConfig.filters.length > 0 && (
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
              )}
            </div>
            
            <div className="flex items-center gap-2">
              <Label className="text-xs text-muted-foreground">{t('Per Page')}:</Label>
              <Select 
                value={pageFilters.per_page?.toString() || "10"} 
                onValueChange={(value) => {
                  router.get(route(routeName), buildParams({ page: 1, per_page: parseInt(value) }), { preserveState: false, preserveScroll: false });
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
          
          {showFilters && planRequestsConfig.filters && planRequestsConfig.filters.length > 0 && (
            <div className="w-full mt-3 p-4 bg-gray-50 border rounded-md">
              <div className="flex flex-wrap gap-4 items-end">
                {planRequestsConfig.filters.map((filter) => (
                  <div key={filter.key} className="space-y-2">
                    <Label>{t(filter.label)}</Label>
                    <Select 
                      value={filterValues[filter.key] || ''} 
                      onValueChange={(value) => handleFilterChange(filter.key, value)}
                    >
                      <SelectTrigger className="w-40">
                        <SelectValue placeholder={t('All') + ' ' + t(filter.label)} />
                      </SelectTrigger>
                      <SelectContent>
                        {filter.options?.map((option) => (
                          <SelectItem key={option.value} value={option.value}>
                            {t(option.label)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                ))}
                
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

      <div className="bg-white rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={customColumns}
          actions={filteredActions}
          data={planRequests?.data || []}
          from={planRequests?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
          permissions={permissions}
          entityPermissions={planRequestsConfig.entity.permissions}
        />

        <div className="p-4 border-t flex items-center justify-between bg-[#F0F0F1] dark:bg-gray-800">
          <div className="text-sm text-muted-foreground">
            {t('Showing')} <span className="font-medium">{planRequests?.from || 0}</span> {t('to')} <span className="font-medium">{planRequests?.to || 0}</span> {t('of')} <span className="font-medium">{planRequests?.total || 0}</span> {t('plan requests')}
          </div>
          
          <div className="flex gap-1">
            {planRequests?.links?.map((link: any, i: number) => {
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
                    router.get(route(routeName), buildParams({ page: pageNum ? parseInt(pageNum) : 1 }), { preserveState: false, preserveScroll: false });
                  }}
                >
                  {isTextLink ? t(label) : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                </Button>
              );
            })}
          </div>
        </div>
      </div>

      <Dialog open={!!pendingDecision} onOpenChange={(open) => { if (!open) setPendingDecision(null); }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {pendingDecision?.action === 'approve' ? t('Approve Plan Request') : t('Reject Plan Request')}
            </DialogTitle>
            <DialogDescription>
              {pendingDecision?.action === 'approve'
                ? t('Approve {{plan}} for {{name}}? Their plan changes immediately.', { plan: pendingDecision?.item?.plan?.name, name: pendingDecision?.item?.user?.name })
                : t('Reject the {{plan}} request from {{name}}? This cannot be undone.', { plan: pendingDecision?.item?.plan?.name, name: pendingDecision?.item?.user?.name })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setPendingDecision(null)}>
              {t('Cancel')}
            </Button>
            <Button
              variant={pendingDecision?.action === 'reject' ? 'destructive' : 'default'}
              onClick={confirmDecision}
            >
              {pendingDecision?.action === 'approve' ? t('Approve') : t('Reject')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}
