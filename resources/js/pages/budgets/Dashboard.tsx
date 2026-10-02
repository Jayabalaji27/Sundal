import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { TrendingUp, Eye, DollarSign, CheckCircle, Wallet, BarChart3, Clock, Tag, User, FolderOpen, Calendar, AlertTriangle } from 'lucide-react';
import { router } from '@inertiajs/react';
import { formatCurrency } from '@/utils/currency';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

interface BudgetDashboardProps {
    initialData?: any;
}

export default function BudgetDashboard({ initialData }: BudgetDashboardProps) {
    const { t } = useTranslation();
    const [dashboardData, setDashboardData] = useState<any>(initialData || null);
    const [loading, setLoading] = useState(!initialData);
    const [hoveredCategory, setHoveredCategory] = useState<number | null>(null);

    const formatText = (text: string) => {
        return text.replace(/_/g, ' ').split(' ').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    };

    useEffect(() => {
        if (!initialData) loadDashboardData();
    }, [initialData]);

    const loadDashboardData = async () => {
        try {
            const response = await fetch(route('budget-dashboard.overview'));
            const data = await response.json();
            setDashboardData(data);
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
        } finally {
            setLoading(false);
        }
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Budget & Expenses'), href: route('budgets.dashboard') },
        { title: t('Budget Dashboard') }
    ];

    const summary = dashboardData?.summary;

    const statCards = [
        {
            title: t('Total Budget'),
            value: formatCurrency(summary?.total_budget || 0),
            icon: DollarSign,
            iconBg: 'bg-blue-100 dark:bg-blue-900',
            iconColor: 'text-blue-600 dark:text-blue-300',
            valueColor: 'text-blue-600 dark:text-blue-400',
        },
        {
            title: t('Total Spent'),
            value: formatCurrency(summary?.total_spent || 0),
            icon: TrendingUp,
            iconBg: 'bg-orange-100 dark:bg-orange-900',
            iconColor: 'text-orange-600 dark:text-orange-300',
            valueColor: 'text-orange-600 dark:text-orange-400',
        },
        {
            title: t('Remaining'),
            value: formatCurrency(summary?.remaining_budget || 0),
            icon: Wallet,
            iconBg: (summary?.remaining_budget || 0) < 0 ? 'bg-red-100 dark:bg-red-900' : 'bg-green-100 dark:bg-green-900',
            iconColor: (summary?.remaining_budget || 0) < 0 ? 'text-red-600 dark:text-red-300' : 'text-green-600 dark:text-green-300',
            valueColor: (summary?.remaining_budget || 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400',
        },
        {
            title: t('Avg Utilization'),
            value: `${(summary?.average_utilization || 0).toFixed(1)}%`,
            icon: BarChart3,
            iconBg: (summary?.average_utilization || 0) >= 90 ? 'bg-red-100 dark:bg-red-900' : (summary?.average_utilization || 0) >= 75 ? 'bg-yellow-100 dark:bg-yellow-900' : 'bg-purple-100 dark:bg-purple-900',
            iconColor: (summary?.average_utilization || 0) >= 90 ? 'text-red-600 dark:text-red-300' : (summary?.average_utilization || 0) >= 75 ? 'text-yellow-600 dark:text-yellow-300' : 'text-purple-600 dark:text-purple-300',
            valueColor: (summary?.average_utilization || 0) >= 90 ? 'text-red-600 dark:text-red-400' : (summary?.average_utilization || 0) >= 75 ? 'text-yellow-600 dark:text-yellow-400' : 'text-purple-600 dark:text-purple-400',
        },
        {
            title: t('Active Budgets'),
            value: summary?.active_budgets || 0,
            icon: CheckCircle,
            iconBg: 'bg-teal-100 dark:bg-teal-900',
            iconColor: 'text-teal-600 dark:text-teal-300',
            valueColor: 'text-teal-600 dark:text-teal-400',
        },
        {
            title: t('Pending Approvals'),
            value: summary?.pending_approvals || 0,
            icon: Clock,
            iconBg: (summary?.pending_approvals || 0) > 0 ? 'bg-yellow-100 dark:bg-yellow-900' : 'bg-gray-100 dark:bg-gray-800',
            iconColor: (summary?.pending_approvals || 0) > 0 ? 'text-yellow-600 dark:text-yellow-300' : 'text-gray-400',
            valueColor: (summary?.pending_approvals || 0) > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400',
        },
    ];

    const allExpenses = dashboardData?.recent_expenses || [];

    if (loading) {
        return (
            <PageTemplate
                title={t('Budget Dashboard')}
                description={t('Overview of budget performance and expense tracking')}
                url="/budgets-dashboard"
                breadcrumbs={breadcrumbs}
            >
                <div className="flex items-center justify-center h-64">
                    <div className="text-gray-500">{t('Loading dashboard...')}</div>
                </div>
            </PageTemplate>
        );
    }

    return (
        <PageTemplate
            title={t('Budget Dashboard')}
            description={t('Overview of budget performance and expense tracking')}
            url="/budgets-dashboard"
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <div className="space-y-6">

                {/* Stat Cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
                    {statCards.map((card, index) => (
                        <Card key={index} className="shadow-sm hover:shadow-md transition-shadow duration-200 cursor-default">
                            <CardContent className="p-4">
                                <div className="flex flex-col gap-3">
                                    <div className={`h-10 w-10 rounded-lg flex items-center justify-center ${card.iconBg}`}>
                                        <card.icon className={`h-5 w-5 ${card.iconColor}`} />
                                    </div>
                                    <div>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{card.title}</p>
                                        <p className={`text-lg font-bold leading-tight ${card.valueColor}`}>{card.value}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Recent Expenses + Top Categories */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">

                    {/* Recent Expenses */}
                    <Card className="lg:col-span-2 shadow-sm flex flex-col" style={{ minHeight: '560px' }}>
                        <CardHeader className="pb-3 border-b shrink-0">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                        <TrendingUp className="h-4 w-4 text-primary" />
                                        {t('Recent Expenses')}
                                    </CardTitle>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {allExpenses.length} {t('total records')}
                                    </p>
                                </div>
                                <Button variant="outline" size="sm" onClick={() => router.get(route('expenses.index'))}>
                                    <Eye className="h-4 w-4 mr-2" />
                                    {t('View All')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0 overflow-y-auto" style={{ maxHeight: '585px' }}>
                            {allExpenses.length > 0 ? (
                                <div className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {allExpenses.map((expense: any) => (
                                        <div
                                            key={expense.id || Math.random()}
                                            className="group flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors cursor-pointer"
                                            onClick={() => expense.id && router.get(route('expenses.show', expense.id))}
                                        >
                                            <div className="flex items-center gap-3 flex-1 min-w-0">
                                                <div className="h-9 w-9 rounded-lg bg-primary/10 flex items-center justify-center shrink-0 group-hover:bg-primary/20 transition-colors">
                                                    <DollarSign className="h-4 w-4 text-primary" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                        {expense.title || t('Untitled Expense')}
                                                    </p>
                                                    <div className="flex items-center gap-2 mt-0.5 flex-wrap">
                                                        <span className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                            <FolderOpen className="h-3 w-3" />
                                                            <span className="truncate max-w-[100px]">{expense.project?.title || t('No Project')}</span>
                                                        </span>
                                                        <span className="text-gray-300 dark:text-gray-600">•</span>
                                                        <span className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                            {/* <User className="h-3 w-3" /> */}
                                                            <Avatar className="h-4 w-4 rounded-full">
                                                                <AvatarImage src={expense.submitter?.avatar || undefined} alt={expense.submitter?.name} />
                                                                <AvatarFallback>{expense.submitter?.name ? expense.submitter.name.charAt(0) : '?'}</AvatarFallback>
                                                            </Avatar>
                                                            <span className="truncate max-w-[80px]">{expense.submitter?.name || t('Unknown')}</span>
                                                        </span>
                                                        {expense.expense_date && (
                                                            <>
                                                                <span className="text-gray-300 dark:text-gray-600">•</span>
                                                                <span className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                                    <Calendar className="h-3 w-3" />
                                                                    {window.appSettings.formatDateTime(new Date(expense.expense_date),false)}
                                                                </span>
                                                            </>
                                                        )}
                                                        {expense.budgetCategory?.name && (
                                                            <>
                                                                <span className="text-gray-300 dark:text-gray-600">•</span>
                                                                <span className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                                    <Tag className="h-3 w-3" />
                                                                    {expense.budgetCategory.name}
                                                                </span>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right shrink-0 ml-4">
                                                <p className="font-bold text-sm text-gray-900 dark:text-gray-100">{formatCurrency(expense.amount || 0)}</p>
                                                <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium mt-1 ${
                                                    expense.status === 'approved' ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' :
                                                    expense.status === 'rejected' ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20' :
                                                    'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20'
                                                }`}>
                                                    {formatText(expense.status || 'pending')}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-14">
                                    <div className="h-12 w-12 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <TrendingUp className="h-6 w-6 text-gray-400" />
                                    </div>
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{t('No recent expenses')}</p>
                                    <p className="text-xs text-gray-400 mt-1">{t('Expenses will appear here once submitted')}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Top Categories */}
                    <Card className="shadow-sm flex flex-col" style={{ minHeight: '560px' }}>
                        <CardHeader className="pb-3 border-b">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                        <Tag className="h-4 w-4 text-primary" />
                                        {t('Top Categories')}
                                    </CardTitle>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{t('By approved spend')}</p>
                                </div>
                                {dashboardData?.top_categories?.length > 0 && (
                                    <span className="text-xs font-medium text-gray-400 dark:text-gray-500">
                                        {dashboardData.top_categories.length} {t('categories')}
                                    </span>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="pt-4 flex-1 overflow-y-auto">
                            {dashboardData?.top_categories && dashboardData.top_categories.length > 0 ? (
                                <div className="space-y-1">
                                    {dashboardData.top_categories.slice(0, 5).map((category: any, index: number) => {
                                        const pct = Math.min(((category.total_spent || 0) / (category.allocated_amount || 1)) * 100, 100);
                                        const isOver = pct >= 90;
                                        const isWarn = pct >= 75 && pct < 90;
                                        const isHovered = hoveredCategory === index;
                                        const barColor = isOver ? '#ef4444' : isWarn ? '#f59e0b' : (category.color || '#6B7280');
                                        return (
                                            <div
                                                key={index}
                                                className={`p-3 rounded-lg transition-colors duration-150 cursor-default ${isHovered ? 'bg-gray-50 dark:bg-gray-800/50' : ''}`}
                                                onMouseEnter={() => setHoveredCategory(index)}
                                                onMouseLeave={() => setHoveredCategory(null)}
                                            >
                                                <div className="flex items-center justify-between mb-2">
                                                    <div className="flex items-center gap-2 min-w-0">
                                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{category.name || t('Unknown')}</span>
                                                        {(isOver || isWarn) && (
                                                            <AlertTriangle className={`h-3.5 w-3.5 shrink-0 ${isOver ? 'text-red-500' : 'text-yellow-500'}`} />
                                                        )}
                                                    </div>
                                                    <span className={`text-xs font-bold shrink-0 ml-2 ${isOver ? 'text-red-600 dark:text-red-400' : isWarn ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-500 dark:text-gray-400'}`}>
                                                        {pct.toFixed(0)}%
                                                    </span>
                                                </div>
                                                <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                                    <div
                                                        className="h-2 rounded-full transition-all duration-500"
                                                        style={{ width: `${pct}%`, backgroundColor: barColor }}
                                                    />
                                                </div>
                                                <div className={`flex justify-between mt-1.5 transition-opacity duration-150 ${isHovered ? 'opacity-100' : 'opacity-60'}`}>
                                                    <span className="text-xs font-medium text-gray-600 dark:text-gray-300">{formatCurrency(category.total_spent || 0)}</span>
                                                    <span className="text-xs text-gray-900 dark:text-gray-500">of {formatCurrency(category.allocated_amount || 0)}</span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="text-center py-12">
                                    <div className="h-12 w-12 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <Tag className="h-6 w-6 text-gray-400" />
                                    </div>
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{t('No categories')}</p>
                                    <p className="text-xs text-gray-400 mt-1">{t('Categories will appear here')}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                </div>
            </div>
        </PageTemplate>
    );
}
