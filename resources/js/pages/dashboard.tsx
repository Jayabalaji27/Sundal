import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import {
  RefreshCw, BarChart3, Download, Users, Activity, UserPlus, DollarSign,
  FolderOpen, CheckSquare, Clock, Bug, Receipt, FileText, Building2,
  TrendingUp, AlertTriangle, Calendar, Target, Wallet, CreditCard, Ticket, X,
  Settings as SettingsIcon, Globe, Shield, Info
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Progress } from '@/components/ui/progress';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from 'react-i18next';
import { formatCurrency } from '@/utils/currency';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { ActiveSprintsCard, AttentionCard, AttentionList, ProjectsAtRiskCard, RoleGreeting, RoleStats, groupByDue } from '@/components/dashboard/role-home';
import { StatStrip } from '@/components/stat-strip';

interface DashboardData {
  cards: Array<{
    value: number;
  }>;
  // Platform admin: the subscription business (SaaS mode).
  subscriptions?: { mrr: number; paying: number; trial: number; expired: number; renewals_due: number } | null;
  // Owner home: active projects whose health score isn't healthy.
  projectsAtRisk?: { id: number; title: string; score: number; status: 'at_risk' | 'critical'; reason: string | null }[] | null;
  projects?: {
    total: number;
    active: number;
    completed: number;
    overdue: number;
  };
  tasks?: {
    total: number;
    pending: number;
    inProgress: number;
    completed: number;
  };
  budgets?: {
    totalBudget: number;
    spent: number;
    remaining: number;
    utilization: number;
  };
  invoices?: {
    total: number;
    paid: number;
    pending: number;
    overdue: number;
  };
  recentActivities?: Array<{
    id: number;
    type: string;
    description: string;
    user: string;
    time: string;
  }>;
  recentCompanies?: Array<{
    id: number;
    name: string;
    email: string;
    plan?: string;
    registered_at: string;
  }>;
}

interface PageAction {
  label: string;
  icon: React.ReactNode;
  variant: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  onClick: () => void;
}

