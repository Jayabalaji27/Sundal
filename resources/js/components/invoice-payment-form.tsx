import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { toast } from '@/components/custom-toast';
import { Loader2 } from 'lucide-react';
import { InvoiceStripeForm } from '@/components/invoices/invoice-stripe-form';

interface InvoicePaymentFormProps {
    invoiceToken: string;
    amount: number;
    paymentMethod: string;
    stripeKey?: string;
    onSuccess: () => void;
    onCancel: () => void;
}

export function InvoicePaymentForm({ 
    invoiceToken, 
    amount, 
    paymentMethod,
    stripeKey, 
    onSuccess, 
    onCancel 
}: InvoicePaymentFormProps) {
    const [processing, setProcessing] = useState(false);

    const handlePayment = () => {
        setProcessing(true);
        
        router.post(route('invoices.payment.process', invoiceToken), {
            payment_method: 'bank',
            invoice_token: invoiceToken,
            amount: amount,
        }, {
            onSuccess: () => {
                onSuccess();
            },
            onError: (errors) => {
                const errorMessage = errors.error || 'Payment processing failed. Please try again.';
                toast.error(errorMessage);
            },
            onFinish: () => {
                setProcessing(false);
            }
        });
    };

    const getPaymentMethodName = (method: string) => {
        const methodNames = {
            stripe: 'Credit Card (Stripe)',
            paypal: 'PayPal',
            razorpay: 'Razorpay',
            bank: 'Bank Transfer',
            paystack: 'Paystack',
            flutterwave: 'Flutterwave',
            paytabs: 'PayTabs',
            skrill: 'Skrill',
            coingate: 'CoinGate',
            payfast: 'PayFast',
            tap: 'Tap',
            xendit: 'Xendit',
            paytr: 'PayTR',
            mollie: 'Mollie',
            toyyibpay: 'ToyyibPay',
            cashfree: 'Cashfree',
            khalti: 'Khalti',
            iyzipay: 'Iyzipay',
            benefit: 'Benefit',
            ozow: 'Ozow',
            easebuzz: 'Easebuzz',
            authorizenet: 'Authorize.Net',
            fedapay: 'FedaPay',
            payhere: 'PayHere',
            cinetpay: 'CinetPay',
            paiement: 'PaiementPro',
            yookassa: 'YooKassa',
            aamarpay: 'AamarPay',
            midtrans: 'Midtrans',
            paymentwall: 'PaymentWall',
            sspay: 'SSPay'
        };
        return methodNames[method] || method;
    };

    const formatAmount = (amount: number) => {
        return `$${amount.toFixed(2)}`;
    };

    // Show Stripe payment form for Stripe payments
    if (paymentMethod === 'stripe') {
        return (
            <InvoiceStripeForm
                invoice={{ id: 14, balance_due: amount }}
                stripeKey={stripeKey || 'pk_test_aLoicAw16w12wxLlmI9uluaf008XMnn2VJ'}
                onSuccess={onSuccess}
                onCancel={onCancel}
            />
        );
    }

    return (
        <div className="space-y-4">
            <Card>
                <CardContent className="p-4">
                    <div className="space-y-3">
                        <div className="flex justify-between items-center">
                            <span className="text-sm text-gray-600">Payment Method:</span>
                            <span className="font-medium">{getPaymentMethodName(paymentMethod)}</span>
                        </div>
                        <div className="flex justify-between items-center">
                            <span className="text-sm text-gray-600">Amount:</span>
                            <span className="font-bold text-lg">{formatAmount(amount)}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {paymentMethod === 'bank' && (
                <Card className="border-blue-200 bg-blue-50">
                    <CardContent className="p-4">
                        <div className="text-sm text-blue-800">
                            <p className="font-medium mb-2">Bank Transfer Instructions:</p>
                            <ul className="space-y-1 text-xs">
                                <li>• Transfer the exact amount shown above</li>
                                <li>• Include the invoice number in the transfer description</li>
                                <li>• Your payment will be verified within 1-3 business days</li>
                                <li>• You will receive a confirmation email once verified</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            )}

            <div className="flex gap-3 pt-4">
                <Button
                    variant="outline"
                    onClick={onCancel}
                    disabled={processing}
                    className="flex-1"
                >
                    Cancel
                </Button>
                <Button
                    onClick={handlePayment}
                    disabled={processing}
                    className="flex-1"
                >
                    {processing ? (
                        <>
                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                            Processing...
                        </>
                    ) : (
                        `Pay ${formatAmount(amount)}`
                    )}
                </Button>
            </div>
        </div>
    );
}