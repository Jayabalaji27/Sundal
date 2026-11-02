import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { CheckCircle, AlertTriangle, XCircle, Copy } from 'lucide-react';

interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    invitation_link?: string;
}

/**
 * Shows a flash's success/error once and clears them on the props object.
 *
 * Pages also toast `flash` from their own effects and router callbacks, and the
 * callbacks read it from a stale closure (the previous response), so they showed
 * the previous action's message. Every one of those reads the same props object,
 * so clearing it here leaves them nothing to show. Display waits a tick because
 * most onSuccess callbacks start with toast.dismiss(), which would remove it.
 */
function showFlashOnce(flash: FlashMessages | undefined) {
    if (!flash) return;
    const { success, error } = flash;
    flash.success = undefined;
    flash.error = undefined;

    setTimeout(() => {
        if (success) {
            toast(
                <div className="flex items-start">
                    <div className="mr-3 shrink-0 rounded-full bg-ds-success-bg p-1">
                        <CheckCircle className="h-5 w-5 text-ds-success" aria-hidden="true" />
                    </div>
                    <div>
                        <h3 className="font-medium text-ds-fg">{success}</h3>
                    </div>
                </div>,
                { duration: 4000 }
            );
        }

        if (error) {
            toast(
                <div className="flex items-start">
                    <div className="mr-3 shrink-0 rounded-full bg-ds-danger-bg p-1">
                        <XCircle className="h-5 w-5 text-ds-danger" aria-hidden="true" />
                    </div>
                    <div>
                        <h3 className="font-medium text-ds-fg">{error}</h3>
                    </div>
                </div>,
                { duration: 6000 }
            );
        }
    }, 0);
}

export default function FlashMessages() {
    const { flash } = usePage().props as { flash: FlashMessages };

    // Inertia fires 'success' before the visit's own onSuccess, so the flash is
    // cleared before any page callback can read it.
    useEffect(() => router.on('success', (event) => {
        showFlashOnce((event.detail.page.props as { flash?: FlashMessages }).flash);
    }), []);

    useEffect(() => {
        // Full page loads (no Inertia visit) and anything the listener missed.
        showFlashOnce(flash);

        // Warning message with optional invitation link
        if (flash.warning) {
            const handleCopyLink = () => {
                if (flash.invitation_link) {
                    navigator.clipboard.writeText(flash.invitation_link);
                    toast(
                        <div className="flex items-start">
                            <div className="mr-3 shrink-0 rounded-full bg-ds-info-bg p-1">
                                <CheckCircle className="h-5 w-5 text-ds-info" aria-hidden="true" />
                            </div>
                            <div>
                                <h3 className="font-medium text-ds-fg">
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
                    <div className="mr-3 shrink-0 rounded-full bg-ds-warning-bg p-1">
                        <AlertTriangle className="h-5 w-5 text-ds-warning" aria-hidden="true" />
                    </div>
                    <div className="flex-1">
                        <h3 className="mb-1 font-medium text-ds-fg">
                            Email Configuration Issue
                        </h3>
                        <p className="mb-2 text-sm text-ds-fg-secondary">
                            {flash.warning}
                        </p>
                        {flash.invitation_link && (
                            <button
                                type="button"
                                onClick={handleCopyLink}
                                className="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border border-ds-border-strong bg-ds-surface px-3 text-sm font-semibold text-ds-fg-control transition-colors hover:bg-ds-button-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus"
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
