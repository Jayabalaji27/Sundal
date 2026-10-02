import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CreditCard, AlertCircle } from 'lucide-react';

interface PaiementInvoiceModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    amount: number;
}

export function PaiementInvoiceModal({ isOpen, onClose, invoice, amount }: PaiementInvoiceModalProps) {
    const [processing, setProcessing] = useState(false);

    const handlePayment = async () => {
        setProcessing(true);

        try {
            const response = await fetch(route('paiement.invoice.payment.link', invoice?.payment_token), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    amount: amount
                })
            });

            const data = await response.json();

            if (data.success) {
                const url = data.payment_response?.url;
                if (url) {
                    window.location.href = url;
                } else {
                    alert('Payment URL not received');
                    setProcessing(false);
                }
            } else {
                alert(data.error || 'Payment method not configured');
                setProcessing(false);
            }
        } catch (error: any) {
            alert('Payment failed');
            setProcessing(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <CreditCard className="h-5 w-5" />
                        Paiement Pro Payment
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Your payment will be processed securely through Paiement Pro.
                        </AlertDescription>
                    </Alert>

                    <div className="bg-blue-50 p-4 rounded-lg">
                        <div className="text-sm text-blue-600 font-medium">
                            Invoice #{invoice?.invoice_number || 'N/A'}
                        </div>
                        <div className="text-2xl font-bold text-blue-900">
                            ${(typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2)}
                        </div>
                    </div>

                    <div className="flex gap-3 pt-4">
                        <Button type="button" variant="outline" onClick={onClose} className="flex-1" disabled={processing}>
                            Cancel
                        </Button>
                        <Button onClick={handlePayment} disabled={processing} className="flex-1">
                            {processing ? "Processing..." : "Pay with Paiement Pro"}
                        </Button>
                    </div>

                    <div className="text-xs text-gray-500 text-center">
                        Powered by Paiement Pro - Secure payment processing
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}