export default function Dashboard({ dashboardData, isSuperAdmin, isSaasMode = true, hasRoleDashboardAccess = false, userWorkspaceRole }: { dashboardData: DashboardData; isSuperAdmin?: boolean; isSaasMode?: boolean; hasRoleDashboardAccess?: boolean; userWorkspaceRole?: string }) {
  const { t } = useTranslation();
  const { auth } = usePage().props as any;
  // If super admin, render super admin dashboard
  if (isSuperAdmin) {
    return (
      <PageTemplate
        title={t('Dashboard')}
        url="/dashboard"
        actions={[
          // SaaS: Companies is in the sidebar and in the header's Create menu.
          ...(!isSaasMode ? [{
            label: t('Manage Companies'),
            icon: <Building2 className="h-4 w-4" />,
            variant: 'default' as const,
            onClick: () => router.visit(route('companies.index'))
          }] : []),
          {
            label: t('Refresh'),
            icon: <RefreshCw className="h-4 w-4" />,
            variant: 'outline',
            onClick: () => window.location.reload()
          }
        ]}
      >
        <div className="space-y-6">
          {isSaasMode && <RoleGreeting role="superadmin" name={auth?.user?.name} />}
          {/* SaaS: the subscription business at a glance (self-hosted keeps the count cards). */}
          {isSaasMode && (
            <StatStrip items={[
              { key: 'mrr', label: t('Monthly recurring revenue'), value: formatCurrency(dashboardData?.subscriptions?.mrr || 0), tone: 'success', onClick: () => router.visit(route('plan-orders.index')) },
              { key: 'paying', label: t('Paying companies'), value: dashboardData?.subscriptions?.paying ?? 0, tone: 'info', onClick: () => router.visit(route('companies.index')) },
              { key: 'trial', label: t('On trial'), value: dashboardData?.subscriptions?.trial ?? 0, tone: 'violet', onClick: () => router.visit(route('companies.index')) },
              { key: 'renewals', label: t('Renewals in 14 days'), value: dashboardData?.subscriptions?.renewals_due ?? 0, tone: 'warning', onClick: () => router.visit(route('companies.index')) },
            ]} />
          )}

          {!isSaasMode && (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card className="overflow-hidden hover:shadow-md transition-shadow cursor-pointer" onClick={() => router.visit(route('companies.index'))}>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="flex items-center gap-1">
                      <p className="text-sm font-medium text-muted-foreground">{t('Total Companies')}</p>
                      <Tooltip><TooltipTrigger asChild><Info className="h-3.5 w-3.5 text-muted-foreground cursor-help" /></TooltipTrigger><TooltipContent>{t('Total registered company accounts on the platform')}</TooltipContent></Tooltip>
                    </div>
                    <h3 className="mt-2 text-xl font-bold">{(dashboardData?.cards?.[0]?.value ?? 0).toLocaleString()}</h3>
                    <p className="text-xs text-muted-foreground mt-1">
                      {dashboardData?.companies?.active ?? 0} {t('Active')}, {dashboardData?.companies?.inactive ?? 0} {t('Inactive')}
                    </p>
                  </div>
                  <div className="rounded-full bg-blue-100 dark:bg-blue-900/20 p-3">
                    <Building2 className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                  </div>
                </div>
              </CardContent>
            </Card>

            {!isSaasMode && (
              <Card className="overflow-hidden hover:shadow-md transition-shadow">
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">{t('Total Projects')}</p>
                      <h3 className="mt-2 text-xl font-bold">{(dashboardData?.projects?.total ?? 0).toLocaleString()}</h3>
                      <p className="text-xs text-muted-foreground mt-1">
                        {dashboardData?.projects?.active ?? 0} {t('Active Projects')}
                      </p>
                    </div>
                    <div className="rounded-full bg-green-100 dark:bg-green-900/20 p-3">
                      <FolderOpen className="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}

            {!isSaasMode && (
              <Card className="overflow-hidden hover:shadow-md transition-shadow">
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">{t('System Users')}</p>
                      <h3 className="mt-2 text-xl font-bold">{(dashboardData?.users?.total ?? 0).toLocaleString()}</h3>
                      <p className="text-xs text-muted-foreground mt-1">
                        {t('Across all companies')}
                      </p>
                    </div>
                    <div className="rounded-full bg-purple-100 dark:bg-purple-900/20 p-3">
                      <Users className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}

            {isSaasMode && (
              <Card className="overflow-hidden hover:shadow-md transition-shadow cursor-pointer" onClick={() => router.visit(route('plans.index'))}>
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-1">
                      <p className="text-sm font-medium text-muted-foreground">{t('Total Plans')}</p>
                      <Tooltip><TooltipTrigger asChild><Info className="h-3.5 w-3.5 text-muted-foreground cursor-help" /></TooltipTrigger><TooltipContent>{t('Total subscription plans available for companies to purchase')}</TooltipContent></Tooltip>
                      </div>
                      <h3 className="mt-2 text-xl font-bold">{(dashboardData?.cards?.[1]?.value ?? 0).toLocaleString()}</h3>
                      <p className="text-xs text-muted-foreground mt-1">
                        {dashboardData?.plans?.active ?? 0} {t('Active')}, {dashboardData?.plans?.inactive ?? 0} {t('Inactive')}
                      </p>
                    </div>
                    <div className="rounded-full bg-green-100 dark:bg-green-900/20 p-3">
                      <CreditCard className="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}

            {isSaasMode && (
              <Card className="overflow-hidden hover:shadow-md transition-shadow cursor-pointer" onClick={() => router.visit(route('plan-orders.index'))}>
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-1">
                      <p className="text-sm font-medium text-muted-foreground">{t('Plan Orders')}</p>
                      <Tooltip><TooltipTrigger asChild><Info className="h-3.5 w-3.5 text-muted-foreground cursor-help" /></TooltipTrigger><TooltipContent>{t('Total plan subscriptions ordered by companies across all time')}</TooltipContent></Tooltip>
                      </div>
                      <h3 className="mt-2 text-xl font-bold">{(dashboardData?.cards?.[2]?.value ?? 0).toLocaleString()}</h3>
                      <p className="text-xs text-muted-foreground mt-1">
                        {dashboardData?.planOrders?.pending ?? 0} {t('Pending Approvals')}
                      </p>
                    </div>
                    <div className="rounded-full bg-purple-100 dark:bg-purple-900/20 p-3">
                      <FileText className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}

            {/* {isSaasMode && (
              <Card className="overflow-hidden hover:shadow-md transition-shadow">
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Total Coupons</p>
                      <h3 className="mt-2 text-3xl font-bold">{(dashboardData?.coupons?.total ?? 0).toLocaleString()}</h3>
                      <p className="text-xs text-muted-foreground mt-1">
                        {dashboardData?.coupons?.active ?? 0} {t('Active')}, {dashboardData?.coupons?.expired ?? 0} {t('Inactive')}
                      </p>
                    </div>
                    <div className="rounded-full bg-yellow-100 dark:bg-yellow-900/20 p-3">
                      <Ticket className="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                  </div>
                </CardContent>
              </Card>
            )} */}

          </div>
          )}

          {/* Module Cards */}
          {isSaasMode ? (
            <div className="grid gap-6 lg:grid-cols-2">
              {/* Plan orders and plan requests are one queue: Subscriptions in the menu. */}
              <AttentionList rows={[
                { key: 'orders', icon: FileText, label: t('Plan orders waiting for approval'), count: dashboardData?.planOrders?.pending ?? 0, href: route('plan-orders.index') },
                { key: 'requests', icon: Clock, label: t('Plan requests waiting for approval'), count: dashboardData?.planRequests?.pending ?? 0, href: route('plan-requests.index') },
                { key: 'expired', icon: AlertTriangle, label: t('Companies with an expired plan'), count: dashboardData?.subscriptions?.expired ?? 0, href: route('companies.index') },
              ]} />
              <NewCompaniesCard companies={dashboardData?.recentCompanies || []} />
            </div>
          ) : (
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                    <SettingsIcon className="h-5 w-5" />
                    {t('System Management')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Companies')}</span>
                    <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-secondary text-secondary-foreground">
                      {dashboardData?.companies?.total ?? 0}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Active')}</span>
                    <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                      {dashboardData?.companies?.active ?? 0}
                    </span>
                  </div>
                  <Link href={route('companies.index')} className="block">
                    <div className="text-xs text-primary hover:underline mt-2">{t('Manage Companies')} →</div>
                  </Link>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                    <DollarSign className="h-5 w-5" />
                    {t('Currencies')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Available')}</span>
                    <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-secondary text-secondary-foreground">
                      {dashboardData?.currencies?.total ?? 0}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Default')}</span>
                    <span className="font-semibold">{dashboardData?.currencies?.default ?? 'USD'}</span>
                  </div>
                  <Link href={route('currencies.index')} className="block">
                    <div className="text-xs text-primary hover:underline mt-2">{t('Manage Currencies')} →</div>
                  </Link>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                    <Globe className="h-5 w-5" />
                    {t('Landing Page')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Status')}</span>
                    <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                      {t('Active')}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Custom Pages')}</span>
                    <span className="font-semibold">{dashboardData?.customPages?.total ?? 0}</span>
                  </div>
                  <Link href={route('landing-page.settings')} className="block">
                    <div className="text-xs text-primary hover:underline mt-2">{t('Manage Landing Page')} →</div>
                  </Link>
                </CardContent>
              </Card>
            </div>
          )}

          {/* System Overview Section (self-hosted; SaaS shows New companies and Needs attention above) */}
          {!isSaasMode && (
          <div className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
              <CardHeader>
                <CardTitle className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <BarChart3 className="h-6 w-6" />
                    <span className="text-lg font-bold">{t('System Overview')}</span>
                  </div>
                  <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20">
                    {t('Live Data')}
                  </span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-6">

                  {dashboardData?.recentCompanies && dashboardData.recentCompanies.length > 0 && (
                    <div className="space-y-2 pb-4 border-b">
                      <h4 className="text-base font-semibold text-foreground mb-1">{t('Recently Registered Companies')}</h4>
                      <div className="space-y-2">
                        {dashboardData.recentCompanies.slice(0, 3).map((company: any) => (
                          <div key={company.id} className="flex items-center justify-between p-2 rounded-lg bg-muted/50">
                            <div className="flex items-center gap-2">
                              <div className="w-2 h-2 bg-green-500 rounded-full" />
                              <div>
                                <p className="text-sm font-medium">{company.name}</p>
                                <p className="text-xs text-muted-foreground">{company.email}</p>
                              </div>
                            </div>
                            <span className="text-xs text-muted-foreground">{company.registered_at}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {isSaasMode && (
                    <div className="grid gap-4 md:grid-cols-2">
                      <div className="space-y-2">
                        <h4 className="text-base font-semibold text-foreground mb-1">{t('Most Bought Plan')}</h4>
                        <div className="p-3 rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/10 dark:to-indigo-900/10">
                          {dashboardData?.mostBoughtPlan ? (
                            <>
                              <p className="font-semibold text-blue-900 dark:text-blue-100">{dashboardData.mostBoughtPlan.name}</p>
                              <p className="text-sm text-blue-700 dark:text-blue-300">{dashboardData.mostBoughtPlan.count} {t('Orders')}</p>
                            </>
                          ) : (
                            <p className="text-sm text-muted-foreground">{t('No data available')}</p>
                          )}
                        </div>
                      </div>

                      <div className="space-y-2">
                        <h4 className="text-base font-semibold text-foreground mb-1">{t('Most Used Coupon')}</h4>
                        <div className="p-3 rounded-lg bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/10 dark:to-emerald-900/10">
                          {dashboardData?.mostUsedCoupon ? (
                            <>
                              <p className="font-semibold text-green-900 dark:text-green-100">{dashboardData.mostUsedCoupon.name}</p>
                              <p className="text-sm text-green-700 dark:text-green-300">{dashboardData.mostUsedCoupon.code} • {dashboardData.mostUsedCoupon.count} {t('Uses')}</p>
                            </>
                          ) : (
                            <p className="text-sm text-muted-foreground">{t('No data available')}</p>
                          )}
                        </div>
                      </div>
                    </div>
                  )}

                  <div className="flex flex-wrap gap-3 pt-2 border-t">
                    <Link href={route('companies.index')} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                      <Building2 className="h-3 w-3" />
                      {t('Manage Companies')}
                    </Link>
                    {isSaasMode && (
                      <Link href={route('plans.index')} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                        <CreditCard className="h-3 w-3" />
                        {t('Manage Plans')}
                      </Link>
                    )}
                    {isSaasMode && (
                      <Link href={route('plans.create')} className="inline-flex items-center gap-1 text-sm text-green-600 hover:underline">
                        <Target className="h-3 w-3" />
                        {t('Create Plan')}
                      </Link>
                    )}
                    {!isSaasMode && (
                      <Link href={route('settings')} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                        <SettingsIcon className="h-3 w-3" />
                        {t('System Settings')}
                      </Link>
                    )}
                    {!isSaasMode && (
                      <Link href={route('currencies.index')} className="inline-flex items-center gap-1 text-sm text-green-600 hover:underline">
                        <DollarSign className="h-3 w-3" />
                        {t('Manage Currencies')}
                      </Link>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Activity className="h-6 w-6" />
                  <span className="text-lg font-bold">{t('Recent Activities')}</span>
                </div>
                <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20">
                  {t('Live')}
                </span>
              </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4 max-h-96 overflow-y-auto">
                  {(dashboardData?.recentActivities ?? []).length > 0 ? (dashboardData?.recentActivities ?? []).map((activity: any, index: number) => {
                    const getActivityIcon = (type: string) => {
                      switch (type) {
                        case 'plan_order':
                          return <FileText className="h-4 w-4 text-blue-500" />;
                        case 'plan_request':
                          return <Clock className="h-4 w-4 text-yellow-500" />;
                        case 'company_registration':
                          return <Building2 className="h-4 w-4 text-green-500" />;
                        default:
                          return <Activity className="h-4 w-4 text-gray-500" />;
                      }
                    };

                    const getActivityColor = (type: string) => {
                      switch (type) {
                        case 'plan_order':
                          return 'border-l-blue-500 bg-blue-50 dark:bg-blue-900/10';
                        case 'plan_request':
                          return 'border-l-yellow-500 bg-yellow-50 dark:bg-yellow-900/10';
                        case 'company_registration':
                          return 'border-l-green-500 bg-green-50 dark:bg-green-900/10';
                        default:
                          return 'border-l-gray-500 bg-gray-50 dark:bg-gray-900/10';
                      }
                    };

                    return (
                      <div key={`${activity.type}-${activity.id}`} className={`p-3 rounded-lg border-l-4 ${getActivityColor(activity.type)} transition-all hover:shadow-sm`}>
                        <div className="flex items-start gap-3">
                          <div className="flex-shrink-0 mt-0.5">
                            {getActivityIcon(activity.type)}
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-start justify-between gap-2 flex-wrap">
                              <p className="text-sm font-medium leading-5">{activity.description}</p>
                              <span className="inline-flex flex-shrink-0 items-center rounded-md px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/20 capitalize whitespace-nowrap">
                                {activity.type?.split('_').map((word: string) => word.charAt(0) + word.slice(1)).join(' ')}
                              </span>
                            </div>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                              <span className="flex items-center gap-1">
                                <Avatar className="h-4 w-4">
                                  <AvatarImage src={activity.avatar} />
                                  <AvatarFallback>{activity.user?.charAt(0)}</AvatarFallback>
                              </Avatar>
                              </span>
                              <span>•</span>
                              <span className="flex items-center gap-1">
                                <Clock className="h-3 w-3" />
                                {activity.time}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  }) : (
                    <div className="text-center text-muted-foreground py-8">
                      <Activity className="h-8 w-8 mx-auto mb-2 opacity-50" />
                      <p className="text-sm">{t('No recent activities')}</p>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>
          )}

        </div>
      </PageTemplate>
    );
  }

  const pageActions: PageAction[] = [
    {
      label: t('Refresh'),
      icon: <RefreshCw className="h-4 w-4" />,
      variant: 'outline',
      onClick: () => window.location.reload()
    }
  ];

  // Use actual data from backend
  const projects = dashboardData?.projects || { total: 0, active: 0, completed: 0, overdue: 0 };
  const tasks = dashboardData?.tasks || { total: 0, pending: 0, inProgress: 0, completed: 0 };
  const budgets = dashboardData?.budgets || { totalBudget: 0, spent: 0, remaining: 0, utilization: 0 };
  const invoices = dashboardData?.invoices || { total: 0, paid: 0, pending: 0, overdue: 0 };
  const recentActivities = dashboardData?.recentActivities || [];
  const hasProjectStatus = userWorkspaceRole !== 'client' && !!(dashboardData?.projects || dashboardData?.tasks || dashboardData?.taskStages);
  const showRecentActivities = userWorkspaceRole !== 'client';
  const showQuickActions = userWorkspaceRole !== 'client';
  const roleData = dashboardData as any;
  const activeSprints = roleData?.activeSprints || [];
  const attention = roleData?.attention || {};
  const showAttention = ['company', 'manager'].includes(userWorkspaceRole || '') && Object.keys(attention).length > 0;
  const showSprints = userWorkspaceRole !== 'client' && (activeSprints.length > 0 || ['company', 'manager'].includes(userWorkspaceRole || ''));
  // Owners rarely carry tasks themselves: only show their list when it has something.
  const showMyTasks = !!roleData?.myTasks && !(userWorkspaceRole === 'company' && roleData.myTasks.length === 0);

  return (
    <PageTemplate
      title={t('Dashboard')}
      url="/dashboard"
      actions={pageActions}
    >
      <div className="space-y-6">
        {/* Role home: what this role checks first. Each number appears once:
            owners see the money, clients what's due; managers and members act
            on the lists below, which carry their own counts. */}
        <RoleGreeting role={userWorkspaceRole || ''} name={auth?.user?.name} />
        {(userWorkspaceRole === 'company' || userWorkspaceRole === 'client') && (
          <RoleStats role={userWorkspaceRole || ''} data={roleData} />
        )}
        {userWorkspaceRole === 'member' && roleData?.weekHours !== null && roleData?.weekHours !== undefined && (
          <p className="text-sm text-muted-foreground">
            {t('Hours logged this week')}:{' '}
            <Link href={route('timesheets.weekly-view')} className="font-semibold text-foreground hover:text-primary">{roleData.weekHours}h</Link>
          </p>
        )}
        {(showSprints || showAttention) && (
          <div className={`grid gap-6 ${showSprints && showAttention ? 'lg:grid-cols-2' : ''}`}>
            {showAttention && <AttentionCard attention={attention} />}
            {showSprints && <ActiveSprintsCard sprints={activeSprints} role={userWorkspaceRole || ''} />}
          </div>
        )}
        {userWorkspaceRole === 'company' && (roleData?.projectsAtRisk || []).length > 0 && (
          <ProjectsAtRiskCard projects={roleData.projectsAtRisk} />
        )}

        {/* My Tasks — quick-update widget so members can update their own tasks
            right from the dashboard instead of navigating into a project's
            Scrum/Tasks tab first. Only rendered for roles with task_view_any. */}
        {showMyTasks && (
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                <CheckSquare className="h-5 w-5" />
                {userWorkspaceRole === 'member' ? t('My work') : t('My Tasks')}
                {dashboardData.myTasks.length > 0 && (
                  <span className="inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
                    {dashboardData.myTasks.length}
                  </span>
                )}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {dashboardData.myTasks.length === 0 ? (
                <div className="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground">
                  <CheckSquare className="h-8 w-8 opacity-30" />
                  <p className="text-sm">{t("You're all caught up! No open tasks assigned to you.")}</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {groupByDue(dashboardData.myTasks as any[]).map(group => (
                  <div key={group.key} className="space-y-2">
                    <p className={`text-xs font-semibold uppercase tracking-wide ${group.key === 'overdue' ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'}`}>
                      {t(group.label)} · {group.tasks.length}
                    </p>
                  {group.tasks.map((task: any) => (
                    <div key={task.id} className="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-lg border border-border bg-card hover:border-primary/40 transition-colors">
                      <div className="flex-1 min-w-0">
                        <Link href={route('tasks.index', { task_id: task.id })} className="font-semibold text-sm text-foreground hover:text-primary transition-colors">
                          {task.title}
                        </Link>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-muted-foreground">
                          {task.project && <span>{task.project.title}</span>}
                          <span className={`inline-flex items-center rounded-md px-1.5 py-0.5 font-medium capitalize ${
                            task.priority === 'critical' || task.priority === 'high'
                              ? 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                              : task.priority === 'medium'
                              ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                              : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                          }`}>
                            {task.priority}
                          </span>
                          {task.end_date && (
                            <span className={`flex items-center gap-1 ${task.is_overdue ? 'text-rose-600 dark:text-rose-400 font-medium' : ''}`}>
                              <Calendar className="h-3 w-3" />
                              {new Date(task.end_date).toLocaleDateString()}
                            </span>
                          )}
                        </div>
                      </div>
                      <Select
                        value={task.task_stage_id ? String(task.task_stage_id) : undefined}
                        onValueChange={(value) => {
                          router.put(route('tasks.change-stage', task.id), { task_stage_id: value }, { preserveScroll: true });
                        }}
                      >
                        <SelectTrigger className="w-full sm:w-40 h-8 text-xs shrink-0">
                          <SelectValue placeholder={t('Set stage')} />
                        </SelectTrigger>
                        <SelectContent>
                          {(dashboardData.taskStageOptions || []).map((stage: any) => (
                            <SelectItem key={stage.id} value={String(stage.id)}>{stage.name}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  ))}
                  </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* Project Progress — Client-facing replacement for "My Tasks": clients
            care about overall project completion, not individual task/ticket
            level detail. */}
        {dashboardData?.projectProgress && (
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                <Target className="h-5 w-5" />
                {t('Project Progress')}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {dashboardData.projectProgress.length === 0 ? (
                <div className="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground">
                  <FolderOpen className="h-8 w-8 opacity-30" />
                  <p className="text-sm">{t('No projects to show yet.')}</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {dashboardData.projectProgress.map((project: any) => (
                    <div key={project.id} className="p-3 rounded-lg border border-border bg-card">
                      <div className="flex items-center justify-between gap-3 mb-2">
                        <Link href={route('projects.show', project.id)} className="font-semibold text-sm text-foreground hover:text-primary transition-colors truncate">
                          {project.title}
                        </Link>
                        <span className="text-xs font-bold text-primary shrink-0">{project.progress}%</span>
                      </div>
                      <Progress value={project.progress} className="h-2" />
                      <div className="flex items-center gap-2 mt-2 text-xs text-muted-foreground">
                        <span className="capitalize">{project.status}</span>
                        {project.deadline && (
                          <>
                            <span>•</span>
                            <span className={project.is_overdue ? 'text-rose-600 dark:text-rose-400 font-medium' : ''}>
                              {new Date(project.deadline).toLocaleDateString()}
                            </span>
                          </>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* A client's invoices at a glance (owners see the money in the strip above). */}
        {userWorkspaceRole === 'client' && dashboardData?.invoices && (
          <div className="grid gap-6">
            {dashboardData?.invoices && (
            <Card className="flex flex-col h-full">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                  <FileText className="h-5 w-5" />
                  {t('Invoice Status')}
                </CardTitle>
              </CardHeader>
              <CardContent className="flex-grow flex flex-col justify-between">
                <div className="space-y-4">
                  {/* Total Invoices */}
                  <div className="flex items-center gap-3 rounded-xl border border-border bg-card shadow-sm hover:shadow-md transition-all duration-200 p-4">
                    <div className="h-10 w-10 rounded-lg flex items-center justify-center shrink-0 bg-primary/10">
                       <FileText className="h-5 w-5 text-primary" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs font-semibold text-muted-foreground capitalize">{t('Total Invoices')}</p>
                        <p className="text-xl font-bold text-foreground leading-tight">
                          {invoices.total.toLocaleString()}
                        </p>
                    </div>
                  </div>

                  {/* Paid, Pending, Overdue 3-Column Grid */}
                  <div className="grid grid-cols-3 gap-3">
                    {/* Paid */}
                    <div className="flex flex-col justify-between p-3.5 rounded-xl border border-border bg-card hover:border-emerald-500/40 hover:shadow-sm transition-all duration-200 border-l-[3px] border-l-emerald-500">
                      <span className="text-xs font-semibold text-muted-foreground capitalize leading-none">{t('Paid')}</span>
                      <span className="text-lg font-bold text-foreground mt-2 leading-none">{invoices.paid}</span>
                    </div>

                    {/* Pending */}
                    <div className="flex flex-col justify-between p-3.5 rounded-xl border border-border bg-card hover:border-amber-500/40 hover:shadow-sm transition-all duration-200 border-l-[3px] border-l-amber-500">
                      <span className="text-xs font-semibold text-muted-foreground capitalize leading-none">{t('Pending')}</span>
                      <span className="text-lg font-bold text-foreground mt-2 leading-none">{invoices.pending}</span>
                    </div>

                    {/* Overdue */}
                    <div className="flex flex-col justify-between p-3.5 rounded-xl border border-border bg-card hover:border-rose-500/40 hover:shadow-sm transition-all duration-200 border-l-[3px] border-l-rose-500">
                      <span className="text-xs font-semibold text-muted-foreground capitalize leading-none">{t('Overdue')}</span>
                      <span className="text-lg font-bold text-foreground mt-2 leading-none">{invoices.overdue}</span>
                    </div>
                  </div>
                </div>
                <div className="mt-6 pt-4 border-t">
                  <Link href={route('invoices.index')} className="group/link flex items-center gap-1.5 text-sm font-bold text-primary hover:text-primary/80 transition-colors">
                    {t('View Invoices')}
                    <span className="transition-transform group-hover/link:translate-x-1">→</span>
                  </Link>
                </div>
              </CardContent>
            </Card>
            )}

          </div>
        )}
      </div>
    </PageTemplate>
  );
}

const initialsOf = (name?: string) => (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(word => word[0]).join('').toUpperCase();

/** Platform admin: the latest sign-ups, each opening that company in Companies. */
function NewCompaniesCard({ companies }: { companies: NonNullable<DashboardData['recentCompanies']> }) {
  const { t } = useTranslation();
  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="flex items-center gap-2 text-base font-semibold">
          <UserPlus className="h-4 w-4" />
          {t('New companies')}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-2">
        {companies.length === 0 ? (
          <p className="py-4 text-sm text-muted-foreground">{t('No company has signed up yet.')}</p>
        ) : companies.map(company => (
          <Link
            key={company.id}
            href={route('companies.index', { search: company.email })}
            className="flex items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:border-primary/40 hover:bg-muted/30"
          >
            <Avatar className="h-8 w-8 shrink-0">
              <AvatarFallback className="text-xs font-semibold">{initialsOf(company.name)}</AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium">{company.name}</p>
              <p className="truncate text-xs text-muted-foreground">{company.email}</p>
            </div>
            <div className="shrink-0 text-right text-xs">
              {company.plan && <p className="font-medium">{company.plan}</p>}
              <p className="text-muted-foreground">{company.registered_at}</p>
            </div>
          </Link>
        ))}
      </CardContent>
    </Card>
  );
}
