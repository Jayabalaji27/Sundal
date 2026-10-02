import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { FileText, Lock, Hash, User, Palette } from 'lucide-react';

interface ViewContractTypeProps {
    contractType: any;
}

export default function View({ contractType }: ViewContractTypeProps) {
    const { t } = useTranslation();
    return (
        <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader className="px-6 pt-6 pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <FileText className="h-5 w-5 text-primary" />
                    </div>
                    <DialogTitle className="text-xl font-semibold">{t('Contract Type Details')}</DialogTitle>
                </div>
            </DialogHeader>
            <div className="px-6 py-4 pb-6 space-y-4">
                {/* Name & Status */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            {t('Name')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{contractType.name || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Lock className="h-4 w-4" />
                            {t('Status')}
                        </label>
                        <div className="mt-1">
                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ${contractType.is_active ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-red-50 text-red-700 ring-red-600/20'}`}>
                                {t(contractType.is_active ? 'Active' : 'Inactive')}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Created By & Contracts Count */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <User className="h-4 w-4" />
                            {t('Created By')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{contractType.creator?.name || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Hash className="h-4 w-4" />
                            {t('Contracts')}
                        </label>
                        <div className="mt-1">
                            <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                {contractType.contracts_count || 0} {t('contracts')}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Color */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Palette className="h-4 w-4" />
                            {t('Color')}
                        </label>
                        <div className="mt-1 flex items-center gap-2">
                            <span className="w-5 h-5 rounded border border-gray-200 inline-block" style={{ backgroundColor: contractType.color || '#007bff' }} />
                            <span className="text-sm font-medium text-gray-900 dark:text-white">{contractType.color || '-'}</span>
                        </div>
                    </div>
                </div>

                {/* Description */}
                {contractType.description && (
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            {t('Description')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white whitespace-pre-wrap">{contractType.description}</p>
                    </div>
                )}
            </div>
        </DialogContent>
    );
}
