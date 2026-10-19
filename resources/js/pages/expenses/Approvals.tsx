import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Clock } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { PageTemplate } from '@/components/page-template';
import { SimplePagination } from '@/components/SimplePagination';
import { ApprovalsHeader, type ApprovalCounts, type ApprovalStatus } from '@/components/approvals/approvals-header';
import { RejectDialog } from '@/components/approvals/reject-dialog';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/utils/currency';

interface Props {
    expenses: { data: any[]; current_page?: number; last_page?: number };
    stats: { pending_amount?: number };
    filters: { status?: string; search?: string };
    permissions?: { approve?: boolean; reject?: boolean; request_info?: boolean; review_own?: boolean };
    approvalCounts: ApprovalCounts;
}

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
    requires_info: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    approved: 'bg-green-50 text-green-700 ring-green-600/20',
    rejected: 'bg-red-50 text-red-700 ring-red-600/20',
};

const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    requires_info: 'Waiting for info',
    approved: 'Approved',
    rejected: 'Rejected',
};

export default function ExpenseApprovals({ expenses, stats, filters, permissions, approvalCounts }: Props) {
    const { t } = useTranslation();
    const { flash, auth } = usePage().props as any;
    const status: ApprovalStatus = filters.status === 'history' ? 'history' : 'pending';
    const [rejecting, setRejecting] = useState<number | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // Only the workspace owner reviews their own expenses (the server enforces this too).
    const isOwn = (expense: any) => !permissions?.review_own && expense.submitted_by === auth?.user?.id;
    const isWaiting = (expense: any) => ['pending', 'requires_info'].includes(expense.status);
    const canReview = (expense: any) => isWaiting(expense) && !isOwn(expense);

    const navigate = (params: { status?: ApprovalStatus; search?: string; page?: number }) => {
        const next = { status, search: filters.search || '', page: 1, ...params };
        router.get(route('expense-approvals.index'), {
            ...(next.status === 'history' ? { status: 'history' } : {}),
            ...(next.search ? { search: next.search } : {}),
            ...(next.page > 1 ? { page: next.page } : {}),
        }, { preserveScroll: true });
    };

    const decide = (id: number, action: 'approve' | 'reject' | 'request-info', notes = '') => {
        router.post(route(`expense-approvals.${action}`, id), { notes }, { preserveScroll: true });
    };

    return (
        <PageTemplate
            title={t('Approvals')}
            breadcrumbs={[{ title: t('Dashboard'), href: route('dashboard') }, { title: t('Approvals') }, { title: t('Expenses') }]}
            noPadding
        >
            <div className="space-y-4">
                <ApprovalsHeader active="expenses" counts={approvalCounts} status={status} search={filters.search || ''} onChange={navigate} />

                {status === 'pending' && !!stats?.pending_amount && (
                    <p className="text-sm text-muted-foreground">
                        {t('Waiting for a decision')}: <span className="font-semibold text-foreground">{formatCurrency(stats.pending_amount)}</span>
                    </p>
                )}

                {expenses.data.length === 0 ? (
                    <div className="rounded-lg border bg-white py-16 text-center dark:bg-gray-900">
                        <Clock className="mx-auto mb-3 h-10 w-10 text-gray-300" />
                        <p className="font-medium">{status === 'pending' ? t('Nothing waiting for your approval') : t('No decided expenses yet')}</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border bg-white dark:bg-gray-900">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Expense')}</TableHead>
                                    <TableHead>{t('Project')}</TableHead>
                                    <TableHead>{t('Date')}</TableHead>
                                    <TableHead className="text-right">{t('Amount')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                    <TableHead className="text-right">{t('Actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {expenses.data.map(expense => (
                                    <TableRow key={expense.id}>
                                        <TableCell>
                                            <button
                                                type="button"
                                                className="text-left font-medium hover:text-primary hover:underline"
                                                onClick={() => router.get(route('expenses.show', expense.id))}
                                            >
                                                {expense.title}
                                            </button>
                                            <div className="text-xs text-muted-foreground">{expense.submitter?.name}</div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {expense.project?.title}
                                            {expense.budget_category && <div className="text-xs text-muted-foreground">{expense.budget_category.name}</div>}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            {window.appSettings.formatDateTime(new Date(expense.expense_date), false)}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-right font-medium">{formatCurrency(expense.amount)}</TableCell>
                                        <TableCell>
                                            <span className={`inline-flex whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${STATUS_STYLES[expense.status] || ''}`}>
                                                {t(STATUS_LABELS[expense.status] || expense.status)}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {canReview(expense) && (
                                                    <>
                                                        {permissions?.request_info && expense.status === 'pending' && (
                                                            <Button size="sm" variant="ghost" onClick={() => decide(expense.id, 'request-info', t('Additional information required'))}>
                                                                {t('Ask for info')}
                                                            </Button>
                                                        )}
                                                        {permissions?.reject && (
                                                            <Button size="sm" variant="outline" onClick={() => setRejecting(expense.id)}>{t('Reject')}</Button>
                                                        )}
                                                        {permissions?.approve && (
                                                            <Button size="sm" onClick={() => decide(expense.id, 'approve')}>{t('Approve')}</Button>
                                                        )}
                                                    </>
                                                )}
                                                {isWaiting(expense) && isOwn(expense) && (
                                                    <span className="text-xs text-muted-foreground">{t('The owner reviews this')}</span>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <SimplePagination
                    currentPage={expenses.current_page || 1}
                    totalPages={expenses.last_page || 1}
                    onPageChange={page => navigate({ page })}
                />
            </div>

            <RejectDialog
                open={rejecting !== null}
                title={t('Reject expense')}
                onCancel={() => setRejecting(null)}
                onConfirm={reason => {
                    if (rejecting !== null) decide(rejecting, 'reject', reason);
                    setRejecting(null);
                }}
            />
        </PageTemplate>
    );
}
