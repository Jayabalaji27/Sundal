import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { History, DollarSign, TrendingUp, TrendingDown } from 'lucide-react';

interface RateHistory {
    id: number;
    rate: number;
    previous_rate?: number;
    effective_date: string;
    project?: { title: string };
    is_default: boolean;
    created_at: string;
}

interface Props {
    userId: number;
    userName: string;
    history: RateHistory[];
    isOpen: boolean;
    onClose: () => void;
}

export default function RateHistoryView({ userId, userName, history, isOpen, onClose }: Props) {
    const getRateChange = (current: number, previous?: number) => {
        if (!previous) return null;
        const change = current - previous;
        const percentage = ((change / previous) * 100).toFixed(1);
        return { change, percentage };
    };

    const formatCurrency = (amount: number) => `${(() => { if (typeof window !== "undefined" && window.appSettings?.formatCurrency) { return window.appSettings.formatCurrency(amount, { showSymbol: true }); } return amount.toFixed(2); })()}`;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <History className="h-5 w-5" />
                        Rate History - {userName}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 max-h-96 overflow-y-auto">
                    {history.map((record, index) => {
                        const rateChange = getRateChange(record.rate, record.previous_rate);
                        
                        return (
                            <Card key={record.id}>
                                <CardContent className="p-4">
                                    <div className="flex justify-between items-start">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 mb-2">
                                                <DollarSign className="h-4 w-4 text-green-600" />
                                                <span className="text-lg font-bold text-green-600">
                                                    {formatCurrency(record.rate)}/hr
                                                </span>
                                                
                                                {rateChange && (
                                                    <div className="flex items-center gap-1">
                                                        {rateChange.change > 0 ? (
                                                            <TrendingUp className="h-4 w-4 text-green-500" />
                                                        ) : (
                                                            <TrendingDown className="h-4 w-4 text-red-500" />
                                                        )}
                                                        <span className={`text-sm ${rateChange.change > 0 ? 'text-green-500' : 'text-red-500'}`}>
                                                            {rateChange.change > 0 ? '+' : ''}{formatCurrency(rateChange.change)} ({rateChange.percentage}%)
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                            
                                            <div className="space-y-1 text-sm">
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Project:</span>
                                                    <span>{record.project?.title || 'Default Rate'}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Effective Date:</span>
                                                    <span>{new Date(record.effective_date).toLocaleDateString()}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">Created:</span>
                                                    <span>{new Date(record.created_at).toLocaleDateString()}</span>
                                                </div>
                                            </div>
                                            
                                            <div className="flex gap-1 mt-2">
                                                {record.is_default && (
                                                    <Badge variant="secondary">Default</Badge>
                                                )}
                                                {record.project && (
                                                    <Badge variant="outline">Project Specific</Badge>
                                                )}
                                                {index === 0 && (
                                                    <Badge variant="default">Current</Badge>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                    
                    {history.length === 0 && (
                        <div className="text-center py-8 text-muted-foreground">
                            <History className="h-12 w-12 mx-auto mb-4 opacity-50" />
                            <p>No rate history available</p>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}