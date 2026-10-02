import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

interface InvoiceKhaltiModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    amount: number;
}

export function InvoiceKhaltiModal({ isOpen, onClose, invoice, amount }: InvoiceKhaltiModalProps) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);

    const handlePayment = async () => {
        setLoading(true);

        try {
            const response = await fetch(route('khalti.process-invoice-payment-from-link', invoice.payment_token), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: amount,
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (jsonError) {
                console.error('Invalid JSON response:', text);
                throw new Error(text || 'Server returned invalid response');
            }

            if (data.success && data.public_key) {
                const config = {
                    publicKey: data.public_key,
                    productIdentity: 'invoice_' + invoice.id,
                    productName: 'Invoice Payment - ' + invoice.invoice_number,
                    productUrl: window.location.origin,
                    paymentPreference: ['KHALTI', 'EBANKING', 'MOBILE_BANKING', 'CONNECT_IPS', 'SCT'],
                    embedded: true,
                    eventHandler: {
                        onSuccess: (payload: any) => {
                            onClose();
                            window.location.href = route('khalti.invoice-success-from-link', invoice.payment_token) + '?token=' + payload.token + '&amount=' + payload.amount;
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

                if (typeof (window as any).KhaltiCheckout === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'https://khalti.s3.ap-south-1.amazonaws.com/KPG/dist/2020.12.17.0.0.0/khalti-checkout.iffe.js';
                    script.onload = () => {
                        const checkout = new (window as any).KhaltiCheckout(config);
                        checkout.show({
                            amount: data.amount,
                            target: '_self'
                        });
                    };
                    document.head.appendChild(script);
                } else {
                    const checkout = new (window as any).KhaltiCheckout(config);
                    checkout.show({
                        amount: data.amount,
                        target: '_self'
                    });
                }
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
                        {t('Khalti Payment')}
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
                            {loading ? t('Loading...') : t('Pay with Khalti')}
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
