import { useEffect, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Loader2, CreditCard, AlertCircle } from 'lucide-react';

interface PaystackPaymentModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    amount: number;
    paystackKey?: string;
}

export function PaystackPaymentModal({
    isOpen,
    onClose,
    invoice,
    amount,
    paystackKey
}: PaystackPaymentModalProps) {
    const [processing, setProcessing] = useState(false);
    const initialized = useRef(false);
    


    const formatAmount = (amount: number | string) => {
        if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
            return window.appSettings.formatCurrency(amount);
        }
        return `$${(typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2)}`;
    };

    useEffect(() => {
        if (!paystackKey || !isOpen || initialized.current) return;

        const script = document.createElement('script');
        script.src = 'https://js.paystack.co/v1/inline.js';
        script.async = true;

        script.onload = () => {
            initialized.current = true;
        };

        document.head.appendChild(script);

        return () => {
            if (document.head.contains(script)) {
                document.head.removeChild(script);
            }
        };
    }, [paystackKey, isOpen]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!window.PaystackPop) {
            toast.error('Paystack not loaded');
            return;
        }

        setProcessing(true);

        const handler = window.PaystackPop.setup({
            key: paystackKey,
            email: invoice.client?.email || 'customer@example.com',
            amount: Math.round(Number(amount) * 100), // Convert to kobo
            currency: 'NGN',
            callback: function (response: any) {
                router.post(route('paystack.invoice.payment.link', invoice.payment_token), {
                    payment_method: 'paystack',
                    payment_id: response.reference,
                    amount: amount,
                }, {
                    onSuccess: () => {
                        setProcessing(false);
                        onClose();
                    },
                    onError: (errors) => {
                        toast.error(Object.values(errors).join(', '));
                        setProcessing(false);
                    }
                });
            },
            onClose: function () {
                setProcessing(false);
            }
        });

        handler.openIframe();
    };

    if (!paystackKey) {
        return (
            <Dialog open={isOpen} onOpenChange={onClose}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <CreditCard className="h-5 w-5" />
                            Paystack Payment
                        </DialogTitle>
                    </DialogHeader>
                    <div className="p-4 text-center text-red-500">
                        Paystack not configured
                    </div>
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <CreditCard className="h-5 w-5" />
                        Paystack Payment
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            You will be redirected to Paystack to complete your payment securely.
                        </AlertDescription>
                    </Alert>

                    <div className="bg-muted p-4 rounded-lg">
                        <div className="flex justify-between items-center">
                            <span className="font-medium">Amount to Pay</span>
                            <span className="text-lg font-bold">{formatAmount(amount)}</span>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="flex gap-3 pt-4">
                            <Button type="button" variant="outline" onClick={onClose} className="flex-1" disabled={processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing} className="flex-1">
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Processing...
                                    </>
                                ) : (
                                    'Pay with Paystack'
                                )}
                            </Button>
                        </div>
                    </form>

                    <div className="text-xs text-muted-foreground text-center">
                        Powered by Paystack - Secure payment processing
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

declare global {
    interface Window {
        PaystackPop?: any;
    }
}