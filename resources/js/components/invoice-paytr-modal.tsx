import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-react';
import { toast } from '@/components/custom-toast';

interface PayTRPaymentModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    amount: number;
}

export function PayTRPaymentModal({ isOpen, onClose, invoice, amount }: PayTRPaymentModalProps) {
    const [loading, setLoading] = useState(false);

    const formatAmount = (amount: number | string) => {
        if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
            return window.appSettings.formatCurrency(amount);
        }
        return `$${(typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2)}`;
    };

    const handlePayment = async () => {
        setLoading(true);

        try {
            const response = await fetch(route('paytr.invoice.payment.link', invoice.payment_token), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    invoice_token: invoice.payment_token,
                    amount: amount,
                }),
            });

            const data = await response.json();

            if (data.success && data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                throw new Error(data.error || 'Payment creation failed');
            }
        } catch (error: any) {
            toast.error(error.message || 'Payment failed');
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <span className="text-2xl">💳</span>
                        PayTR Payment
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="bg-blue-50 p-4 rounded-lg">
                        <div className="text-sm text-blue-600 font-medium">
                            Invoice #{invoice.invoice_number}
                        </div>
                        <div className="text-2xl font-bold text-blue-900">
                            {formatAmount(amount)}
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <Button
                            onClick={handlePayment}
                            disabled={loading}
                            className="flex-1"
                        >
                            {loading ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Redirecting...
                                </>
                            ) : (
                                'Pay with PayTR'
                            )}
                        </Button>
                        <Button variant="outline" onClick={onClose} disabled={loading}>
                            Cancel
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}