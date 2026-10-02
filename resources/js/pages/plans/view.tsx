import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { ShoppingCart, Calendar, FileText, CreditCard, User, Tag, Download, Percent, BadgeCheck } from 'lucide-react';

interface ViewProps {
    record: any;
}

export default function View({ record }: ViewProps) {
    const { t } = useTranslation();

    const fmt = (v: any) =>
        window.appSettings?.formatCurrency
            ? window.appSettings.formatCurrency(parseFloat(v))
            : parseFloat(v || 0).toFixed(2);

    const statusColors: Record<string, string> = {
        pending:  'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
        approved: 'bg-green-50 text-green-700 ring-green-600/20',
        rejected: 'bg-red-50 text-red-700 ring-red-600/20',
    };

    return (
        <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader className="px-6 pt-6 pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <ShoppingCart className="h-5 w-5 text-primary" />
                    </div>
                    <DialogTitle className="text-xl font-semibold">{t('Plan Order Details')}</DialogTitle>
                </div>
            </DialogHeader>

            <div className="px-6 py-4 pb-6 space-y-4">

                {/* Row 1: Order Number & Order Date */}
                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <ShoppingCart className="h-4 w-4" />
                            {t('Order Number')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{record.order_number || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            {t('Order Date')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                            {record.ordered_at
                                ? (window.appSettings?.formatDateTime(record.ordered_at, false) || record.ordered_at)
                                : '-'}
                        </p>
                    </div>
                </div>

                {/* Row 2: Status & Payment Method */}
                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            {t('Status')}
                        </label>
                        <div className="mt-1">
                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset capitalize ${statusColors[record.status] || 'bg-gray-50 text-gray-700 ring-gray-600/20'}`}>
                                {t(record.status)}
                            </span>
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <CreditCard className="h-4 w-4" />
                            {t('Payment Method')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white capitalize">{record.payment_method || '-'}</p>
                    </div>
                </div>

                {/* Row 3: User & Plan */}
                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <User className="h-4 w-4" />
                            {t('User')}
                            {record.user?.id && (
                                <span className="text-xs font-normal text-gray-400">#{record.user.id}</span>
                            )}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{record.user?.name || '-'}</p>
                        <p className="text-xs text-gray-500 mt-0.5">{record.user?.email || ''}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Tag className="h-4 w-4" />
                            {t('Plan')}
                        </label>
                        <div className="mt-1">
                            <span className="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-900/30 px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-300 ring-1 ring-inset ring-blue-700/10">
                                {record.plan?.name || '-'}
                            </span>
                        </div>
                        <p className="text-xs text-gray-500 mt-1 capitalize">{record.billing_cycle || ''}</p>
                    </div>
                </div>

                {/* Row 4: Original Price & Discount */}
                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <CreditCard className="h-4 w-4" />
                            {t('Original Price')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{fmt(record.original_price)}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Tag className="h-4 w-4" />
                            {t('Discount')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                            {record.discount_amount > 0 ? `-${fmt(record.discount_amount)}` : '-'}
                        </p>
                    </div>
                </div>

                {/* Row 5: Final Price & Coupon Code (or Receipt if no coupon) */}
                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <CreditCard className="h-4 w-4" />
                            {t('Final Price')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{fmt(record.final_price)}</p>
                    </div>
                    {record.coupon_code ? (
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Tag className="h-4 w-4" />
                                {t('Coupon Code')}
                            </label>
                            <p className="mt-1 text-sm font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded inline-block">{record.coupon_code}</p>
                        </div>
                    ) : record.receipt_url ? (
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Download className="h-4 w-4" />
                                {t('Payment Receipt')}
                            </label>
                            <div className="mt-2">
                                <a href={record.receipt_url} target="_blank" rel="noopener noreferrer" download>
                                    <Button variant="default" size="sm">
                                        {t('Download Receipt')}
                                    </Button>
                                </a>
                            </div>
                        </div>
                    ) : <div />}
                </div>

                {/* Row 6: Receipt (only if coupon code also exists) */}
                {record.coupon_code && record.receipt_url && (
                    <div className="grid grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Download className="h-4 w-4" />
                                {t('Payment Receipt')}
                            </label>
                            <div className="mt-2">
                                <a href={record.receipt_url} target="_blank" rel="noopener noreferrer" download>
                                    <Button variant="default" size="sm">
                                        {t('Download Receipt')}
                                    </Button>
                                </a>
                            </div>
                        </div>
                        <div />
                    </div>
                )}

            </div>
        </DialogContent>
    );
}
