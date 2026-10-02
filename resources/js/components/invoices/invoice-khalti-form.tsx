import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { formatCurrency } from '@/utils/currency';

interface InvoiceKhaltiFormProps {
    invoice: any;
    amount: number;
    onSuccess: () => void;
    onCancel: () => void;
    khaltiPublicKey?: string;
}

export function InvoiceKhaltiForm({ invoice, amount, onSuccess, onCancel, khaltiPublicKey }: InvoiceKhaltiFormProps) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);

    const handlePayment = async () => {
        setLoading(true);

        try {
            const response = await fetch(route('khalti.create-invoice-payment'), {
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

            if (data.success && data.public_key) {
                // Initialize Khalti payment
                const config = {
                    publicKey: data.public_key,
                    productIdentity: data.product_identity,
                    productName: data.product_name,
                    productUrl: window.location.origin,
                    paymentPreference: ['KHALTI', 'EBANKING', 'MOBILE_BANKING', 'CONNECT_IPS', 'SCT'],
                    eventHandler: {
                        onSuccess: (payload: any) => {
                            // Create form to submit to backend for proper redirect
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = route('khalti.process-invoice-payment');
                            
                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = '_token';
                            csrfInput.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                            form.appendChild(csrfInput);
                            
                            const tokenInput = document.createElement('input');
                            tokenInput.type = 'hidden';
                            tokenInput.name = 'invoice_token';
                            tokenInput.value = invoice.payment_token;
                            form.appendChild(tokenInput);
                            
                            const amountInput = document.createElement('input');
                            amountInput.type = 'hidden';
                            amountInput.name = 'amount';
                            amountInput.value = amount.toString();
                            form.appendChild(amountInput);
                            
                            const paymentTokenInput = document.createElement('input');
                            paymentTokenInput.type = 'hidden';
                            paymentTokenInput.name = 'token';
                            paymentTokenInput.value = payload.token;
                            form.appendChild(paymentTokenInput);
                            
                            document.body.appendChild(form);
                            form.submit();
                        },
                        onError: (error: any) => {
                            console.error('Khalti payment error:', error);
                            toast.error(t('Payment failed'));
                            setLoading(false);
                        },
                        onClose: () => {
                            setLoading(false);
                        }
                    }
                };

                // Load Khalti script if not already loaded
                if (typeof (window as any).KhaltiCheckout === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.17.0.0.0/khalti-checkout.iffe.js';
                    script.onload = () => {
                        const checkout = new (window as any).KhaltiCheckout(config);
                        checkout.show({ amount: data.amount });
                    };
                    document.head.appendChild(script);
                } else {
                    const checkout = new (window as any).KhaltiCheckout(config);
                    checkout.show({ amount: data.amount });
                }
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
                            {t('Loading...')}
                        </>
                    ) : (
                        t('Pay with Khalti')
                    )}
                </Button>
                <Button variant="outline" onClick={onCancel} disabled={loading}>
                    {t('Cancel')}
                </Button>
            </div>
        </div>
    );
}