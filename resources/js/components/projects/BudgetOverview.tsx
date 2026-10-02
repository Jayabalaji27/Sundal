import React from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { DollarSign, Eye, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Budget {
    id: number;
    total_budget: number;
    currency: string;
    period_type: string;
    status: string;
    total_spent: number;
    remaining_budget: number;
    utilization_percentage: number;
    start_date: string;
    end_date?: string;
    categories: Array<{
        id: number;
        name: string;
        allocated_amount: number;
        color: string;
        total_spent: number;
    }>;
}

interface Props {
    budget: Budget | null;
    canManage: boolean;
}

export default function BudgetOverview({ budget, canManage }: Props) {
    const { t } = useTranslation();
    
    if (!budget) {
        return (
            <div className="text-center py-8 text-gray-500">
                <DollarSign className="h-12 w-12 mx-auto mb-4 opacity-50" />
                <p>{t('No budget created for this project yet.')}</p>
                {canManage && (
                    <Button className="mt-4" onClick={() => router.get(route('budgets.index'))}>
                        <Plus className="h-4 w-4 mr-2" />
                        {t('Create Budget')}
                    </Button>
                )}
            </div>
        );
    }

    const formatCurrency = (amount: number, currency: string) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };

    const getStatusColor = (status: string) => {
        const colors = {
            active: 'bg-green-100 text-green-800',
            completed: 'bg-blue-100 text-blue-800',
            cancelled: 'bg-red-100 text-red-800'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    return (
        <div className="space-y-6">
            {/* Budget Summary */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <DollarSign className="h-4 w-4 text-blue-500" />
                            <span className="text-sm font-medium">{t('Total Budget')}</span>
                        </div>
                        <p className="text-2xl font-bold text-blue-600">
                            {formatCurrency(budget.total_budget || 0)}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">{t('Total Spent')}</span>
                        </div>
                        <p className="text-2xl font-bold text-red-600">
                            {formatCurrency(budget.total_spent || 0)}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">{t('Remaining')}</span>
                        </div>
                        <p className="text-2xl font-bold text-green-600">
                            {formatCurrency(budget.remaining_budget || 0)}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">{t('Utilization')}</span>
                        </div>
                        <p className="text-2xl font-bold">{(budget.utilization_percentage || 0).toFixed(1)}%</p>
                        <Progress value={budget.utilization_percentage || 0} className="h-2 mt-2" />
                    </CardContent>
                </Card>
            </div>

            {/* Budget Details */}
            <Card>
                <CardHeader>
                    <div className="flex justify-between items-center">
                        <div>
                            <CardTitle className="capitalize">{budget.period_type} {t('Budget')}</CardTitle>
                            <div className="flex items-center gap-2 mt-1">
                                <Badge className={getStatusColor(budget.status)}>{budget.status}</Badge>
                                <span className="text-sm text-gray-500">
                                    {budget.start_date ? new Date(budget.start_date).toLocaleDateString() : t('Not set')} {budget.end_date ? `- ${new Date(budget.end_date).toLocaleDateString()}` : ''}
                                </span>
                            </div>
                        </div>
                        <Button variant="outline" onClick={() => router.get(route('budgets.show', budget.id))}>
                            <Eye className="h-4 w-4 mr-2" />
                            {t('View Details')}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <div className="flex justify-between">
                            <span className="font-medium">{t('Budget Utilization')}</span>
                            <span className="font-bold">{(budget.utilization_percentage || 0).toFixed(1)}%</span>
                        </div>
                        <Progress value={budget.utilization_percentage || 0} className="h-3" />
                    </div>

                    {budget.categories?.length > 0 && (
                        <div>
                            <h4 className="font-medium mb-3">{t('Budget Categories')}</h4>
                            <div className="space-y-2">
                                {budget.categories.map((category) => (
                                    <div key={category.id} className="flex justify-between items-center p-3 bg-gray-50 rounded">
                                        <div className="flex items-center gap-2">
                                            <div className="w-3 h-3 rounded-full" style={{ backgroundColor: category.color }} />
                                            <span className="font-medium">{category.name}</span>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-medium">{formatCurrency(category.allocated_amount)}</p>
                                            <p className="text-xs text-gray-500">
                                                {t('Spent')}: {formatCurrency(category.total_spent || 0)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}