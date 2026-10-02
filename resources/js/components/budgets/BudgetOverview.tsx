import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { DollarSign, TrendingUp, AlertTriangle, CheckCircle } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';

interface BudgetOverviewProps {
    summary: {
        total_budget: number;
        total_spent: number;
        remaining_budget: number;
        average_utilization: number;
        active_budgets: number;
        pending_approvals: number;
    };
    currency?: string;
}

export default function BudgetOverview({ summary, currency = 'USD' }: BudgetOverviewProps) {
    if (!summary) {
        return (
            <div className="text-center py-8 text-gray-500">
                <p>Loading budget overview...</p>
            </div>
        );
    }



    const cards = [
        {
            title: 'Total Budget',
            value: formatCurrency(summary.total_budget || 0),
            icon: DollarSign,
            color: 'text-blue-600',
            bgColor: 'bg-blue-100'
        },
        {
            title: 'Total Spent',
            value: formatCurrency(summary.total_spent || 0),
            icon: TrendingUp,
            color: 'text-green-600',
            bgColor: 'bg-green-100'
        },
        {
            title: 'Remaining Budget',
            value: formatCurrency(summary.remaining_budget || 0),
            icon: CheckCircle,
            color: (summary.remaining_budget || 0) < 0 ? 'text-red-600' : 'text-green-600',
            bgColor: (summary.remaining_budget || 0) < 0 ? 'bg-red-100' : 'bg-green-100'
        },
        {
            title: 'Average Utilization',
            value: `${(summary.average_utilization || 0).toFixed(1)}%`,
            icon: TrendingUp,
            color: (summary.average_utilization || 0) >= 90 ? 'text-red-600' : 
                   (summary.average_utilization || 0) >= 75 ? 'text-yellow-600' : 'text-green-600',
            bgColor: (summary.average_utilization || 0) >= 90 ? 'bg-red-100' : 
                     (summary.average_utilization || 0) >= 75 ? 'bg-yellow-100' : 'bg-green-100'
        }
    ];

    return (
        <div className="space-y-6">
            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {cards.map((card, index) => (
                    <Card key={index}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">
                                {card.title}
                            </CardTitle>
                            <div className={`p-2 rounded-full ${card.bgColor}`}>
                                <card.icon className={`h-4 w-4 ${card.color}`} />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className={`text-2xl font-bold ${card.color}`}>
                                {card.value}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Status Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium text-gray-600">
                            Active Budgets
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div className="text-2xl font-bold text-blue-600">
                                {summary.active_budgets || 0}
                            </div>
                            <Badge variant="secondary" className="bg-blue-100 text-blue-800">
                                Active
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-medium text-gray-600">
                            Pending Approvals
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div className={`text-2xl font-bold ${(summary.pending_approvals || 0) > 0 ? 'text-yellow-600' : 'text-green-600'}`}>
                                {summary.pending_approvals || 0}
                            </div>
                            {(summary.pending_approvals || 0) > 0 ? (
                                <Badge variant="secondary" className="bg-yellow-100 text-yellow-800 flex items-center gap-1">
                                    <AlertTriangle className="h-3 w-3" />
                                    Pending
                                </Badge>
                            ) : (
                                <Badge variant="secondary" className="bg-green-100 text-green-800 flex items-center gap-1">
                                    <CheckCircle className="h-3 w-3" />
                                    Clear
                                </Badge>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}