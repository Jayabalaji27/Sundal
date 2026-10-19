import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Clock } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { PageTemplate } from '@/components/page-template';
import { SimplePagination } from '@/components/SimplePagination';
import { ApprovalsHeader, type ApprovalCounts, type ApprovalStatus } from '@/components/approvals/approvals-header';
import { RejectDialog } from '@/components/approvals/reject-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import ViewPopup from './ApprovalsView';

interface TimesheetApproval {
    id: number;
    status: string;
    comments?: string;
    timesheet: {
        id: number;
        user_id: number;
        start_date: string;
        end_date: string;
        total_hours: number;
        billable_hours: number;
        user: { name: string; avatar: string };
    };
}

interface Props {
    approvals: { data: TimesheetApproval[]; current_page?: number; last_page?: number };
    filters: { status?: string; search?: string };
    userWorkspaceRole?: string;
    approvalCounts: ApprovalCounts;
}

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
    approved: 'bg-green-50 text-green-700 ring-green-600/20',
    rejected: 'bg-red-50 text-red-700 ring-red-600/20',
};

export default function TimesheetApprovals({ approvals, filters, userWorkspaceRole, approvalCounts }: Props) {
    const { t } = useTranslation();
    const { flash, auth } = usePage().props as any;
    const status: ApprovalStatus = filters.status === 'history' ? 'history' : 'pending';
    const [selected, setSelected] = useState<number[]>([]);
    const [rejecting, setRejecting] = useState<number[] | null>(null);
    const [viewing, setViewing] = useState<TimesheetApproval | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    // Only the workspace owner reviews their own timesheets (the server enforces this too).
    const isOwn = (approval: TimesheetApproval) => userWorkspaceRole !== 'owner' && approval.timesheet?.user_id === auth?.user?.id;
    const canReview = (approval: TimesheetApproval) => approval.status === 'pending' && !isOwn(approval);
    const reviewable = approvals.data.filter(canReview);

    const navigate = (params: { status?: ApprovalStatus; search?: string; page?: number }) => {
        const next = { status, search: filters.search || '', page: 1, ...params };
        router.get(route('timesheet-approvals.index'), {
            ...(next.status === 'history' ? { status: 'history' } : {}),
            ...(next.search ? { search: next.search } : {}),
            ...(next.page > 1 ? { page: next.page } : {}),
        }, { preserveScroll: true });
    };

    const approve = (ids: number[]) => {
        const [url, data] = ids.length === 1
            ? [route('timesheet-approvals.approve', ids[0]), {}]
            : [route('timesheet-approvals.bulk-approve'), { approval_ids: ids }];
        router.post(url, data, { preserveScroll: true, onSuccess: () => setSelected([]) });
    };

    const reject = (ids: number[], reason: string) => {
        const [url, data] = ids.length === 1
            ? [route('timesheet-approvals.reject', ids[0]), { comments: reason }]
            : [route('timesheet-approvals.bulk-reject'), { approval_ids: ids, comments: reason }];
        router.post(url, data, { preserveScroll: true, onSuccess: () => setSelected([]) });
        setRejecting(null);
    };

    const toggle = (id: number) => setSelected(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    const allSelected = reviewable.length > 0 && selected.length === reviewable.length;
    const formatDate = (value: string) => window.appSettings.formatDateTime(new Date(value), false);

    return (
        <PageTemplate
            title={t('Approvals')}
            breadcrumbs={[{ title: t('Dashboard'), href: route('dashboard') }, { title: t('Approvals') }, { title: t('Timesheets') }]}
            noPadding
        >
            <div className="space-y-4">
                <ApprovalsHeader active="timesheets" counts={approvalCounts} status={status} search={filters.search || ''} onChange={navigate} />

                {selected.length > 0 && (
                    <div className="flex items-center gap-3 rounded-lg border bg-muted/40 px-4 py-2 text-sm">
                        <span className="font-medium">{t('{{count}} selected', { count: selected.length })}</span>
                        <Button size="sm" onClick={() => approve(selected)}>{t('Approve')}</Button>
                        <Button size="sm" variant="outline" onClick={() => setRejecting(selected)}>{t('Reject')}</Button>
                    </div>
                )}

                {approvals.data.length === 0 ? (
                    <div className="rounded-lg border bg-white py-16 text-center dark:bg-gray-900">
                        <Clock className="mx-auto mb-3 h-10 w-10 text-gray-300" />
                        <p className="font-medium">{status === 'pending' ? t('Nothing waiting for your approval') : t('No decided timesheets yet')}</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border bg-white dark:bg-gray-900">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {status === 'pending' && (
                                        <TableHead className="w-10">
                                            <Checkbox
                                                checked={allSelected}
                                                disabled={reviewable.length === 0}
                                                onCheckedChange={() => setSelected(allSelected ? [] : reviewable.map(a => a.id))}
                                                aria-label={t('Select all')}
                                            />
                                        </TableHead>
                                    )}
                                    <TableHead>{t('Employee')}</TableHead>
                                    <TableHead>{t('Week')}</TableHead>
                                    <TableHead className="text-right">{t('Total')}</TableHead>
                                    <TableHead className="text-right">{t('Billable')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                    <TableHead className="text-right">{t('Actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {approvals.data.map(approval => (
                                    <TableRow key={approval.id}>
                                        {status === 'pending' && (
                                            <TableCell>
                                                {canReview(approval) && (
                                                    <Checkbox checked={selected.includes(approval.id)} onCheckedChange={() => toggle(approval.id)} />
                                                )}
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Avatar className="h-7 w-7">
                                                    <AvatarImage src={approval.timesheet.user?.avatar} />
                                                    <AvatarFallback className="text-xs">{approval.timesheet.user?.name?.charAt(0)}</AvatarFallback>
                                                </Avatar>
                                                <span className="font-medium">{approval.timesheet.user?.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">
                                            {formatDate(approval.timesheet.start_date)} – {formatDate(approval.timesheet.end_date)}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">{approval.timesheet.total_hours}h</TableCell>
                                        <TableCell className="text-right">{approval.timesheet.billable_hours}h</TableCell>
                                        <TableCell>
                                            <span className={`inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${STATUS_STYLES[approval.status] || ''}`}>
                                                {t(approval.status.charAt(0).toUpperCase() + approval.status.slice(1))}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <Button size="sm" variant="ghost" onClick={() => setViewing(approval)}>{t('View')}</Button>
                                                {canReview(approval) && (
                                                    <>
                                                        <Button size="sm" variant="outline" onClick={() => setRejecting([approval.id])}>{t('Reject')}</Button>
                                                        <Button size="sm" onClick={() => approve([approval.id])}>{t('Approve')}</Button>
                                                    </>
                                                )}
                                                {approval.status === 'pending' && isOwn(approval) && (
                                                    <span className="self-center text-xs text-muted-foreground">{t('The owner reviews this')}</span>
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
                    currentPage={approvals.current_page || 1}
                    totalPages={approvals.last_page || 1}
                    onPageChange={page => navigate({ page })}
                />
            </div>

            <RejectDialog
                open={rejecting !== null}
                title={rejecting && rejecting.length > 1 ? t('Reject {{count}} timesheets', { count: rejecting.length }) : t('Reject timesheet')}
                onCancel={() => setRejecting(null)}
                onConfirm={reason => rejecting && reject(rejecting, reason)}
            />

            <Dialog open={viewing !== null} onOpenChange={open => !open && setViewing(null)}>
                {viewing && <ViewPopup record={viewing} />}
            </Dialog>
        </PageTemplate>
    );
}
