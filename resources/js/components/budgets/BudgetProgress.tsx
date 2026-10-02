import React from 'react';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, TrendingUp, TrendingDown } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';

interface BudgetProgressProps {
    budget: {
        total_budget: number;
        total_spent: number;
        remaining_budget: number;
        utilization_percentage: number;
        currency: string;
        categories: Array<{
            id: number;
            name: string;
            allocated_amount: number;
            total_spent: number;
            utilization_percentage: number;
            color: string;
        }>;
    };
}

export default function BudgetProgress({ budget }: BudgetProgressProps) {


    const getUtilizationColor = (percentage: number) => {
        if (percentage >= 90) return 'text-red-600';
        if (percentage >= 75) return 'text-yellow-600';
        return 'text-green-600';
    };

    const getProgressColor = (percentage: number) => {
        if (percentage >= 90) return 'bg-red-500';
        if (percentage >= 75) return 'bg-yellow-500';
        return 'bg-green-500';
    };

    return (
        <div className="space-y-6">
            {/* Overall Budget Progress */}
            <div className="bg-white p-6 rounded-lg border">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold">Budget Overview</h3>
                    {(budget.utilization_percentage || 0) >= 90 && (
                        <Badge variant="destructive" className="flex items-center gap-1">
                            <AlertTriangle className="h-3 w-3" />
                            Critical
                        </Badge>
                    )}
                </div>

                <div className="space-y-4">
                    <div className="flex justify-between text-sm">
                        <span>Budget Utilization</span>
                        <span className={getUtilizationColor(budget.utilization_percentage || 0)}>
                            {(budget.utilization_percentage || 0).toFixed(1)}%
                        </span>
                    </div>
                    
                    <div className="relative">
                        <Progress value={budget.utilization_percentage || 0} className="h-3" />
                        <div 
                            className={`absolute top-0 left-0 h-3 rounded-full transition-all ${getProgressColor(budget.utilization_percentage || 0)}`}
                            style={{ width: `${Math.min(budget.utilization_percentage || 0, 100)}%` }}
                        />
                    </div>

                    <div className="grid grid-cols-3 gap-4 text-sm">
                        <div className="text-center">
                            <div className="font-medium text-gray-900">
                                {formatCurrency(budget.total_budget || 0)}
                            </div>
                            <div className="text-gray-500">Total Budget</div>
                        </div>
                        <div className="text-center">
                            <div className="font-medium text-gray-900">
                                {formatCurrency(budget.total_spent || 0)}
                            </div>
                            <div className="text-gray-500">Spent</div>
                        </div>
                        <div className="text-center">
                            <div className={`font-medium ${(budget.remaining_budget || 0) < 0 ? 'text-red-600' : 'text-green-600'}`}>
                                {formatCurrency(budget.remaining_budget || 0)}
                            </div>
                            <div className="text-gray-500">Remaining</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Category Breakdown */}
            <div className="bg-white rounded-lg shadow-sm border">
                <div className="p-6 border-b">
                    <h3 className="text-xl font-semibold text-gray-900">Budget Categories</h3>
                    <p className="text-sm text-gray-500 mt-1">Track spending across different categories</p>
                </div>
                
                <div className="p-6">
                    <div className="grid gap-6">
                        {budget.categories.map((category) => (
                            <div key={category.id} className="bg-gray-50 rounded-xl p-5 hover:bg-gray-100 transition-colors">
                                <div className="flex items-center justify-between mb-4">
                                    <div className="flex items-center gap-3">
                                        <div 
                                            className="w-4 h-4 rounded-full shadow-sm"
                                            style={{ backgroundColor: category.color }}
                                        />
                                        <div>
                                            <h4 className="font-semibold text-gray-900">{category.name}</h4>
                                            <p className="text-sm text-gray-500">{category.description || 'No description'}</p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className={`text-lg font-bold ${getUtilizationColor(category.utilization_percentage || 0)}`}>
                                            {(category.utilization_percentage || 0).toFixed(1)}%
                                        </div>
                                        <div className="flex items-center gap-1 text-sm">
                                            {(category.utilization_percentage || 0) > 100 ? (
                                                <>
                                                    <TrendingUp className="h-3 w-3 text-red-500" />
                                                    <span className="text-red-600 font-medium">Over budget</span>
                                                </>
                                            ) : (
                                                <>
                                                    <TrendingDown className="h-3 w-3 text-green-500" />
                                                    <span className="text-green-600 font-medium">On track</span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="space-y-3">
                                    <div className="relative">
                                        <div className="bg-gray-200 rounded-full h-3">
                                            <div 
                                                className="h-3 rounded-full transition-all duration-300 shadow-sm"
                                                style={{ 
                                                    backgroundColor: category.color,
                                                    width: `${Math.min(category.utilization_percentage || 0, 100)}%`
                                                }}
                                            />
                                        </div>
                                    </div>
                                    
                                    <div className="grid grid-cols-3 gap-4 text-sm">
                                        <div className="text-center">
                                            <div className="font-semibold text-gray-900">
                                                {formatCurrency(category.allocated_amount || 0)}
                                            </div>
                                            <div className="text-gray-500">Allocated</div>
                                        </div>
                                        <div className="text-center">
                                            <div className="font-semibold text-gray-900">
                                                {formatCurrency(category.total_spent || 0)}
                                            </div>
                                            <div className="text-gray-500">Spent</div>
                                        </div>
                                        <div className="text-center">
                                            <div className={`font-semibold ${(category.allocated_amount || 0) - (category.total_spent || 0) < 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                {formatCurrency((category.allocated_amount || 0) - (category.total_spent || 0))}
                                            </div>
                                            <div className="text-gray-500">Remaining</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}