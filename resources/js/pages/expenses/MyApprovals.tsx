import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { CheckCircle, XCircle, AlertCircle, Clock, Receipt } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function MyApprovals() {
    const { t } = useTranslation();
    const [approvals, setApprovals] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [processingIds, setProcessingIds] = useState<number[]>([]);
    const [selectedApprovals, setSelectedApprovals] = useState<number[]>([]);
    const [bulkNotes, setBulkNotes] = useState('');

    useEffect(() => {
        loadApprovals();
    }, []);

    const loadApprovals = async () => {
        try {
            const response = await fetch(route('api.expense-workflows.my-approvals'));
            const data = await response.json();
            setApprovals(data.approvals.data || []);
        } catch (error) {
            console.error('Failed to load approvals:', error);
        } finally {
            setLoading(false);
        }
    };

    const processApproval = async (workflowId: number, action: string, notes?: string) => {
        setProcessingIds(prev => [...prev, workflowId]);
        
        try {
            await fetch(route('expense-workflows.process', workflowId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ action, notes })
            });
            
            loadApprovals(); // Reload data
        } catch (error) {
            console.error('Failed to process approval:', error);
        } finally {
            setProcessingIds(prev => prev.filter(id => id !== workflowId));
        }
    };

    const processBulkApprovals = async (action: string) => {
        if (selectedApprovals.length === 0) return;
        
        try {
            await fetch(route('expense-workflows.bulk-process'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ 
                    workflow_ids: selectedApprovals, 
                    action, 
                    notes: bulkNotes 
                })
            });
            
            setSelectedApprovals([]);
            setBulkNotes('');
            loadApprovals();
        } catch (error) {
            console.error('Failed to process bulk approvals:', error);
        }
    };

    const formatCurrency = (amount: number, currency: string) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    };

    const toggleSelection = (workflowId: number) => {
        setSelectedApprovals(prev => 
            prev.includes(workflowId) 
                ? prev.filter(id => id !== workflowId)
                : [...prev, workflowId]
        );
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('My Approvals') }
    ];

    if (loading) {
        return (
            <PageTemplate title={t('My Approvals')} breadcrumbs={breadcrumbs}>
                <div className="flex items-center justify-center h-64">
                    <div className="text-gray-500">{t('Loading approvals...')}</div>
                </div>
            </PageTemplate>
        );
    }

    return (
        <PageTemplate title={t('My Approvals')} breadcrumbs={breadcrumbs} noPadding>
            <div className="space-y-6">
                {/* Bulk Actions */}
                {selectedApprovals.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Bulk Actions')} ({selectedApprovals.length} {t('selected')})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <Textarea
                                    placeholder={t('Add notes for bulk action (optional)...')}
                                    value={bulkNotes}
                                    onChange={(e) => setBulkNotes(e.target.value)}
                                    rows={3}
                                />
                                <div className="flex gap-2">
                                    <Button 
                                        onClick={() => processBulkApprovals('approve')}
                                        className="bg-green-600 hover:bg-green-700"
                                    >
                                        <CheckCircle className="h-4 w-4 mr-2" />
                                        {t('Approve Selected')}
                                    </Button>
                                    <Button 
                                        onClick={() => processBulkApprovals('reject')}
                                        variant="destructive"
                                    >
                                        <XCircle className="h-4 w-4 mr-2" />
                                        {t('Reject Selected')}
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Approvals List */}
                {approvals.length > 0 ? (
                    <div className="space-y-4">
                        {approvals.map((workflow: any) => (
                            <Card key={workflow.id} className="overflow-hidden">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                checked={selectedApprovals.includes(workflow.id)}
                                                onChange={() => toggleSelection(workflow.id)}
                                                className="rounded"
                                            />
                                            <div>
                                                <CardTitle className="text-lg">
                                                    {workflow.project_expense.title}
                                                </CardTitle>
                                                <div className="text-sm text-gray-600">
                                                    {workflow.project_expense.project.title} • 
                                                    {t('Submitted by')} {workflow.project_expense.submitter.name}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-2xl font-bold text-green-600">
                                                {formatCurrency(workflow.project_expense.amount)}
                                            </div>
                                            <Badge variant="secondary" className="bg-yellow-100 text-yellow-800">
                                                {t('Step')} {workflow.step}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>
                                
                                <CardContent>
                                    <div className="space-y-4">
                                        {workflow.project_expense.description && (
                                            <div>
                                                <h4 className="font-medium mb-2">{t('Description')}</h4>
                                                <p className="text-gray-600">{workflow.project_expense.description}</p>
                                            </div>
                                        )}
                                        
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span className="font-medium">{t('Expense Date')}:</span>
                                                <div>{new Date(workflow.project_expense.expense_date).toLocaleDateString()}</div>
                                            </div>
                                            <div>
                                                <span className="font-medium">{t('Vendor')}:</span>
                                                <div>{workflow.project_expense.vendor || t('Not specified')}</div>
                                            </div>
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="flex gap-2 pt-4 border-t">
                                            <Button
                                                onClick={() => processApproval(workflow.id, 'approve')}
                                                disabled={processingIds.includes(workflow.id)}
                                                className="bg-green-600 hover:bg-green-700"
                                            >
                                                <CheckCircle className="h-4 w-4 mr-2" />
                                                {t('Approve')}
                                            </Button>
                                            <Button
                                                onClick={() => processApproval(workflow.id, 'reject')}
                                                disabled={processingIds.includes(workflow.id)}
                                                variant="destructive"
                                            >
                                                <XCircle className="h-4 w-4 mr-2" />
                                                {t('Reject')}
                                            </Button>
                                            <Button
                                                onClick={() => processApproval(workflow.id, 'request_info')}
                                                disabled={processingIds.includes(workflow.id)}
                                                variant="outline"
                                            >
                                                <AlertCircle className="h-4 w-4 mr-2" />
                                                {t('Request Info')}
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <div className="bg-white rounded-lg shadow p-8 text-center">
                        <Clock className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                        <p className="text-gray-500 mb-4">{t('No pending approvals')}</p>
                        <p className="text-sm text-gray-400">{t('All expenses have been processed')}</p>
                    </div>
                )}
            </div>
        </PageTemplate>
    );
}