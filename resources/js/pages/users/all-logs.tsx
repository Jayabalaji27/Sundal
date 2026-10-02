import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Search, Eye, Trash2, MonitorSmartphone, User, Mail, MapPin, Globe, Cpu, Clock, Wifi, Building2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import { CrudTable } from '@/components/CrudTable';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { TableColumn, TableAction } from '@/types/crud';
import { type BreadcrumbItem } from '@/types';
import { toast } from 'sonner';
export default function AllUserLogs() {
  const { t } = useTranslation();
  const { loginHistories, filters = {}, flash, auth, isSaasMode } = usePage().props as any;
  
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [selectedHistory, setSelectedHistory] = useState<any>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [itemToDelete, setItemToDelete] = useState<any>(null);

  // Handle flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
    }
    if (flash?.error) {
      toast.error(flash.error);
    }
    if (flash?.warning) {
      toast.warning(flash.warning);
    }
  }, [flash]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get(route('users.all-logs'), { 
      search: searchTerm,
      per_page: filters.per_page || 10
    });
  };

  const handleViewDetails = async (historyId: number) => {
    setIsLoading(true);
    try {
      const response = await axios.get(route('login-histories.show', historyId));
      setSelectedHistory(response.data.loginHistory);
      setIsModalOpen(true);
    } catch (error) {
      console.error('Failed to fetch login history details:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const user = auth?.user;
  const userType = user?.type;
  const currentWorkspace = user?.current_workspace;

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('Dashboard'), href: route('dashboard') },
  ];

  if (isSaasMode && userType === 'superadmin') {
    breadcrumbs.push({ title: t('Companies'), href: route('companies.index') });
  } else {
    breadcrumbs.push({ title: t('Workspaces'), href: route('workspaces.index') });
  }

  breadcrumbs.push({ title: t('Login History') });

  const formatLocation = (details: any) => {
    if (!details) return 'Unknown';
    
    const parts = [];
    if (details.city && details.city !== 'Unknown') parts.push(details.city);
    if (details.regionName && details.regionName !== 'Unknown') parts.push(details.regionName);
    if (details.country && details.country !== 'Unknown') parts.push(details.country);
    
    return parts.length > 0 ? parts.join(', ') : 'Unknown';
  };

  const formatDevice = (details: any) => {
    if (!details) return 'Unknown';
    
    const browser = details.browser_name || 'Unknown';
    const os = details.os_name || 'Unknown';
    const deviceType = details.device_type || 'desktop';
    
    return `${browser} on ${os} (${deviceType})`;
  };

  // Define table columns for CrudTable
  const columns: TableColumn[] = [
    { 
      key: 'user', 
      label: t('User'),
      render: (value: any, row: any) => (
        <div>
          <div className="font-medium text-gray-900">
            {row.user?.name || 'Company'}
          </div>
          <div className="text-sm text-gray-500">
            {row.user?.email || 'company@example.com'}
          </div>
        </div>
      )
    },
    { 
      key: 'user.type', 
      label: t('User Type'),
      render: (_, row) => {
        // role_label = the user's workspace role (server-side); type is only a fallback
        const userType = row.user?.role_label || row.user?.type || '-';
        return userType.charAt(0).toUpperCase() + userType.slice(1);
      }
    },
    { 
      key: 'ip', 
      label: t('IP Address'),
      render: (value: string) => <span className="text-gray-900 font-mono">{value}</span>
    },
    { 
      key: 'created_at', 
      label: t('Login Date'),
      render: (value: string) => (
        <span className="text-gray-900">
          {window.appSettings.formatDateTime(new Date(value),true)}
        </span>
      )
    },
    { 
      key: 'details', 
      label: t('Details'),
      render: (value: any, row: any) => (
        <div>
          <div className="text-gray-900">
            {row.details?.browser_name || 'Chrome'}
          </div>
          <div className="text-sm text-gray-500">
            {row.details?.os_name || 'Windows'}
          </div>
        </div>
      )
    }
  ];

  // Define table actions
  const actions: TableAction[] = [
    {
      label: t('View'),
      icon: 'Eye',
      action: 'view',
      className: 'text-blue-500 hover:text-blue-700'
    },
    {
      label: t('Delete'),
      icon: 'Trash2', 
      action: 'delete',
      className: 'text-red-500 hover:text-red-700'
    }
  ];

  const handleAction = async (action: string, row: any) => {
    if (action === 'view') {
      handleViewDetails(row.id);
    } else if (action === 'delete') {
      setItemToDelete(row);
      setDeleteModalOpen(true);
    }
  };

  const handleDeleteConfirm = async () => {
    if (!itemToDelete) return;
    
    setDeleteModalOpen(false);
    setItemToDelete(null);
    
    // Use Inertia router for proper flash message handling
    router.delete(route('users.destroyLoginHistory', itemToDelete.id), {
      preserveScroll: true,
      onError: (errors) => {
        console.error('Failed to delete login history:', errors);
      }
    });
  };

  const handleDeleteCancel = () => {
    setDeleteModalOpen(false);
    setItemToDelete(null);
  };

  return (
    <PageTemplate 
      title={t("Login History")} 
      description={t("User login history logs")}
      url="/companies/all-logs"
      breadcrumbs={breadcrumbs}
      noPadding
    >
      {/* Search section */}
      <div className="bg-white rounded-lg shadow mb-4">
        <div className="p-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <form onSubmit={handleSearch} className="flex gap-2">
              <div className="relative w-full sm:w-64">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder={t("Search users, emails, IPs...")}
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
            
            <div className="flex items-center gap-2">
              <Label className="text-xs text-muted-foreground">{t("Per Page:")}</Label>
              <Select 
                value={filters.per_page?.toString() || "10"} 
                onValueChange={(value) => {
                  router.get(route('users.all-logs'), { 
                    search: searchTerm,
                    per_page: parseInt(value) 
                  });
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
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={loginHistories?.data || []}
          from={loginHistories?.from || 1}
          onAction={handleAction}
          permissions={[]} // Add appropriate permissions if needed
          showActions={true}
        />

        {/* Pagination section */}
        <div className="p-4 border-t flex flex-wrap items-center justify-between gap-3 bg-[#F0F0F1] dark:bg-gray-800">
          <div className="text-sm text-muted-foreground">
            {t("Showing")} <span className="font-medium">{loginHistories?.from || 0}</span> {t("to")} <span className="font-medium">{loginHistories?.to || 0}</span> {t("of")} <span className="font-medium">{loginHistories?.total || 0}</span> {t("login records")}
          </div>

          <div className="flex flex-wrap gap-1">
            {loginHistories?.links?.map((link: any, i: number) => {
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

      {/* Login History Details Modal */}
      <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
        <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
          <DialogHeader className="px-6 pt-6 pb-4 border-b">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-primary/10 rounded-lg">
                <MonitorSmartphone className="h-5 w-5 text-primary" />
              </div>
              <DialogTitle className="text-xl font-semibold">{t('Login Details')}</DialogTitle>
            </div>
          </DialogHeader>

          {selectedHistory && (
            <div className="px-6 py-4 pb-6 space-y-4">
              {/* User & Email */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <User className="h-4 w-4" />
                    {t('User Name')}
                  </label>
                  <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{selectedHistory.user?.name || '-'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <Mail className="h-4 w-4" />
                    {t('Email')}
                  </label>
                  <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{selectedHistory.user?.email || '-'}</p>
                </div>
              </div>

              {/* Login Time & IP */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <Clock className="h-4 w-4" />
                    {t('Login Time')}
                  </label>
                  <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{window.appSettings.formatDateTime(new Date(selectedHistory.created_at),true)}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <Globe className="h-4 w-4" />
                    {t('IP Address')}
                  </label>
                  <p className="mt-1 text-sm font-mono font-medium text-gray-900 dark:text-white">{selectedHistory.ip || '-'}</p>
                </div>
              </div>

              {/* Country & City */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <MapPin className="h-4 w-4" />
                    {t('Country')}
                  </label>
                  <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{selectedHistory.details?.country || '-'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <MapPin className="h-4 w-4" />
                    {t('City')}
                  </label>
                  <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                    {[selectedHistory.details?.city, selectedHistory.details?.regionName].filter(Boolean).join(', ') || '-'}
                  </p>
                </div>
              </div>

              {/* Browser & OS */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <MonitorSmartphone className="h-4 w-4" />
                    {t('Browser')}
                  </label>
                  <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{selectedHistory.details?.browser_name || '-'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <Cpu className="h-4 w-4" />
                    {t('Operating System')}
                  </label>
                  <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white capitalize">{selectedHistory.details?.os_name || '-'}</p>
                </div>
              </div>

              {/* Device Type & Timezone */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <MonitorSmartphone className="h-4 w-4" />
                    {t('Device Type')}
                  </label>
                  <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white capitalize">{selectedHistory.details?.device_type || 'Desktop'}</p>
                </div>
                {selectedHistory.details?.timezone && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                      <Clock className="h-4 w-4" />
                      {t('Timezone')}
                    </label>
                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{selectedHistory.details.timezone}</p>
                  </div>
                )}
              </div>

              {/* ISP & Organization */}
              {(selectedHistory.details?.isp || selectedHistory.details?.org) && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {selectedHistory.details?.isp && (
                    <div>
                      <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                        <Wifi className="h-4 w-4" />
                        {t('ISP')}
                      </label>
                      <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{selectedHistory.details.isp}</p>
                    </div>
                  )}
                  {selectedHistory.details?.org && (
                    <div>
                      <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                        <Building2 className="h-4 w-4" />
                        {t('Organization')}
                      </label>
                      <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{selectedHistory.details.org}</p>
                    </div>
                  )}
                </div>
              )}

              {/* Referrer */}
              {(selectedHistory.details?.referrer_host || selectedHistory.details?.referrer_path) && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {selectedHistory.details?.referrer_host && (
                    <div>
                      <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                        <Globe className="h-4 w-4" />
                        {t('Referrer Host')}
                      </label>
                      <p className="mt-1 text-sm font-mono font-medium text-gray-900 dark:text-white">{selectedHistory.details.referrer_host}</p>
                    </div>
                  )}
                  {selectedHistory.details?.referrer_path && (
                    <div>
                      <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                        <Globe className="h-4 w-4" />
                        {t('Referrer Path')}
                      </label>
                      <p className="mt-1 text-sm font-mono font-medium text-gray-900 dark:text-white break-all">{selectedHistory.details.referrer_path}</p>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Modal */}
      <CrudDeleteModal
        isOpen={deleteModalOpen}
        onClose={handleDeleteCancel}
        onConfirm={handleDeleteConfirm}
        itemName={itemToDelete?.user?.name || itemToDelete?.user?.email || 'this login record'}
        entityName={t('Login History')}
      />
    </PageTemplate>
  );
}