import { DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { Building2, Mail, Lock, Calendar, CreditCard, LogIn } from 'lucide-react';

interface ViewProps {
    record: any;
    isSaasMode?: boolean;
    onImpersonate?: () => void;
}

export default function View({ record, isSaasMode = true, onImpersonate }: ViewProps) {
    const { t } = useTranslation();

    return (
        <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader className="px-6 pt-6 pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <Building2 className="h-5 w-5 text-primary" />
                    </div>
                    <DialogTitle className="text-xl font-semibold">{t('Company Details')}</DialogTitle>
                </div>
            </DialogHeader>

            <div className="px-6 py-4 pb-6 space-y-4">
                {/* Name & Email */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Building2 className="h-4 w-4" />
                            {t('Company Name')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{record.name || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Mail className="h-4 w-4" />
                            {t('Email')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{record.email || '-'}</p>
                    </div>
                </div>

                {/* Status & Created At */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Lock className="h-4 w-4" />
                            {t('Status')}
                        </label>
                        <div className="mt-1">
                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${
                                record.status === 'active'
                                    ? 'bg-green-50 text-green-700 ring-green-600/20'
                                    : 'bg-red-50 text-red-700 ring-red-600/20'
                            }`}>
                                {record.status === 'active' ? t('Active') : t('Inactive')}
                            </span>
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            {t('Created At')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                            {record.created_at ? (window.appSettings?.formatDateTime(record.created_at, false) || record.created_at) : '-'}
                        </p>
                    </div>
                </div>

                {/* Plan Info */}
                {isSaasMode && record.plan_name && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <CreditCard className="h-4 w-4" />
                                {t('Plan')}
                            </label>
                            <div className="mt-1">
                                <span className="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-900/30 px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-300 ring-1 ring-inset ring-blue-700/10">
                                    {record.plan_name || t('No Plan')}
                                </span>
                            </div>
                        </div>
                        {record.plan_expiry_date && (
                            <div>
                                <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                    <Calendar className="h-4 w-4" />
                                    {t('Plan Expires')}
                                </label>
                                <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                    {window.appSettings?.formatDateTime(record.plan_expiry_date, false) || record.plan_expiry_date}
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {onImpersonate && (
                <DialogFooter className="px-6 pb-6">
                    <Button onClick={onImpersonate} className="gap-2">
                        <LogIn className="h-4 w-4" />
                        {t('Impersonate Company')}
                    </Button>
                </DialogFooter>
            )}
        </DialogContent>
    );
}
