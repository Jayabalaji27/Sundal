import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from 'react-i18next';
import { User, Calendar, Timer, DollarSign, MessageSquare, CheckCircle } from 'lucide-react';

interface ViewProps {
    record: any;
}

export default function View({ record }: ViewProps) {
    const { t } = useTranslation();

    const getStatusColor = (status: string) => {
        const colors: Record<string, string> = {
            pending: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            approved: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            rejected: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
        };
        return colors[status] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    return (
        <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader className="px-6 pt-6 pb-4 border-b">
                <div className="flex items-center justify-between mr-6">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg">
                            <CheckCircle className="h-5 w-5 text-primary" />
                        </div>
                        <DialogTitle className="text-xl font-semibold">{t('Approval Details')}</DialogTitle>
                    </div>
                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(record.status)}`}>
                        {record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                    </span>
                </div>
            </DialogHeader>

            <div className="px-6 py-4 pb-6 space-y-4">
                {/* Employee & Period */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <User className="h-4 w-4" />
                            {t('Employee')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{record.timesheet?.user?.name || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            {t('Period')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                            {record.timesheet?.start_date ? new Date(record.timesheet.start_date).toLocaleDateString() : '-'}
                            {' - '}
                            {record.timesheet?.end_date ? new Date(record.timesheet.end_date).toLocaleDateString() : '-'}
                        </p>
                    </div>
                </div>

                {/* Total Hours & Billable Hours */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Timer className="h-4 w-4" />
                            {t('Total Hours')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{record.timesheet?.total_hours ?? '-'}h</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <DollarSign className="h-4 w-4" />
                            {t('Billable Hours')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-green-600">{record.timesheet?.billable_hours ?? '-'}h</p>
                    </div>
                </div>

                {/* Timesheet Notes */}
                {record.timesheet?.notes && (
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <MessageSquare className="h-4 w-4" />
                            {t('Timesheet Notes')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white whitespace-pre-wrap">{record.timesheet.notes}</p>
                    </div>
                )}

                {/* Comments */}
                {record.comments && (
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <MessageSquare className="h-4 w-4" />
                            {t('Comments')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white whitespace-pre-wrap">{record.comments}</p>
                    </div>
                )}
            </div>
        </DialogContent>
    );
}
