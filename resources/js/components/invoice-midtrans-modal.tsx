import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

interface MidtransInvoiceModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    amount: number;
}

export function MidtransInvoiceModal({ isOpen, onClose, invoice, amount }: MidtransInvoiceModalProps) {
    const [loading, setLoading] = useState(false);

    const handlePayment = async () => {
        setLoading(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch(route('midtrans.invoice.payment.link', invoice?.payment_token), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    amount: amount,
                }),
            });

            const responseText = await response.text();

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: Server returned HTML instead of JSON`);
            }

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Server returned invalid JSON response');
            }

            if (data.success) {
                if (data.snap_token) {
                    // Load Midtrans Snap if not already loaded
                    if (typeof (window as any).snap === 'undefined') {
                        const script = document.createElement('script');
                        script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
                        script.setAttribute('data-client-key', 'SB-Mid-client-your-client-key'); // Replace with actual client key
                        document.head.appendChild(script);

                        script.onload = () => {
                            (window as any).snap.pay(data.snap_token, {
                                onSuccess: function(result: any) {
                                    window.location.href = route('midtrans.invoice.success.link', invoice?.payment_token) + `?order_id=${data.order_id}&amount=${amount}`;
                                },
                                onPending: function(result: any) {
                                    alert('Payment is being processed');
                                },
                                onError: function(result: any) {
                                    alert('Payment failed');
                                    setLoading(false);
                                },
                                onClose: function() {
                                    setLoading(false);
                                }
                            });
                        };
                    } else {
                        (window as any).snap.pay(data.snap_token, {
                            onSuccess: function(result: any) {
                                window.location.href = route('midtrans.invoice.success.link', invoice?.payment_token) + `?order_id=${data.order_id}&amount=${amount}`;
                            },
                            onPending: function(result: any) {
                                alert('Payment is being processed');
                            },
                            onError: function(result: any) {
                                alert('Payment failed');
                                setLoading(false);
                            },
                            onClose: function() {
                                setLoading(false);
                            }
                        });
                    }
                } else if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                }
            } else {
                throw new Error(data.error || 'Payment creation failed');
            }
        } catch (error: any) {
            alert(error.message || 'Payment failed');
            setLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <div className="w-8 h-6 bg-blue-600 rounded flex items-center justify-center">
                            <span className="text-white text-xs font-bold">M</span>
                        </div>
                        Midtrans Payment
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="bg-blue-50 p-4 rounded-lg">
                        <div className="text-sm text-blue-600 font-medium">
                            Invoice #{invoice?.invoice_number || 'N/A'}
                        </div>
                        <div className="text-2xl font-bold text-blue-900">
                            ${(typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2)}
                        </div>
                    </div>

                    <div className="bg-orange-50 p-3 rounded-lg border border-orange-200">
                        <p className="text-sm text-orange-800">
                            <strong>Test Mode:</strong> This will simulate Midtrans payment processing
                        </p>
                    </div>

                    <div className="flex gap-3">
                        <Button
                            onClick={handlePayment}
                            disabled={loading}
                            className="flex-1 bg-blue-600 hover:bg-blue-700"
                        >
                            {loading ? "Processing..." : "Pay with Midtrans"}
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