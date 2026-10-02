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

interface DashboardData {
  cards: Array<{
    value: number;
  }>;
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
          {
            label: t('Manage Companies'),
            icon: <Building2 className="h-4 w-4" />,
            variant: 'default',
            onClick: () => router.visit(route('companies.index'))
          },
          {
            label: t('Refresh'),
            icon: <RefreshCw className="h-4 w-4" />,
            variant: 'outline',
            onClick: () => window.location.reload()
          }
        ]}
      >
        <div className="space-y-6">
          {/* Main Stats Cards */}
          <div className={`grid gap-4 ${isSaasMode ? 'md:grid-cols-2 lg:grid-cols-3' : 'md:grid-cols-2 lg:grid-cols-3'}`}>
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

          {/* Module Cards */}
          {isSaasMode ? (
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="flex items-center gap-2 text-lg font-medium">
                    <FileText className="h-5 w-5" />
                    {t('Plan Orders')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Pending')}</span>
                    <span className={(dashboardData?.planOrders?.pending ?? 0) > 0 ? "inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20" : "inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20"}>
                      {dashboardData?.planOrders?.pending ?? 0}
                    </span>
                  </div>
                  <Link href={route('plan-orders.index')} className="block">
                    <div className="text-xs text-primary hover:underline mt-2">{t('Manage Orders')} →</div>
                  </Link>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="flex items-center gap-2 text-lg font-medium">
                    <Clock className="h-5 w-5" />
                    {t('Plan Requests')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Pending')}</span>
                    <span className={(dashboardData?.planRequests?.pending ?? 0) > 0 ? "inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20" : "inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20"}>
                      {dashboardData?.planRequests?.pending ?? 0}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Approved')}</span>
                    <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                      {dashboardData?.planRequests?.approved ?? 0}
                    </span>
                  </div>
                  <Link href={route('plan-requests.index')} className="block">
                    <div className="text-xs text-primary hover:underline mt-2">{t('Manage Requests')} →</div>
                  </Link>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="flex items-center gap-2 text-lg font-medium">
                    <Ticket className="h-5 w-5" />
                    {t('Coupons')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Inactive')}</span>
                    <span className={(dashboardData?.coupons?.expired ?? 0) > 0 ? "inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20" : "inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20"}>
                      {dashboardData?.coupons?.expired ?? 0}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">{t('Active')}</span>
                    <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">
                      {dashboardData?.coupons?.active ?? 0}
                    </span>
                  </div>
                  <Link href={route('coupons.index')} className="block">
                    <div className="text-xs text-primary hover:underline mt-2">{t('Manage Coupons')} →</div>
                  </Link>
                </CardContent>
              </Card>
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

          {/* System Overview Section */}
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
                      <div key={activity.id} className={`p-3 rounded-lg border-l-4 ${getActivityColor(activity.type)} transition-all hover:shadow-sm`}>
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

  return (
    <PageTemplate
      title={t('Dashboard')}
      url="/dashboard"
      actions={pageActions}
    >
      <div className="space-y-6">
        {/* My Tasks — quick-update widget so members can update their own tasks
            right from the dashboard instead of navigating into a project's
            Scrum/Tasks tab first. Only rendered for roles with task_view_any. */}
        {dashboardData?.myTasks && (
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                <CheckSquare className="h-5 w-5" />
                {t('My Tasks')}
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
                <div className="space-y-2">
                  {dashboardData.myTasks.map((task: any) => (
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

        {/* Main Stats Cards - Dynamically rendered based on backend. Uses flex-wrap
            with each card flexing to fill the row evenly, so it looks balanced
            no matter how many cards a given role's permissions leave visible
            (1 card fills the row, 2 split it evenly, etc.) instead of a fixed
            grid-cols count leaving an empty gap when the count doesn't match. */}
        {dashboardData?.cards && dashboardData.cards.length > 0 && (
          <div className="flex flex-wrap gap-4">
            {dashboardData.cards.map((card: any, index: number) => {
              const getCardIcon = (title: string, className: string) => {
                if (title.includes('User')) return <Users className={className} />;
                if (title.includes('Project')) return <FolderOpen className={className} />;
                if (title.includes('Deadline')) return <Calendar className={className} />;
                if (title.includes('Task')) return <CheckSquare className={className} />;
                if (title.includes('Revenue') || title.includes('Received')) return <DollarSign className={className} />;
                return <Activity className={className} />;
              };

              const getCardColors = (title: string) => {
                if (title.includes('User')) return { bg: 'bg-emerald-100 dark:bg-emerald-900', text: 'text-emerald-600 dark:text-emerald-100' };
                if (title.includes('Project')) return { bg: 'bg-blue-100 dark:bg-blue-900', text: 'text-blue-600 dark:text-blue-100' };
                if (title.includes('Deadline')) return { bg: 'bg-orange-100 dark:bg-orange-900', text: 'text-orange-600 dark:text-orange-100' };
                if (title.includes('Task')) return { bg: 'bg-violet-100 dark:bg-violet-900', text: 'text-violet-600 dark:text-violet-100' };
                if (title.includes('Revenue') || title.includes('Received')) return { bg: 'bg-amber-100 dark:bg-amber-900', text: 'text-amber-600 dark:text-amber-100' };
                return { bg: 'bg-gray-100 dark:bg-gray-800', text: 'text-gray-600 dark:text-gray-100' };
              };

              const colors = getCardColors(card.title);
              const hasLink = !!card.link;
              const CardTag = hasLink ? Link : 'div';
              const cardTagProps = hasLink
                ? { href: card.link }
                : {};

              return (
                <CardTag
                  key={index}
                  {...cardTagProps}
                  className={`flex-1 min-w-[220px] flex items-center gap-3 rounded-xl border border-border bg-card shadow-sm hover:shadow-md transition-all duration-200 p-4 ${hasLink ? 'cursor-pointer' : ''}`}
                >
                    <div className={`h-10 w-10 rounded-lg flex items-center justify-center shrink-0 ${colors.bg}`}>
                        {getCardIcon(card.title, `h-5 w-5 ${colors.text}`)}
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs font-semibold text-muted-foreground truncate capitalize">{t(card.title)}</p>
                        <p className="text-xl font-bold text-foreground leading-tight">
                          {card.format === 'currency' ? formatCurrency(card.value) : card.value.toLocaleString()}
                        </p>
                    </div>
                </CardTag>
              );
            })}
          </div>
        )}

        {/* Budget Overview — single card, no wrapping grid needed so it never
            leaves an empty half-row gap for roles without other secondary widgets */}
        {dashboardData?.budgets && userWorkspaceRole !== 'client' && (
            <Card className="flex flex-col h-full">
              <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                  <Wallet className="h-5 w-5" />
                  {t('Budget Overview')}
                </CardTitle>
              </CardHeader>
              <CardContent className="flex-grow flex flex-col justify-between space-y-4">
                <div className="flex flex-col gap-3">
                  <div className="p-3 rounded-lg border border-border bg-card">
                    <div className="flex justify-between text-sm mb-2">
                      <span className="font-medium text-foreground">{t('Utilization')}</span>
                      <span className="font-bold text-foreground">{budgets.utilization}{t('%')}</span>
                    </div>
                    <Progress value={budgets.utilization} className="h-2" />
                  </div>

                  <div className="flex items-center justify-between p-3 rounded-lg border border-border bg-card">
                    <div className="flex items-center gap-3">
                      <div className="p-2 rounded-md bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400">
                        <TrendingUp className="h-4 w-4" />
                      </div>
                      <span className="text-sm font-medium text-foreground">{t('Spent')}</span>
                    </div>
                    <span className="font-bold text-foreground">{formatCurrency(budgets.spent)}</span>
                  </div>

                  <div className="flex items-center justify-between p-3 rounded-lg border border-border bg-card">
                    <div className="flex items-center gap-3">
                      <div className="p-2 rounded-md bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                        <Wallet className="h-4 w-4" />
                      </div>
                      <span className="text-sm font-medium text-foreground">{t('Remaining')}</span>
                    </div>
                    <span className="font-bold text-foreground">{formatCurrency(budgets.remaining)}</span>
                  </div>
                </div>
                {auth?.permissions?.includes('budget_dashboard_view') && (
                  <Link href={route('budgets.dashboard')} className="group/link flex items-center gap-1.5 text-xs font-bold text-primary mt-4 hover:text-primary/80 transition-colors">
                    {t('View Budgets')}
                    <span className="transition-transform group-hover/link:translate-x-1">→</span>
                  </Link>
                )}
              </CardContent>
            </Card>
        )}

        {/* Main Dashboard Content */}
        {(hasProjectStatus || showRecentActivities) && (
        <div className="grid gap-6 lg:grid-cols-3">
          {/* Project Status - Only show if data exists. Recent Activities below
              spans the full row when this card is hidden for a role, instead of
              sitting alone in 1/3 of the row width. */}
          {hasProjectStatus && (
            <Card className="lg:col-span-2 flex flex-col h-full">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                  <Target className="h-6 w-6" />
                  {t('Project Status Overview')}
                  {dashboardData?.projects && (
                    <span className="inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
                      {projects.total} {t('Total')}
                    </span>
                  )}
                </CardTitle>
              </CardHeader>
              <CardContent className="flex-grow">
                <div className="space-y-8">
                  {/* Project Progress Chart */}
                  <div className="flex flex-col gap-8">
                    {dashboardData?.projects && (
                      <div className="space-y-4">
                        <h4 className="text-sm font-bold text-foreground capitalize mb-4">{t('Projects Pipeline')}</h4>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                          <div className="flex items-center gap-3 rounded-xl border border-border bg-card shadow-sm hover:shadow-md transition-all duration-200 p-4">
                            <div className="h-10 w-10 rounded-lg flex items-center justify-center shrink-0 bg-emerald-100 dark:bg-emerald-900">
                               <Activity className="h-5 w-5 text-emerald-600 dark:text-emerald-100" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs font-semibold text-muted-foreground truncate capitalize">{t('Active')}</p>
                                <p className="text-xl font-bold text-foreground leading-tight">
                                  {projects.active.toLocaleString()}
                                </p>
                            </div>
                          </div>
                          
                          <div className="flex items-center gap-3 rounded-xl border border-border bg-card shadow-sm hover:shadow-md transition-all duration-200 p-4">
                            <div className="h-10 w-10 rounded-lg flex items-center justify-center shrink-0 bg-blue-100 dark:bg-blue-900">
                               <CheckSquare className="h-5 w-5 text-blue-600 dark:text-blue-100" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs font-semibold text-muted-foreground truncate capitalize">{t('Completed')}</p>
                                <p className="text-xl font-bold text-foreground leading-tight">
                                  {projects.completed.toLocaleString()}
                                </p>
                            </div>
                          </div>

                          <div className="flex items-center gap-3 rounded-xl border border-border bg-card shadow-sm hover:shadow-md transition-all duration-200 p-4">
                            <div className="h-10 w-10 rounded-lg flex items-center justify-center shrink-0 bg-rose-100 dark:bg-rose-900">
                               <AlertTriangle className="h-5 w-5 text-rose-600 dark:text-rose-100" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-xs font-semibold text-muted-foreground truncate capitalize">{t('Overdue')}</p>
                                <p className="text-xl font-bold text-foreground leading-tight">
                                  {projects.overdue.toLocaleString()}
                                </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    )}

                    {dashboardData?.taskStages && dashboardData.taskStages.length > 0 && (
                      <div className="space-y-4 pt-4 border-t border-border">
                        <h4 className="text-sm font-bold text-foreground capitalize mb-4">{t('Task Stages')}</h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1">
                          {dashboardData.taskStages.map((stage: any, index: number) => {
                            const dotColors = [
                               'bg-blue-500',
                               'bg-amber-500',
                               'bg-emerald-500',
                               'bg-purple-500',
                               'bg-rose-500',
                               'bg-cyan-500',
                               'bg-indigo-500',
                               'bg-teal-500'
                            ];
                            const dotBg = dotColors[index % dotColors.length];

                            return (
                               <div key={stage.name} className="group flex items-center justify-between p-3 rounded-lg bg-card border border-border hover:border-primary/50 hover:shadow-sm transition-all duration-200">
                                 <div className="flex items-center gap-3">
                                   <div className={`w-2 h-2 rounded-full ${dotBg} shadow-sm`} />
                                   <span className="text-sm font-semibold text-foreground/80 capitalize group-hover:text-foreground transition-colors">{stage.name}</span>
                                 </div>
                                 <span className="text-sm font-bold text-foreground bg-muted px-2.5 py-0.5 rounded-full">{stage.count}</span>
                               </div>
                            );
                          })}
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Progress Bar */}
                  {dashboardData?.projects && dashboardData?.tasks && (
                    <div className="pt-6 border-t border-border/50">
                      <div className="flex justify-between items-end mb-3">
                        <div className="flex flex-col">
                          <span className="text-sm font-bold text-foreground capitalize">{t('Overall Completion')}</span>
                          <span className="text-xs text-muted-foreground mt-1 capitalize">{t('Projects and tasks combined')}</span>
                        </div>
                        <span className="text-lg font-bold text-primary">
                          {(() => {
                            const totalItems = projects.total + tasks.total;
                            const completedItems = projects.completed + tasks.completed;
                            return totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
                          })()}%
                        </span>
                      </div>
                      <div className="h-3 w-full bg-muted rounded-full overflow-hidden border border-border/50">
                        <div 
                          className="h-full bg-primary transition-all duration-1000 ease-in-out"
                          style={{ width: `${(() => {
                            const totalItems = projects.total + tasks.total;
                            const completedItems = projects.completed + tasks.completed;
                            return totalItems > 0 ? (completedItems / totalItems) * 100 : 0;
                          })()}%` }}
                        />
                      </div>
                    </div>
                  )}

                  {/* Action Links */}
                  <div className="flex flex-wrap gap-6 pt-5 border-t">
                    {dashboardData?.projects && (
                      <Link href={route('projects.index')} className="group/link flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary/80 transition-colors">
                        <div className="p-1.5 rounded-md bg-primary/10 group-hover/link:bg-primary/20 transition-colors">
                          <FolderOpen className="h-3.5 w-3.5" />
                        </div>
                        {t('View Projects')}
                      </Link>
                    )}
                    {dashboardData?.tasks && (
                      <Link href={route('tasks.index')} className="group/link flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary/80 transition-colors">
                        <div className="p-1.5 rounded-md bg-primary/10 group-hover/link:bg-primary/20 transition-colors">
                          <CheckSquare className="h-3.5 w-3.5" />
                        </div>
                        {t('View Tasks')}
                      </Link>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Recent Activities — hidden for clients, who shouldn't see task/bug/ticket-level detail */}
          {showRecentActivities && (
          <Card className={`flex flex-col h-full ${hasProjectStatus ? '' : 'lg:col-span-3'}`}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                  <Activity className="h-6 w-6" />
                  {t('Recent Activities')}
                <span className="inline-flex items-center rounded-md border px-2 py-1 text-xs font-medium">
                  {t('Live')}
                </span>
              </CardTitle>
            </CardHeader>
            <CardContent className="relative flex-1 min-h-[350px]">
              <div className="absolute inset-x-6 top-2 bottom-6 overflow-y-auto pr-1 space-y-0">
                {recentActivities.map((activity, index) => {
                  const getActivityStyle = (type: string) => {
                    switch (type) {
                      case 'task': return { icon: <CheckSquare className="h-4 w-4 text-blue-600 dark:text-blue-400" />, bg: 'bg-blue-100 dark:bg-blue-900/40', border: 'border-blue-200 dark:border-blue-800', text: 'text-blue-700 dark:text-blue-400' };
                      case 'project': return { icon: <FolderOpen className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />, bg: 'bg-emerald-100 dark:bg-emerald-900/40', border: 'border-emerald-200 dark:border-emerald-800', text: 'text-emerald-700 dark:text-emerald-400' };
                      case 'expense': return { icon: <Receipt className="h-4 w-4 text-amber-600 dark:text-amber-400" />, bg: 'bg-amber-100 dark:bg-amber-900/40', border: 'border-amber-200 dark:border-amber-800', text: 'text-amber-700 dark:text-amber-400' };
                      case 'bug': return { icon: <Bug className="h-4 w-4 text-rose-600 dark:text-rose-400" />, bg: 'bg-rose-100 dark:bg-rose-900/40', border: 'border-rose-200 dark:border-rose-800', text: 'text-rose-700 dark:text-rose-400' };
                      case 'invoice': return { icon: <FileText className="h-4 w-4 text-purple-600 dark:text-purple-400" />, bg: 'bg-purple-100 dark:bg-purple-900/40', border: 'border-purple-200 dark:border-purple-800', text: 'text-purple-700 dark:text-purple-400' };
                      case 'timesheet': return { icon: <Clock className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />, bg: 'bg-indigo-100 dark:bg-indigo-900/40', border: 'border-indigo-200 dark:border-indigo-800', text: 'text-indigo-700 dark:text-indigo-400' };
                      default: return { icon: <Activity className="h-4 w-4 text-primary" />, bg: 'bg-primary/10', border: 'border-primary/20', text: 'text-primary' };
                    }
                  };

                  const style = getActivityStyle(activity.type);
                  return (
                    <div key={activity.id} className="group relative pl-10 pb-6 last:pb-0">
                      {/* Timeline Line */}
                      {index !== recentActivities.length - 1 && (
                        <div className="absolute left-[15px] top-8 bottom-0 w-px bg-gray-200 dark:bg-gray-800 group-hover:bg-primary/30 transition-colors" />
                      )}
                      
                      {/* Timeline Dot/Icon */}
                      <div className={`absolute left-0 top-1 w-8 h-8 rounded-full flex items-center justify-center ring-4 ring-white dark:ring-gray-950 ${style.bg} ${style.border} border group-hover:scale-110 transition-all duration-300 z-10 shadow-sm`}>
                        {style.icon}
                      </div>

                      <div className="flex flex-col gap-2 p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 shadow-sm hover:shadow-md transition-all duration-200">
                        <div className="flex justify-between items-start gap-4">
                          <p className="text-sm font-semibold text-gray-900 dark:text-white leading-tight">{activity.description}</p>
                          <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20 capitalize`}>
                            {activity.type?.split('_').join(' ')}
                          </span>
                        </div>
                        <div className="flex items-center gap-3 text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">
                          <span className="flex items-center gap-1.5">
                            <Avatar className="h-5 w-5 border border-gray-200 dark:border-gray-700">
                              <AvatarImage src={activity.avatar} alt={activity.user} />
                              <AvatarFallback>{activity.user?.charAt(0)}</AvatarFallback>
                            </Avatar>
                            <span className="text-gray-700 dark:text-gray-300">{activity.user}</span>
                          </span>
                          <span className="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600" />
                          <span className="flex items-center gap-1.5">
                            <Clock className="h-3.5 w-3.5" />
                            {activity.time}
                          </span>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </CardContent>
          </Card>
          )}
        </div>
        )}

        {/* Financial Overview - Only show if data exists */}
        {(dashboardData?.invoices || dashboardData?.expenses) && (
          <div className="grid gap-6 md:grid-cols-2">
            {dashboardData?.invoices && (
            <Card className={`flex flex-col h-full ${showQuickActions ? '' : 'md:col-span-2'}`}>
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
                    {showQuickActions ? t('Manage Invoices') : t('View Invoices')}
                    <span className="transition-transform group-hover/link:translate-x-1">→</span>
                  </Link>
                </div>
              </CardContent>
            </Card>
            )}

            {showQuickActions && (
            <Card className={`flex flex-col h-full ${dashboardData?.invoices ? '' : 'md:col-span-2'}`}>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                  <TrendingUp className="h-5 w-5" />
                  {t('Quick Actions')}
                </CardTitle>
              </CardHeader>
              <CardContent className="flex-grow flex flex-col justify-between">
                <div className="grid grid-cols-2 gap-3">
                  {dashboardData?.projects && (
                    <Link href={route('projects.index')} className="group flex flex-col justify-between p-3.5 rounded-xl border border-border bg-card hover:border-primary/40 hover:shadow-sm transition-all duration-200 min-h-[90px]">
                      <div className="p-2 w-fit rounded-lg bg-primary/10 text-primary transition-all duration-200 shadow-sm group-hover:scale-105">
                        <FolderOpen className="h-4.5 w-4.5" />
                      </div>
                      <span className="text-sm font-semibold text-foreground capitalize mt-2">{t('View Projects')}</span>
                    </Link>
                  )}
                  {dashboardData?.tasks && (
                    <Link href={route('tasks.index')} className="group flex flex-col justify-between p-3.5 rounded-xl border border-border bg-card hover:border-primary/40 hover:shadow-sm transition-all duration-200 min-h-[90px]">
                      <div className="p-2 w-fit rounded-lg bg-primary/10 text-primary transition-all duration-200 shadow-sm group-hover:scale-105">
                        <CheckSquare className="h-4.5 w-4.5" />
                      </div>
                      <span className="text-sm font-semibold text-foreground capitalize mt-2">{t('View Tasks')}</span>
                    </Link>
                  )}
                  {dashboardData?.expenses && userWorkspaceRole !== 'client' && (
                    <Link href={route('expenses.create')} className="group flex flex-col justify-between p-3.5 rounded-xl border border-border bg-card hover:border-primary/40 hover:shadow-sm transition-all duration-200 min-h-[90px]">
                      <div className="p-2 w-fit rounded-lg bg-primary/10 text-primary transition-all duration-200 shadow-sm group-hover:scale-105">
                        <Receipt className="h-4.5 w-4.5" />
                      </div>
                      <span className="text-sm font-semibold text-foreground capitalize mt-2">{t('Submit Expense')}</span>
                    </Link>
                  )}
                  {dashboardData?.invoices && userWorkspaceRole !== 'client' && (
                    <Link href={route('invoices.create')} className="group flex flex-col justify-between p-3.5 rounded-xl border border-border bg-card hover:border-primary/40 hover:shadow-sm transition-all duration-200 min-h-[90px]">
                      <div className="p-2 w-fit rounded-lg bg-primary/10 text-primary transition-all duration-200 shadow-sm group-hover:scale-105">
                        <FileText className="h-4.5 w-4.5" />
                      </div>
                      <span className="text-sm font-semibold text-foreground capitalize mt-2">{t('Create Invoice')}</span>
                    </Link>
                  )}
                  {hasRoleDashboardAccess && (
                    <Link href={route('roles.dashboard')} className="col-span-2 group flex items-center justify-between p-3.5 rounded-xl border border-border bg-card hover:border-primary/40 hover:shadow-sm transition-all duration-200">
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-primary/10 text-primary transition-all duration-200 shadow-sm group-hover:scale-105">
                          <Shield className="h-4.5 w-4.5" />
                        </div>
                        <span className="text-sm font-semibold text-foreground capitalize">{t('Manage Workspace Roles')}</span>
                      </div>
                      <span className="text-gray-400 group-hover:text-primary transition-all duration-200 transform group-hover:translate-x-1">→</span>
                    </Link>
                  )}
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