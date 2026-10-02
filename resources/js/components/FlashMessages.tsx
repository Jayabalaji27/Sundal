import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { CheckCircle, AlertTriangle, XCircle, Copy } from 'lucide-react';

interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    invitation_link?: string;
}

export default function FlashMessages() {
    const { flash } = usePage().props as { flash: FlashMessages };

    useEffect(() => {
        // Success message
        if (flash.success) {
            toast(
                <div className="flex items-start">
                    <div className="p-1 rounded-full bg-green-50 dark:bg-green-900/20 mr-3">
                        <CheckCircle className="h-5 w-5 text-green-500 dark:text-green-400" />
                    </div>
                    <div>
                        <h3 className="font-medium text-gray-900 dark:text-gray-100">{flash.success}</h3>
                    </div>
                </div>,
                { duration: 4000 }
            );
        }

        // Error message
        if (flash.error) {
            toast(
                <div className="flex items-start">
                    <div className="p-1 rounded-full bg-red-50 dark:bg-red-900/20 mr-3">
                        <XCircle className="h-5 w-5 text-red-500 dark:text-red-400" />
                    </div>
                    <div>
                        <h3 className="font-medium text-gray-900 dark:text-gray-100">{flash.error}</h3>
                    </div>
                </div>,
                { duration: 6000 }
            );
        }

        // Warning message with optional invitation link
        if (flash.warning) {
            const handleCopyLink = () => {
                if (flash.invitation_link) {
                    navigator.clipboard.writeText(flash.invitation_link);
                    toast(
                        <div className="flex items-start">
                            <div className="p-1 rounded-full bg-blue-50 dark:bg-blue-900/20 mr-3">
                                <CheckCircle className="h-5 w-5 text-blue-500 dark:text-blue-400" />
                            </div>
                            <div>
                                <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                    Invitation link copied to clipboard!
                                </h3>
                            </div>
                        </div>,
                        { duration: 3000 }
                    );
                }
            };

            toast(
                <div className="flex items-start">
                    <div className="p-1 rounded-full bg-amber-50 dark:bg-amber-900/20 mr-3">
                        <AlertTriangle className="h-5 w-5 text-amber-500 dark:text-amber-400" />
                    </div>
                    <div className="flex-1">
                        <h3 className="font-medium text-gray-900 dark:text-gray-100 mb-1">
                            Email Configuration Issue
                        </h3>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            {flash.warning}
                        </p>
                        {flash.invitation_link && (
                            <button
                                onClick={handleCopyLink}
                                className="inline-flex cursor-pointer items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50 rounded-md transition-colors"
                            >
                                <Copy className="h-3.5 w-3.5" />
                                Copy Invitation Link
                            </button>
                        )}
                    </div>
                </div>,
                { duration: 10000 }
            );
        }
    }, [flash]);

    return null;
}
