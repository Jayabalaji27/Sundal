import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Wallet } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

interface InvoiceSkrillModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    amount: number;
}

export function InvoiceSkrillModal({ isOpen, onClose, invoice, amount }: InvoiceSkrillModalProps) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);
    const [email, setEmail] = useState('');

    const handlePayment = async () => {
        if (!email) {
            toast.error(t('Please enter your email address'));
            return;
        }

        setLoading(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(route('skrill.create-invoice-payment-from-link', invoice.payment_token), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrfToken || '',
                    "X-Requested-With": "XMLHttpRequest"
                },
                body: JSON.stringify({
                    amount: amount,
                    email: email
                }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.redirect_url) {
                if (data.method === 'POST' && data.data) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = data.redirect_url;

                    Object.keys(data.data).forEach(key => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = data.data[key];
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                } else {
                    window.location.href = data.redirect_url;
                }
            } else {
                throw new Error(data.error || "Payment failed");
            }
        } catch (error: any) {
            toast.error(error.message || t("Payment failed"));
        } finally {
            setLoading(false);
        }
    };

    const formatAmount = (amt: number | string) => {
        const numAmount = typeof amt === 'string' ? parseFloat(amt) : amt;
        return `$${numAmount.toFixed(2)}`;
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Wallet className="h-5 w-5" />
                        {t("Skrill Payment")}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <div className="text-sm text-blue-600 font-medium">
                            {t("Invoice")} #{invoice?.invoice_number || 'N/A'}
                        </div>
                        <div className="text-2xl font-bold text-blue-900">
                            {formatAmount(amount)}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="email">{t("Email Address")}</Label>
                        <Input
                            id="email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder={t("Enter your email address")}
                            required
                        />
                    </div>

                    <div className="bg-blue-50 p-3 rounded-lg border border-blue-200">
                        <p className="text-sm text-blue-800">
                            {t("You will be redirected to Skrill to complete the payment")}
                        </p>
                    </div>

                    <div className="flex gap-3">
                        <Button
                            onClick={handlePayment}
                            disabled={loading || !email}
                            className="flex-1"
                        >
                            {loading ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    {t("Processing...")}
                                </>
                            ) : (
                                t("Pay with Skrill")
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={onClose}
                            disabled={loading}
                        >
                            {t("Cancel")}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
