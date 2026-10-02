import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';
import { toast } from '@/components/custom-toast';

interface InvoiceIyzipayFormProps {
    invoice: any;
    amount: number;
    onSuccess: () => void;
    onCancel: () => void;
}

export function InvoiceIyzipayForm({ invoice, amount, onSuccess, onCancel }: InvoiceIyzipayFormProps) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);

    const handlePayment = async () => {
        setLoading(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(route('iyzipay.create-invoice-payment'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    invoice_token: invoice.payment_token,
                    amount: amount,
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                if (response.status === 419) {
                    // CSRF token expired, reload page
                    window.location.reload();
                    return;
                }
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.redirect_url) {
                // Store invoice info in sessionStorage before redirect
                sessionStorage.setItem('payment_invoice_id', invoice.id.toString());
                window.location.href = data.redirect_url;
            } else {
                throw new Error(data.error || t('Payment creation failed'));
            }
        } catch (error: any) {
            toast.error(error.message || t('Payment failed'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className="bg-muted p-4 rounded-lg">
                <div className="text-sm text-muted-foreground font-medium">
                    {t('Invoice')} #{invoice.invoice_number}
                </div>
                <div className="text-2xl font-bold">
                    {formatCurrency(amount.toFixed(2))}
                </div>
            </div>

            <div className="bg-blue-50 p-3 rounded-lg border border-blue-200">
                <p className="text-sm text-blue-800">
                    {t('You will be redirected to Iyzipay to complete the payment')}
                </p>
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
                        t('Pay with Iyzipay')
                    )}
                </Button>
                <Button
                    variant="outline"
                    onClick={onCancel}
                    disabled={loading}
                >
                    {t('Cancel')}
                </Button>
            </div>
        </div>
    );
}