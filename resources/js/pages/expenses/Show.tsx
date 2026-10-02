import React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Calendar, DollarSign, User, Building, CheckSquare, Edit } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';
import { useTranslation } from 'react-i18next';

interface Expense {
    id: number;
    project_id: number;
    budget_category_id?: number;
    task_id?: number;
    amount: number;
    currency: string;
    expense_date: string;
    title: string;
    description?: string;
    status: string;
    project: {
        id: number;
        title: string;
    };
    budgetCategory?: {
        id: number;
        name: string;
        color: string;
    };
    task?: {
        id: number;
        title: string;
    };
    submitter: {
        id: number;
        name: string;
    };

}

interface Props {
    expense: Expense;
    permissions?: any;
}

export default function Show({ expense, permissions }: Props) {
    const { t } = useTranslation();
    const { permissions: pagePermissions } = usePage().props as any;
    const expensePermissions = permissions || pagePermissions;
    const getStatusColor = (status: string) => {
        switch (status) {
            case 'approved': return 'bg-green-100 text-green-800';
            case 'rejected': return 'bg-red-100 text-red-800';
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Expenses'), href: route('expenses.index') },
        { title: expense.title }
    ];

    const pageActions = [
        {
            label: t('Back to Expenses'),
            icon: <ArrowLeft className="h-4 w-4 mr-2" />,
            variant: 'outline' as const,
            onClick: () => router.visit(route('expenses.index'))
        }
    ];
    
    if (expensePermissions?.update) {
        pageActions.unshift({
            label: t('Edit Expense'),
            icon: <Edit className="h-4 w-4 mr-2" />,
            variant: 'default' as const,
            onClick: () => router.visit(route('expenses.edit', expense.id))
        });
    }

    return (
        <PageTemplate 
            title={`${t('Expense')}: ${expense.title}`}
            url={`/expenses/${expense.id}`}
            breadcrumbs={breadcrumbs}
            actions={pageActions}
        >
            <div className="max-w-4xl mx-auto space-y-6">
                <Card>
                    <CardHeader className="pb-4">
                        <div className="flex justify-between items-start">
                            <div>
                                <CardTitle className="text-xl font-bold">{expense.title}</CardTitle>
                                <p className="text-muted-foreground mt-2 text-lg">{t('Expense')} #{expense.id}</p>
                            </div>
                            <Badge className={`${getStatusColor(expense.status)} text-sm px-3 py-1`}>
                                {expense.status.charAt(0).toUpperCase() + expense.status.slice(1)}
                            </Badge>
                        </div>
                    </CardHeader>
                </Card>

                <Card className="bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200">
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <div className="flex items-center justify-center gap-2 mb-2">
                                <DollarSign className="h-8 w-8 text-blue-600" />
                                <span className="text-4xl font-bold text-blue-900">
                                    {formatCurrency(expense.amount)}
                                </span>
                            </div>
                            <p className="text-blue-700 font-medium">{t('Total Expense Amount')}</p>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">{t('Basic Information')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <Calendar className="h-5 w-5 text-gray-600" />
                                <div>
                                    <p className="text-sm font-medium text-gray-600">{t('Expense Date')}</p>
                                    <p className="font-semibold text-gray-900">
                                        {new Date(expense.expense_date).toLocaleDateString('en-US', {
                                            weekday: 'long',
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric'
                                        })}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <User className="h-5 w-5 text-gray-600" />
                                <div>
                                    <p className="text-sm font-medium text-gray-600">{t('Submitted By')}</p>
                                    <p className="font-semibold text-gray-900">{expense.submitter.name}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">{t('Project Details')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <Building className="h-5 w-5 text-gray-600" />
                                <div>
                                    <p className="text-sm font-medium text-gray-600">{t('Project')}</p>
                                    <p className="font-semibold text-gray-900">{expense.project.title}</p>
                                </div>
                            </div>

                            {expense.budgetCategory && (
                                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div 
                                        className="w-5 h-5 rounded-full border-2 border-white shadow-sm"
                                        style={{ backgroundColor: expense.budgetCategory.color }}
                                    />
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">{t('Budget Category')}</p>
                                        <p className="font-semibold text-gray-900">{expense.budgetCategory.name}</p>
                                    </div>
                                </div>
                            )}

                            {expense.task && (
                                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <CheckSquare className="h-5 w-5 text-gray-600" />
                                    <div>
                                        <p className="text-sm font-medium text-gray-600">{t('Related Task')}</p>
                                        <p className="font-semibold text-gray-900">{expense.task.title}</p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {expense.description && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">{t('Description')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="bg-gray-50 p-4 rounded-lg">
                                <p className="text-gray-700 leading-relaxed">{expense.description}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </PageTemplate>
    );
}