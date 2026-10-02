import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    Calendar,
    Building2,
    CheckSquare,
    Receipt,
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle,
    Tag,
    FileText,
    User,
    DollarSign,
    Store,
} from 'lucide-react';
import { formatCurrency } from '@/utils/currency';
import { useTranslation } from 'react-i18next';

interface Expense {
    id: number;
    project_id?: number;
    budget_category_id?: number;
    task_id?: number;
    amount: number;
    currency: string;
    expense_date: string;
    title: string;
    description?: string;
    vendor?: string;
    status: 'pending' | 'approved' | 'rejected' | 'requires_info';
    created_at?: string;
    project: {
        id: number;
        title: string;
    };
    budget_category?: {
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
        avatar?: string;
    };
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    expense: Expense | null;
}

const STATUS_CONFIG = {
    pending: {
        label: 'Pending',
        icon: Clock,
        badgeCls: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
    },
    approved: {
        label: 'Approved',
        icon: CheckCircle2,
        badgeCls: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
    },
    rejected: {
        label: 'Rejected',
        icon: XCircle,
        badgeCls: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
    },
    requires_info: {
        label: 'Requires Info',
        icon: AlertCircle,
        badgeCls: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
    },
};

export default function ExpenseViewModal({ isOpen, onClose, expense }: Props) {
    const { t } = useTranslation();

    if (!expense) return null;

    const status = STATUS_CONFIG[expense.status] ?? STATUS_CONFIG.pending;

    const formattedDate = window.appSettings.formatDateTime(new Date(expense.expense_date),false);

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
                <DialogHeader className="px-6 pt-6 pb-4 border-b">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg">
                            <Receipt className="h-5 w-5 text-primary" />
                        </div>
                        <DialogTitle className="text-xl font-semibold">{t('Expense Details')}</DialogTitle>
                    </div>
                </DialogHeader>

                <div className="px-6 py-4 pb-6 space-y-4">
                    {/* Row 1: Title + Amount */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Receipt className="h-4 w-4" />
                                {t('Title')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{expense.title || '-'}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <DollarSign className="h-4 w-4" />
                                {t('Total Amount')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{formatCurrency(expense.amount)}</p>
                        </div>
                    </div>

                    {/* Row 2: Date + Submitted By */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Calendar className="h-4 w-4" />
                                {t('Expense Date')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{formattedDate}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <User className="h-4 w-4" />
                                {t('Submitted by')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{expense.submitter?.name || '-'}</p>
                        </div>
                    </div>

                    {/* Row 3: Project + Status */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Building2 className="h-4 w-4" />
                                {t('Project')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{expense.project?.title || '-'}</p>
                        </div>
                        
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <AlertCircle className="h-4 w-4" />
                                {t('Status')}
                            </label>
                            <div className="mt-1">
                                <span className={`inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium ${status.badgeCls}`}>
                                    {t(status.label)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Row 4: Vendor + Budget Category/Task */}
                    {(expense.vendor || expense.budget_category || expense.task) && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {expense.vendor && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Store className="h-4 w-4" />
                                        {t('Vendor')}
                                    </label>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{expense.vendor}</p>
                                </div>
                            )}
                            {expense.budget_category && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                        <Tag className="h-4 w-4" />
                                        {t('Budget Category')}
                                    </label>
                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{expense.budget_category.name}</p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Related Task */}
                    {expense.task && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                    <CheckSquare className="h-4 w-4" />
                                    {t('Related Task')}
                                </label>
                                <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{expense.task.title}</p>
                            </div>
                        </div>
                    )}

                    {/* Description */}
                    {expense.description && (
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <FileText className="h-4 w-4" />
                                {t('Description')}
                            </label>
                            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{expense.description}</p>
                        </div>
                    )}

                    
                </div>
            </DialogContent>
        </Dialog>
    );
}
