import React, { useState, useEffect, type ReactNode } from 'react';
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
import { Badge } from '@/components/ui/badge';
import { ActiveSprintsCard, AttentionCard, AttentionList, ProjectsAtRiskCard, RoleGreeting, RoleStats, groupByDue } from '@/components/dashboard/role-home';
import { StatStrip } from '@/components/stat-strip';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
  BarList, BudgetBurnCard, ColumnChartCard, ComingUpCard, ContractsToSignCard, ExpiringSoonCard, InsightCard,
  RecentActivityCard, TasksByStageCard, TeamWorkloadCard, daysToColumns, monthsToColumns,
} from '@/components/dashboard/insights';

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
    const platformInsights = (dashboardData as any)?.insights as Record<string, any> | undefined;
    return (
      <PageTemplate
        title={t('Dashboard')}
        url="/dashboard"
        noPadding
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

          {/* SaaS: the business over time (two measures, two charts), plan mix and what expires soon. */}
          {isSaasMode && platformInsights && (
            <>
              <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {platformInsights.revenueByMonth && (
                  <ColumnChartCard
                    title={t('Subscription revenue')}
                    icon={TrendingUp}
                    data={monthsToColumns(platformInsights.revenueByMonth)}
                    format={(value) => formatCurrency(value) as string}
                    valueLabel={t('Revenue')}
                    emptyText={t('No paid plan orders in the last 6 months.')}
                    summary={<p className="text-sm text-ds-fg-secondary">{t('Paid plan orders by month, last 6 months')}</p>}
                  />
                )}
                {platformInsights.signupsByMonth && (
                  <ColumnChartCard
                    title={t('Sign-ups')}
                    icon={UserPlus}
                    data={monthsToColumns(platformInsights.signupsByMonth)}
                    format={(value) => String(value)}
                    valueLabel={t('New companies')}
                    emptyText={t('No company signed up in the last 6 months.')}
                    summary={<p className="text-sm text-ds-fg-secondary">{t('New company accounts by month, last 6 months')}</p>}
                  />
                )}
              </div>
              <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {platformInsights.planMix && (
                  <InsightCard
                    title={t('Plan mix')}
                    icon={CreditCard}
                    action={<Link href={route('plans.index')} className="rounded-sm text-sm font-medium text-ds-fg-accent hover:underline">{t('Plans')}</Link>}
                  >
                    <p className="mb-3 text-sm text-ds-fg-secondary">{t('Companies with an active plan, per plan')}</p>
                    {platformInsights.planMix.length === 0 ? (
                      <p className="py-4 text-sm text-ds-fg-secondary">{t('No company has an active plan yet.')}</p>
                    ) : (
                      <BarList rows={platformInsights.planMix.map((plan: { name: string; count: number }) => ({ key: plan.name, label: plan.name, value: plan.count }))} />
                    )}
                  </InsightCard>
                )}
                {platformInsights.expiringSoon && <ExpiringSoonCard companies={platformInsights.expiringSoon} />}
              </div>
            </>
          )}

          {!isSaasMode && (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Link href={route('companies.index')} className="block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus">
            <Card className="overflow-hidden transition-colors hover:border-ds-border-strong">
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
                  <div className="rounded-full bg-ds-info-bg p-3">
                    <Building2 className="h-6 w-6 text-ds-info" aria-hidden="true" />
                  </div>
                </div>
              </CardContent>
            </Card>
            </Link>

            {!isSaasMode && (
              <Card className="overflow-hidden">
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">{t('Total Projects')}</p>
                      <h3 className="mt-2 text-xl font-bold">{(dashboardData?.projects?.total ?? 0).toLocaleString()}</h3>
                      <p className="text-xs text-muted-foreground mt-1">
                        {dashboardData?.projects?.active ?? 0} {t('Active Projects')}
                      </p>
                    </div>
                    <div className="rounded-full bg-ds-success-bg p-3">
                      <FolderOpen className="h-6 w-6 text-ds-success" aria-hidden="true" />
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}

            {!isSaasMode && (
              <Card className="overflow-hidden">
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">{t('System Users')}</p>
                      <h3 className="mt-2 text-xl font-bold">{(dashboardData?.users?.total ?? 0).toLocaleString()}</h3>
                      <p className="text-xs text-muted-foreground mt-1">
                        {t('Across all companies')}
                      </p>
                    </div>
                    <div className="rounded-full bg-brand-soft p-3">
                      <Users className="h-6 w-6 text-brand-soft-fg" aria-hidden="true" />
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
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
              {/* Plan orders and plan requests are one queue: Subscriptions in the menu. */}
              <AttentionList rows={[
                { key: 'orders', icon: FileText, label: t('Plan orders waiting for approval'), count: dashboardData?.planOrders?.pending ?? 0, href: route('plan-orders.index') },
                { key: 'requests', icon: Clock, label: t('Plan requests waiting for approval'), count: dashboardData?.planRequests?.pending ?? 0, href: route('plan-requests.index') },
                { key: 'expired', icon: AlertTriangle, label: t('Companies with an expired plan'), count: dashboardData?.subscriptions?.expired ?? 0, href: route('companies.index') },
              ]} />
              <NewCompaniesCard companies={dashboardData?.recentCompanies || []} />
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
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
                    <Badge variant="success">
                      {dashboardData?.companies?.active ?? 0}
                    </Badge>
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
                    <Badge variant="success">
                      {t('Active')}
                    </Badge>
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
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
              <CardHeader>
                <CardTitle className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <BarChart3 className="h-6 w-6" />
                    <span className="text-lg font-bold">{t('System Overview')}</span>
                  </div>
                  <Badge variant="outline">
                    {t('Live Data')}
                  </Badge>
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
                              <div className="h-2 w-2 rounded-full bg-ds-success-dot" aria-hidden="true" />
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
                        <div className="rounded-lg border border-ds-border-subtle bg-ds-surface-subtle p-3">
                          {dashboardData?.mostBoughtPlan ? (
                            <>
                              <p className="font-semibold text-ds-fg">{dashboardData.mostBoughtPlan.name}</p>
                              <p className="text-sm text-ds-fg-secondary">{dashboardData.mostBoughtPlan.count} {t('Orders')}</p>
                            </>
                          ) : (
                            <p className="text-sm text-muted-foreground">{t('No data available')}</p>
                          )}
                        </div>
                      </div>

                      <div className="space-y-2">
                        <h4 className="text-base font-semibold text-foreground mb-1">{t('Most Used Coupon')}</h4>
                        <div className="rounded-lg border border-ds-border-subtle bg-ds-surface-subtle p-3">
                          {dashboardData?.mostUsedCoupon ? (
                            <>
                              <p className="font-semibold text-ds-fg">{dashboardData.mostUsedCoupon.name}</p>
                              <p className="text-sm text-ds-fg-secondary">{dashboardData.mostUsedCoupon.code} • {dashboardData.mostUsedCoupon.count} {t('Uses')}</p>
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
                      <Link href={route('plans.create')} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
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
                      <Link href={route('currencies.index')} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
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
                <Badge variant="outline">
                  {t('Live')}
                </Badge>
              </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4 max-h-96 overflow-y-auto">
                  {(dashboardData?.recentActivities ?? []).length > 0 ? (dashboardData?.recentActivities ?? []).map((activity: any, index: number) => {
                    const getActivityIcon = (type: string) => {
                      switch (type) {
                        case 'plan_order':
                          return <FileText className="h-4 w-4 text-ds-info" />;
                        case 'plan_request':
                          return <Clock className="h-4 w-4 text-ds-warning" />;
                        case 'company_registration':
                          return <Building2 className="h-4 w-4 text-ds-success" />;
                        default:
                          return <Activity className="h-4 w-4 text-ds-fg-muted" />;
                      }
                    };

                    const getActivityColor = (type: string) => {
                      switch (type) {
                        case 'plan_order':
                          return 'border-l-ds-info';
                        case 'plan_request':
                          return 'border-l-ds-warning';
                        case 'company_registration':
                          return 'border-l-ds-success';
                        default:
                          return 'border-l-ds-border-strong';
                      }
                    };

                    return (
                      <div key={`${activity.type}-${activity.id}`} className={`rounded-lg border-l-4 bg-ds-surface-subtle p-3 ${getActivityColor(activity.type)}`}>
                        <div className="flex items-start gap-3">
                          <div className="flex-shrink-0 mt-0.5">
                            {getActivityIcon(activity.type)}
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-start justify-between gap-2 flex-wrap">
                              <p className="text-sm font-medium leading-5">{activity.description}</p>
                              <Badge variant="outline" className="capitalize">
                                {activity.type?.split('_').map((word: string) => word.charAt(0) + word.slice(1)).join(' ')}
                              </Badge>
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

  const invoices = dashboardData?.invoices || { total: 0, paid: 0, pending: 0, overdue: 0 };
  const role = userWorkspaceRole || '';
  const roleData = dashboardData as any;
  const insights = roleData?.insights || {};
  const activeSprints = roleData?.activeSprints || [];
  const attention = roleData?.attention || {};
  const showAttention = ['company', 'manager'].includes(role) && Object.keys(attention).length > 0;
  const showSprints = role !== 'client' && (activeSprints.length > 0 || ['company', 'manager'].includes(role));
  // Owners rarely carry tasks themselves: only show their list when it has something.
  const showMyTasks = !!roleData?.myTasks && !(role === 'company' && roleData.myTasks.length === 0);

  const revenueChart = insights.revenueByMonth && (
    <ColumnChartCard
      title={t('Revenue received')}
      icon={TrendingUp}
      data={monthsToColumns(insights.revenueByMonth)}
      format={(value) => formatCurrency(value) as string}
      valueLabel={t('Received')}
      emptyText={t('No paid invoices in the last 6 months.')}
      summary={<p className="text-sm text-ds-fg-secondary">{t('Paid invoices by month, last 6 months')}</p>}
    />
  );
  const myWeekChart = insights.myWeek && (
    <ColumnChartCard
      title={t('My week')}
      icon={Clock}
      data={daysToColumns(insights.myWeek.days)}
      format={(value) => `${value}h`}
      valueLabel={t('Hours')}
      emptyText={t('No time logged this week yet.')}
      action={
        <Button asChild size="sm" variant="outline" className="h-7 px-2 text-xs">
          <Link href={route('timesheets.weekly-view')}>{t('Log time')}</Link>
        </Button>
      }
      summary={
        <div>
          <div className="mb-1 flex items-baseline justify-between text-sm">
            <span className="text-ds-fg-secondary">{t('Logged this week')}</span>
            <span className="font-semibold text-ds-fg">{insights.myWeek.total}h <span className="font-normal text-ds-fg-muted">{t('of')} {insights.myWeek.target}h</span></span>
          </div>
          <Progress value={Math.min(100, (insights.myWeek.total / insights.myWeek.target) * 100)} className="h-1.5" aria-label={t('Hours logged this week')} />
        </div>
      }
    />
  );

  return (
    <PageTemplate
      title={t('Dashboard')}
      url="/dashboard"
      actions={pageActions}
      noPadding
    >
      <div className="space-y-6">
        {/* Each role leads with its numbers, then the cards that break them down.
            Every number and row opens the list behind it. */}
        <RoleGreeting role={role} name={auth?.user?.name} />
        <RoleStats role={role} data={roleData} />

        {role === 'company' && (
          <>
            {/* Money over time beside what is waiting on the owner. */}
            <div className={cn('grid grid-cols-1 gap-6', revenueChart && showAttention && 'lg:grid-cols-3')}>
              {revenueChart && <div className={cn(showAttention && 'lg:col-span-2')}>{revenueChart}</div>}
              {showAttention && <AttentionCard attention={attention} />}
            </div>
            <Row>
              {insights.tasksByStage && <TasksByStageCard stages={insights.tasksByStage.stages} doneThisWeek={insights.tasksByStage.done_this_week} />}
              {insights.teamWorkload && <TeamWorkloadCard people={insights.teamWorkload} />}
            </Row>
            <Row>
              {(roleData?.projectsAtRisk || []).length > 0 && <ProjectsAtRiskCard projects={roleData.projectsAtRisk} />}
              {insights.budgetBurn && <BudgetBurnCard budgets={insights.budgetBurn} />}
            </Row>
            <Row>
              {showSprints && <ActiveSprintsCard sprints={activeSprints} role={role} />}
              {(insights.dueSoon || insights.meetings) && <ComingUpCard tasks={insights.dueSoon} meetings={insights.meetings} />}
            </Row>
            <Row>
              <RecentActivityCard activities={roleData?.recentActivities || []} />
              {showMyTasks && <MyTasksCard tasks={roleData.myTasks} stageOptions={roleData.taskStageOptions || []} role={role} />}
            </Row>
          </>
        )}

        {role === 'manager' && (
          <>
            <Row>
              {showAttention && <AttentionCard attention={attention} />}
              {showSprints && <ActiveSprintsCard sprints={activeSprints} role={role} />}
            </Row>
            <Row>
              {insights.tasksByStage && <TasksByStageCard stages={insights.tasksByStage.stages} doneThisWeek={insights.tasksByStage.done_this_week} />}
              {insights.teamWorkload && <TeamWorkloadCard people={insights.teamWorkload} />}
            </Row>
            {(insights.dueSoon || insights.meetings) && <ComingUpCard tasks={insights.dueSoon} meetings={insights.meetings} />}
            {showMyTasks && <MyTasksCard tasks={roleData.myTasks} stageOptions={roleData.taskStageOptions || []} role={role} />}
          </>
        )}

        {role !== 'company' && role !== 'manager' && role !== 'client' && (
          <>
            {/* Member: their week and what's scheduled, then their work. */}
            <div className={cn('grid grid-cols-1 gap-6', myWeekChart && insights.meetings && 'lg:grid-cols-3')}>
              {myWeekChart && <div className={cn(insights.meetings && 'lg:col-span-2')}>{myWeekChart}</div>}
              {insights.meetings && <ComingUpCard meetings={insights.meetings} />}
            </div>
            {showMyTasks && <MyTasksCard tasks={roleData.myTasks} stageOptions={roleData.taskStageOptions || []} role={role} />}
            {activeSprints.length > 0 && <ActiveSprintsCard sprints={activeSprints} role={role} />}
          </>
        )}

        {role === 'client' && (
          <>
            <Row>
              {roleData?.projectProgress && <ProjectProgressCard projects={roleData.projectProgress} />}
              {(insights.milestones || insights.meetings) && <ComingUpCard milestones={insights.milestones} meetings={insights.meetings} days={30} />}
            </Row>
            <Row>
              {insights.contractsToSign && <ContractsToSignCard contracts={insights.contractsToSign} />}
              {dashboardData?.invoices && <InvoiceStatusCard invoices={invoices} />}
            </Row>
          </>
        )}
      </div>
    </PageTemplate>
  );
}

/** Two cards side by side on wide screens; one card takes the full width. */
function Row({ children }: { children: ReactNode }) {
  const items = React.Children.toArray(children);
  if (items.length === 0) return null;
  return <div className={cn('grid grid-cols-1 gap-6', items.length > 1 && 'lg:grid-cols-2')}>{items}</div>;
}

/** The person's own open tasks, grouped by when they're due, each with a stage switcher. */
function MyTasksCard({ tasks, stageOptions, role }: { tasks: any[]; stageOptions: any[]; role: string }) {
  const { t } = useTranslation();
  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="flex items-center gap-2 text-base font-semibold">
          <CheckSquare className="h-4 w-4 text-ds-fg-muted" aria-hidden="true" />
          {role === 'member' ? t('My work') : t('My Tasks')}
          {tasks.length > 0 && (
            <Badge variant="secondary" className="tabular-nums">
              {tasks.length}
            </Badge>
          )}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {tasks.length === 0 ? (
          <div className="flex flex-col items-center gap-3 py-8 text-center">
            <div className="flex size-10 items-center justify-center rounded-full bg-ds-success-bg" aria-hidden="true">
              <CheckSquare className="h-5 w-5 text-ds-success" />
            </div>
            <p className="text-sm text-ds-fg-secondary">{t("You're all caught up! No open tasks assigned to you.")}</p>
          </div>
        ) : (
          <div className="space-y-4">
            {groupByDue(tasks).map(group => (
              <div key={group.key} className="space-y-2">
                <p className={`text-xs font-semibold uppercase tracking-wide ${group.key === 'overdue' ? 'text-ds-danger-fg' : 'text-ds-fg-muted'}`}>
                  {t(group.label)} · {group.tasks.length}
                </p>
                {group.tasks.map((task: any) => (
                  <div key={task.id} className="flex flex-col gap-3 rounded-lg border border-ds-border-subtle bg-ds-surface p-3 transition-colors hover:border-ds-border-strong sm:flex-row sm:items-center">
                    <div className="flex-1 min-w-0">
                      <Link href={route('tasks.index', { task_id: task.id })} className="rounded-sm text-sm font-semibold text-ds-fg transition-colors hover:text-ds-fg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus">
                        {task.title}
                      </Link>
                      <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ds-fg-muted">
                        {task.project && <span>{task.project.title}</span>}
                        <span className={`inline-flex items-center rounded-sm px-1.5 py-0.5 font-medium capitalize ${
                          task.priority === 'critical' || task.priority === 'high'
                            ? 'bg-ds-danger-bg text-ds-danger-fg'
                            : task.priority === 'medium'
                            ? 'bg-ds-warning-bg text-ds-warning-fg'
                            : 'bg-ds-neutral-bg text-ds-neutral-fg'
                        }`}>
                          {task.priority}
                        </span>
                        {task.end_date && (
                          <span className={`flex items-center gap-1 ${task.is_overdue ? 'font-medium text-ds-danger-fg' : ''}`}>
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
                      <SelectTrigger className="w-full sm:w-40 h-8 text-xs shrink-0" aria-label={t('Stage of {{title}}', { title: task.title })}>
                        <SelectValue placeholder={t('Set stage')} />
                      </SelectTrigger>
                      <SelectContent>
                        {stageOptions.map((stage: any) => (
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
  );
}

/** Client: how far along each of their projects is (projects, not task-level detail). */
function ProjectProgressCard({ projects }: { projects: any[] }) {
  const { t } = useTranslation();
  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="flex items-center gap-2 text-base font-semibold">
          <Target className="h-4 w-4 text-ds-fg-muted" aria-hidden="true" />
          {t('Project Progress')}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {projects.length === 0 ? (
          <div className="flex flex-col items-center gap-3 py-8 text-center">
            <div className="flex size-10 items-center justify-center rounded-full bg-brand-soft" aria-hidden="true">
              <FolderOpen className="h-5 w-5 text-brand-soft-fg" />
            </div>
            <p className="text-sm text-ds-fg-secondary">{t('No projects to show yet.')}</p>
          </div>
        ) : (
          <div className="space-y-3">
            {projects.map((project: any) => (
              <div key={project.id} className="rounded-lg border border-ds-border-subtle bg-ds-surface p-3">
                <div className="mb-2 flex items-center justify-between gap-3">
                  <Link href={route('projects.show', project.id)} className="truncate rounded-sm text-sm font-semibold text-ds-fg transition-colors hover:text-ds-fg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus">
                    {project.title}
                  </Link>
                  <span className="shrink-0 text-xs font-semibold tabular-nums text-ds-fg-accent">{project.progress}%</span>
                </div>
                <Progress value={project.progress} className="h-2" aria-label={t('{{title}} progress', { title: project.title })} />
                <div className="mt-2 flex items-center gap-2 text-xs text-ds-fg-muted">
                  <span className="capitalize">{project.status}</span>
                  {project.deadline && (
                    <>
                      <span>•</span>
                      <span className={project.is_overdue ? 'font-medium text-ds-danger-fg' : ''}>
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
  );
}

/** Client: their invoices by state (owners see the money in their strip). */
function InvoiceStatusCard({ invoices }: { invoices: { total: number; paid: number; pending: number; overdue: number } }) {
  const { t } = useTranslation();
  const states = [
    { key: 'paid', label: t('Paid'), value: invoices.paid, border: 'border-l-ds-success' },
    { key: 'pending', label: t('Pending'), value: invoices.pending, border: 'border-l-ds-warning' },
    { key: 'overdue', label: t('Overdue'), value: invoices.overdue, border: 'border-l-ds-danger' },
  ];
  return (
    <Card className="flex flex-col">
      <CardHeader className="flex flex-row items-center justify-between gap-3 space-y-0 pb-3">
        <CardTitle className="flex items-center gap-2 text-base font-semibold">
          <FileText className="h-4 w-4 text-ds-fg-muted" aria-hidden="true" />
          {t('Invoice Status')}
        </CardTitle>
        <Link href={route('invoices.index')} className="rounded-sm text-sm font-medium text-ds-fg-accent hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus">
          {t('View Invoices')}
        </Link>
      </CardHeader>
      <CardContent>
        <p className="mb-3 text-sm text-ds-fg-secondary">
          <span className="font-semibold text-ds-fg">{invoices.total.toLocaleString()}</span> {t('invoices in total')}
        </p>
        <div className="grid grid-cols-3 gap-3">
          {states.map(state => (
            <Link
              key={state.key}
              href={route('invoices.index', { status: state.key === 'pending' ? 'sent' : state.key })}
              className={cn('flex flex-col rounded-xl border border-ds-border-subtle border-l-[3px] bg-ds-surface p-3.5 transition-colors hover:bg-ds-surface-hover', state.border)}
            >
              <span className="text-xs font-semibold text-muted-foreground">{state.label}</span>
              <span className="mt-2 text-lg font-bold leading-none text-foreground">{state.value}</span>
            </Link>
          ))}
        </div>
      </CardContent>
    </Card>
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
          <UserPlus className="h-4 w-4 text-ds-fg-muted" aria-hidden="true" />
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
            className="flex items-center gap-3 rounded-lg border border-ds-border-subtle p-3 text-sm transition-colors hover:border-ds-border-strong hover:bg-ds-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus"
          >
            <Avatar className="h-8 w-8 shrink-0">
              <AvatarFallback seed={company.name} className="text-xs font-semibold">{initialsOf(company.name)}</AvatarFallback>
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
