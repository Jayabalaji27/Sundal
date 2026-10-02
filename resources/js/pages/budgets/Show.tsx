import React, { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import BudgetProgress from '@/components/budgets/BudgetProgress';
import BudgetFormModal from '@/components/budgets/BudgetFormModal';
import ExpenseFormModal from '@/components/expenses/ExpenseFormModal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Edit, Plus, Receipt, Calendar, DollarSign, TrendingUp, AlertTriangle, ArrowLeft, Eye, Wallet, BarChart3, User, Tag } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

export default function BudgetShow() {
    const { t } = useTranslation();
    const { budget, projects = [], permissions } = usePage().props as any;
    const { auth } = usePage().props as any;
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isExpenseModalOpen, setIsExpenseModalOpen] = useState(false);

    const formatText = (text: string) => {
        return text.replace(/_/g, ' ').split(' ').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    };

    const getStatusColor = (status: string) => {
        const colors = {
            active: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            completed: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const utilization = budget.utilization_percentage || 0;
    const utilizationColor = utilization >= 90 ? 'text-red-600' : utilization >= 75 ? 'text-yellow-600' : 'text-green-600';
    const barColor = utilization >= 90 ? '#ef4444' : utilization >= 75 ? '#f59e0b' : '#22c55e';

    const pageActions = [
        {
            label: t('View Project'),
            icon: <Eye className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('projects.show', budget.project.id))
        },
    ];

    if (permissions?.create) {
        pageActions.push({
            label: t('Add Expense'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default',
            onClick: () => setIsExpenseModalOpen(true)
        });
    }

    if (auth?.permissions?.includes('budget_dashboard_view')) {
        pageActions.push({
            label: t('View Expenses'),
            icon: <Receipt className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('expenses.index', { project_id: budget.project_id, budget_id: budget.id }))
        });
    }

    if (permissions?.update) {
        pageActions.push({
            label: t('Edit Budget'),
            icon: <Edit className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => setIsEditModalOpen(true)
        });
    }

    pageActions.push({
        label: t('Back'),
        icon: <ArrowLeft className="h-4 w-4 mr-2" />,
        variant: 'outline',
        onClick: () => router.get(route('budgets.index'))
    });

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Budget & Expenses'), href: route('budgets.dashboard') },
        { title: t('Budgets'), href: route('budgets.index') },
        { title: t('Budget Details') }
    ];

    return (
        <PageTemplate
            title={budget.project.title}
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <div className="space-y-6">

                {/* Budget Info Header */}
                <Card className="shadow-sm">
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between gap-4 mb-6">
                            <p className="text-sm text-gray-500 dark:text-gray-400" />
                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium shrink-0 ${getStatusColor(budget.status)}`}>
                                {formatText(budget.status)}
                            </span>
                        </div>
                        {/* Stat Cards */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div className="flex items-center gap-3 p-4 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div className="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center shrink-0">
                                    <DollarSign className="h-5 w-5 text-blue-600 dark:text-blue-300" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{t('Total Budget')}</p>
                                    <p className="text-base font-bold text-blue-600 dark:text-blue-400 truncate">{formatCurrency(budget.total_budget || 0)}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 p-4 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div className="h-10 w-10 rounded-lg bg-orange-100 dark:bg-orange-900 flex items-center justify-center shrink-0">
                                    <TrendingUp className="h-5 w-5 text-orange-600 dark:text-orange-300" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{t('Total Spent')}</p>
                                    <p className="text-base font-bold text-orange-600 dark:text-orange-400 truncate">{formatCurrency(budget.total_spent || 0)}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 p-4 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div className={`h-10 w-10 rounded-lg flex items-center justify-center shrink-0 ${(budget.remaining_budget || 0) < 0 ? 'bg-red-100 dark:bg-red-900' : 'bg-green-100 dark:bg-green-900'}`}>
                                    <Wallet className={`h-5 w-5 ${(budget.remaining_budget || 0) < 0 ? 'text-red-600 dark:text-red-300' : 'text-green-600 dark:text-green-300'}`} />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{t('Remaining')}</p>
                                    <p className={`text-base font-bold truncate ${(budget.remaining_budget || 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                                        {formatCurrency(budget.remaining_budget || 0)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 p-4 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div className={`h-10 w-10 rounded-lg flex items-center justify-center shrink-0 ${utilization >= 90 ? 'bg-red-100 dark:bg-red-900' : utilization >= 75 ? 'bg-yellow-100 dark:bg-yellow-900' : 'bg-purple-100 dark:bg-purple-900'}`}>
                                    {utilization >= 75 ? (
                                        <AlertTriangle className={`h-5 w-5 ${utilization >= 90 ? 'text-red-600 dark:text-red-300' : 'text-yellow-600 dark:text-yellow-300'}`} />
                                    ) : (
                                        <BarChart3 className="h-5 w-5 text-purple-600 dark:text-purple-300" />
                                    )}
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{t('Utilization')}</p>
                                    <p className={`text-base font-bold ${utilizationColor}`}>{utilization.toFixed(1)}%</p>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                            <div>
                                {/* <div className="flex items-center gap-2 mb-1">
                                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(budget.status)}`}>
                                        {formatText(budget.status)}
                                    </span>
                                </div> */}
                                <p className="text-sm text-gray-500 dark:text-gray-400">{budget.description || t('Project Budget Overview')}</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400 shrink-0">
                                <span className="flex items-center gap-1.5">
                                    {/* <Calendar className="h-4 w-4" /> */}
                                    <span className="capitalize">{formatText(budget.period_type)} {t('Budget')}</span>
                                </span>
                                <span className="text-gray-300 dark:text-gray-600">•</span>
                                <span className="flex items-center gap-1.5">
                                    <span>{budget.start_date ? window.appSettings.formatDateTime(new Date(budget.start_date),false) : t('Not set')}</span>
                                    <span>→</span>
                                    <span>{budget.end_date ? window.appSettings.formatDateTime(new Date(budget.end_date),false) : t('Ongoing')}</span>
                                </span>
                            </div>
                        </div>

                        

                        {/* Overall Progress Bar */}
                        <div className="space-y-1.5">
                            <div className="flex justify-between text-xs">
                                <span className="text-gray-500 dark:text-gray-400">{t('Budget Progress')}</span>
                                <span className={`font-semibold ${utilizationColor}`}>{utilization.toFixed(1)}%</span>
                            </div>
                            <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                                <div
                                    className="h-2 rounded-full transition-all duration-500"
                                    style={{ width: `${Math.min(utilization, 100)}%`, backgroundColor: barColor }}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Budget Progress (Categories) */}
                <BudgetProgress budget={budget} />

                {/* Recent Expenses */}
                <Card className="shadow-sm">
                    <CardHeader className="pb-3 border-b">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                    <Receipt className="h-4 w-4 text-primary" />
                                    {t('Recent Expenses')}
                                </CardTitle>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{t('Latest expense submissions for this project')}</p>
                            </div>
                            {auth?.permissions?.includes('budget_dashboard_view') && (
                                <Button variant="outline" size="sm" onClick={() => router.get(route('expenses.index', { project_id: budget.project_id }))}>
                                    <Eye className="h-4 w-4 mr-2" />
                                    {t('View All')}
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {budget.expenses?.length > 0 ? (
                            <div className="divide-y divide-gray-100 dark:divide-gray-800">
                                {budget.expenses.slice(0, 5).map((expense: any) => (
                                    <div
                                        key={expense.id}
                                        className="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors cursor-pointer"
                                        onClick={() => router.get(route('expenses.show', expense.id))}
                                    >
                                        <div className="flex items-center gap-3 flex-1 min-w-0">
                                            <div className="h-9 w-9 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                                <DollarSign className="h-4 w-4 text-primary" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">{expense.title}</p>
                                                <div className="flex items-center gap-2 mt-0.5 flex-wrap">
                                                    <span className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                        {/* <User className="h-3 w-3" /> */}
                                                        <Avatar className="h-4 w-4 rounded-full">
                                                            <AvatarImage src={expense.submitter?.avatar || undefined} alt={expense.submitter?.name} />
                                                            <AvatarFallback>{expense.submitter?.name ? expense.submitter.name.charAt(0) : '?'}</AvatarFallback>
                                                        </Avatar>
                                                        {expense.submitter?.name}
                                                    </span>
                                                    <span className="text-gray-300 dark:text-gray-600">•</span>
                                                    <span className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                        <Calendar className="h-3 w-3" />
                                                        {window.appSettings.formatDateTime(new Date(expense.expense_date),false)}
                                                    </span>
                                                    {expense.budget_category && (
                                                        <>
                                                            <span className="text-gray-300 dark:text-gray-600">•</span>
                                                            <span className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                                <span className="h-2 w-2 rounded-full shrink-0" style={{ backgroundColor: expense.budget_category.color }} />
                                                                {expense.budget_category.name}
                                                            </span>
                                                        </>
                                                    )}
                                                    {expense.vendor && (
                                                        <>
                                                            <span className="text-gray-300 dark:text-gray-600">•</span>
                                                            <span className="text-xs text-gray-500 dark:text-gray-400">{expense.vendor}</span>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-right shrink-0 ml-4">
                                            <p className="font-bold text-sm text-gray-900 dark:text-gray-100">{formatCurrency(expense.amount)}</p>
                                            <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium mt-1 ${
                                                expense.status === 'approved' ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' :
                                                expense.status === 'rejected' ? 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20' :
                                                'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20'
                                            }`}>
                                                {formatText(expense.status)}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                                {budget.expenses.length > 5 && (
                                    <div className="px-6 py-3 text-center">
                                        <Button variant="default" size="sm" onClick={() => router.get(route('expenses.index', { project_id: budget.project_id }))}>
                                            {t('View')} {t('all expenses')} →
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-14">
                                <div className="h-12 w-12 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <Receipt className="h-6 w-6 text-gray-400" />
                                </div>
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{t('No expenses submitted yet')}</p>
                                <p className="text-xs text-gray-400 mt-1 mb-4">{t('Start tracking your project expenses to monitor budget utilization.')}</p>
                                {permissions?.create && (
                                    <Button size="sm" onClick={() => setIsExpenseModalOpen(true)}>
                                        <Plus className="h-4 w-4 mr-2" />
                                        {t('Submit First Expense')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <BudgetFormModal
                isOpen={isEditModalOpen}
                onClose={() => setIsEditModalOpen(false)}
                budget={budget}
                mode="edit"
            />

            <ExpenseFormModal
                isOpen={isExpenseModalOpen}
                onClose={() => setIsExpenseModalOpen(false)}
                projects={projects}
                mode="create"
                currentProject={{
                    ...budget.project,
                    budget: { categories: budget.categories || [] }
                }}
                redirectUrl={route('budgets.show', budget.id)}
            />
        </PageTemplate>
    );
}
