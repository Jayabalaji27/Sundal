import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, CreditCard, AlertCircle, ExternalLink } from 'lucide-react';
import { toast } from '@/components/custom-toast';

interface XenditPaymentModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    amount: number;
}

export function XenditPaymentModal({ isOpen, onClose, invoice, amount }: XenditPaymentModalProps) {
    const [processing, setProcessing] = useState(false);

    const formatAmount = (amount: number | string) => {
        if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
            return window.appSettings.formatCurrency(amount);
        }
        return `$${(typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2)}`;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        try {
            const response = await fetch(route('xendit.invoice.payment.link', invoice.payment_token), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    invoice_token: invoice.payment_token,
                    amount: amount
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (data.success && data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                throw new Error(data.error || 'Payment creation failed');
            }
        } catch (error: any) {
            toast.error(error.message || 'Payment processing failed');
            setProcessing(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <CreditCard className="h-5 w-5" />
                        Xendit Payment
                    </DialogTitle>
                </DialogHeader>
                
                <div className="space-y-4">
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            You will be redirected to Xendit to complete your payment securely.
                        </AlertDescription>
                    </Alert>

                    <div className="bg-blue-50 p-4 rounded-lg">
                        <div className="text-sm text-blue-600 font-medium">
                            Invoice #{invoice.invoice_number}
                        </div>
                        <div className="text-2xl font-bold text-blue-900">
                            {formatAmount(amount)}
                        </div>
                    </div>

                    <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 className="font-medium text-blue-900 mb-2">Supported Payment Methods</h4>
                        <ul className="text-sm text-blue-800 space-y-1">
                            <li>• Credit/Debit Cards</li>
                            <li>• Bank Transfer</li>
                            <li>• E-Wallets (OVO, DANA, LinkAja)</li>
                            <li>• Virtual Accounts</li>
                            <li>• Retail Outlets</li>
                        </ul>
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
                                        Redirecting...
                                    </>
                                ) : (
                                    <>
                                        <ExternalLink className="mr-2 h-4 w-4" />
                                        Pay with Xendit
                                    </>
                                )}
                            </Button>
                        </div>
                    </form>

                    <div className="text-xs text-muted-foreground text-center">
                        Powered by Xendit - Secure payment processing
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}