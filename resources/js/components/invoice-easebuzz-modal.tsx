import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

interface InvoiceEasebuzzModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    amount: number;
}

export function InvoiceEasebuzzModal({ isOpen, onClose, invoice, amount }: InvoiceEasebuzzModalProps) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);

    const handlePayment = async () => {
        setLoading(true);

        try {
            const response = await fetch(route('easebuzz.create-invoice-payment-from-link', invoice.payment_token), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: amount,
                }),
            });

            if (!response.ok) {
                const text = await response.text();
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.payment_url) {
                window.location.href = data.payment_url;
            } else {
                throw new Error(data.error || t('Payment creation failed'));
            }
        } catch (error: any) {
            toast.error(error.message || t('Payment failed'));
            setLoading(false);
        }
    };

    const formatAmount = (amount: number | string) => {
        return `$${(typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2)}`;
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <span className="text-2xl">💳</span>
                        {t('Easebuzz Payment')}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="bg-blue-50 p-4 rounded-lg">
                        <div className="text-sm text-blue-600 font-medium">
                            {t('Invoice')} #{invoice.invoice_number}
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
                            {loading ? t('Redirecting...') : t('Pay with Easebuzz')}
                        </Button>
                        <Button variant="outline" onClick={onClose} disabled={loading}>
                            {t('Cancel')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
