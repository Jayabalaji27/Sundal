import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { formatCurrency } from '@/utils/currency';

interface InvoiceOzowFormProps {
    invoice: any;
    amount: number;
    onSuccess: () => void;
    onCancel: () => void;
}

export function InvoiceOzowForm({ invoice, amount, onSuccess, onCancel }: InvoiceOzowFormProps) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);

    const handlePayment = async () => {
        setLoading(true);

        try {
            const response = await fetch(route('ozow.create-invoice-payment'), {
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

    return (
        <div className="space-y-4">
            <div className="bg-blue-50 p-4 rounded-lg">
                <div className="text-sm text-blue-600 font-medium">
                    {t('Invoice')} #{invoice.invoice_number}
                </div>
                <div className="text-2xl font-bold text-blue-900">
                    {formatCurrency(amount)}
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
                            {t('Redirecting...')}
                        </>
                    ) : (
                        t('Pay with Ozow')
                    )}
                </Button>
                <Button variant="outline" onClick={onCancel} disabled={loading}>
                    {t('Cancel')}
                </Button>
            </div>
        </div>
    );
}