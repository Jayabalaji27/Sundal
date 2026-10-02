import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/custom-toast';
import { InvoiceBankPaymentModal } from '@/components/invoice-bank-payment-modal';
import { StripePaymentModal } from '@/components/invoice-stripe-modal';
import { PayPalPaymentModal } from '@/components/invoice-paypal-modal';
import { RazorpayPaymentModal } from '@/components/invoice-razorpay-modal';
import { MercadoPagoPaymentModal } from '@/components/invoice-mercadopago-modal';
import { PaystackPaymentModal } from '@/components/invoice-paystack-modal';
import { FlutterwavePaymentModal } from '@/components/invoice-flutterwave-modal';
import { TapPaymentModal } from '@/components/invoice-tap-modal';
import { XenditPaymentModal } from '@/components/invoice-xendit-modal';
import { PayTRPaymentModal } from '@/components/invoice-paytr-modal';
import { MolliePaymentModal } from '@/components/invoice-mollie-modal';
import { ToyyibPayPaymentModal } from '@/components/invoice-toyyibpay-modal';
import { BenefitInvoiceModal } from '@/components/invoice-benefit-modal';
import { IyzipayInvoiceModal } from '@/components/invoice-iyzipay-modal';
import { AamarpayInvoiceModal } from '@/components/invoice-aamarpay-modal';
import { MidtransInvoiceModal } from '@/components/invoice-midtrans-modal';
import { YooKassaInvoiceModal } from '@/components/invoice-yookassa-modal';
import { PaiementInvoiceModal } from '@/components/invoice-paiement-modal';
import { CinetPayInvoiceModal } from '@/components/invoice-cinetpay-modal';
import { PayHereInvoiceModal } from '@/components/invoice-payhere-modal';
import { InvoiceFedaPayModal } from '@/components/invoice-fedapay-modal';
import { InvoiceAuthorizeNetModal } from '@/components/invoice-authorizenet-modal';
import { InvoiceKhaltiModal } from '@/components/invoice-khalti-modal';
import { InvoiceEasebuzzModal } from '@/components/invoice-easebuzz-modal';
import { InvoiceOzowModal } from '@/components/invoice-ozow-modal';
import { InvoiceCashfreeModal } from '@/components/invoice-cashfree-modal';
import { InvoicePayTabsModal } from '@/components/invoice-paytabs-modal';
import { InvoiceSkrillModal } from '@/components/invoice-skrill-modal';
import { InvoiceCoingateModal } from '@/components/invoice-coingate-modal';
import { InvoicePayfastModal } from '@/components/invoice-payfast-modal';
import { useState } from 'react';

interface InvoicePaymentCopylinkModalProps {
    isOpen: boolean;
    onClose: () => void;
    invoice: any;
    remainingAmount: number;
    paymentAmount: number;
    onPaymentAmountChange: (amount: number) => void;
    gateways: any[];
    paypalClientId?: string;
    paystackPublicKey?: string;
    flutterwavePublicKey?: string;
}

export function InvoicePaymentCopylinkModal({
    isOpen,
    onClose,
    invoice,
    remainingAmount,
    paymentAmount,
    onPaymentAmountChange,
    gateways,
    paypalClientId,
    paystackPublicKey,
    flutterwavePublicKey,
}: InvoicePaymentCopylinkModalProps) {
    const [selectedGateway, setSelectedGateway] = useState<string | null>(null);
    const [showBankPaymentModal, setShowBankPaymentModal] = useState(false);
    const [showStripePaymentModal, setShowStripePaymentModal] = useState(false);
    const [showPayPalPaymentModal, setShowPayPalPaymentModal] = useState(false);
    const [showRazorpayPaymentModal, setShowRazorpayPaymentModal] = useState(false);
    const [showMercadoPagoPaymentModal, setShowMercadoPagoPaymentModal] = useState(false);
    const [showPaystackPaymentModal, setShowPaystackPaymentModal] = useState(false);
    const [showFlutterwavePaymentModal, setShowFlutterwavePaymentModal] = useState(false);
    const [showTapPaymentModal, setShowTapPaymentModal] = useState(false);
    const [showXenditPaymentModal, setShowXenditPaymentModal] = useState(false);
    const [showPayTRPaymentModal, setShowPayTRPaymentModal] = useState(false);
    const [showMolliePaymentModal, setShowMolliePaymentModal] = useState(false);
    const [showToyyibPayPaymentModal, setShowToyyibPayPaymentModal] = useState(false);
    const [showBenefitPaymentModal, setShowBenefitPaymentModal] = useState(false);
    const [showIyzipayPaymentModal, setShowIyzipayPaymentModal] = useState(false);
    const [showAamarpayPaymentModal, setShowAamarpayPaymentModal] = useState(false);
    const [showMidtransPaymentModal, setShowMidtransPaymentModal] = useState(false);
    const [showYooKassaPaymentModal, setShowYooKassaPaymentModal] = useState(false);
    const [showPaiementPaymentModal, setShowPaiementPaymentModal] = useState(false);
    const [showCinetPayPaymentModal, setShowCinetPayPaymentModal] = useState(false);
    const [showPayHerePaymentModal, setShowPayHerePaymentModal] = useState(false);
    const [showFedaPayPaymentModal, setShowFedaPayPaymentModal] = useState(false);
    const [showAuthorizeNetPaymentModal, setShowAuthorizeNetPaymentModal] = useState(false);
    const [showKhaltiPaymentModal, setShowKhaltiPaymentModal] = useState(false);
    const [showEasebuzzPaymentModal, setShowEasebuzzPaymentModal] = useState(false);
    const [showOzowPaymentModal, setShowOzowPaymentModal] = useState(false);
    const [showCashfreePaymentModal, setShowCashfreePaymentModal] = useState(false);
    const [showPayTabsPaymentModal, setShowPayTabsPaymentModal] = useState(false);
    const [showSkrillPaymentModal, setShowSkrillPaymentModal] = useState(false);
    const [showCoinGatePaymentModal, setShowCoinGatePaymentModal] = useState(false);
    const [showPayfastPaymentModal, setShowPayfastPaymentModal] = useState(false);

    const formatAmount = (amount: number | string) => {
        const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount);
        return `$${numericAmount.toFixed(2)}`;
    };

    const closeAllModals = () => {
        setShowBankPaymentModal(false);
        setShowStripePaymentModal(false);
        setShowPayPalPaymentModal(false);
        setShowRazorpayPaymentModal(false);
        setShowMercadoPagoPaymentModal(false);
        setShowPaystackPaymentModal(false);
        setShowFlutterwavePaymentModal(false);
        setShowTapPaymentModal(false);
        setShowXenditPaymentModal(false);
        setShowPayTRPaymentModal(false);
        setShowMolliePaymentModal(false);
        setShowToyyibPayPaymentModal(false);
        setShowBenefitPaymentModal(false);
        setShowIyzipayPaymentModal(false);
        setShowAamarpayPaymentModal(false);
        setShowMidtransPaymentModal(false);
        setShowYooKassaPaymentModal(false);
        setShowPaiementPaymentModal(false);
        setShowCinetPayPaymentModal(false);
        setShowPayHerePaymentModal(false);
        setShowFedaPayPaymentModal(false);
        setShowAuthorizeNetPaymentModal(false);
        setShowKhaltiPaymentModal(false);
        setShowEasebuzzPaymentModal(false);
        setShowOzowPaymentModal(false);
        setShowCashfreePaymentModal(false);
        setShowPayTabsPaymentModal(false);
        setShowSkrillPaymentModal(false);
        setShowCoinGatePaymentModal(false);
        setShowPayfastPaymentModal(false);
    };

    const handleGatewaySelect = (gatewayId: string) => {
        if (!paymentAmount || paymentAmount <= 0) {
            toast.error('Please enter a valid payment amount.');
            return;
        }
        if (paymentAmount > remainingAmount) {
            toast.error('Payment amount cannot exceed remaining balance.');
            return;
        }

        closeAllModals();
        
        if (gatewayId === 'bank') {
            setShowBankPaymentModal(true);
        } else if (gatewayId === 'stripe') {
            setShowStripePaymentModal(true);
        } else if (gatewayId === 'paypal') {
            setShowPayPalPaymentModal(true);
        } else if (gatewayId === 'razorpay') {
            setShowRazorpayPaymentModal(true);
        } else if (gatewayId === 'mercadopago') {
            setShowMercadoPagoPaymentModal(true);
        } else if (gatewayId === 'paystack') {
            setShowPaystackPaymentModal(true);
        } else if (gatewayId === 'flutterwave') {
            setShowFlutterwavePaymentModal(true);
        } else if (gatewayId === 'tap') {
            setShowTapPaymentModal(true);
        } else if (gatewayId === 'xendit') {
            setShowXenditPaymentModal(true);
        } else if (gatewayId === 'paytr') {
            setShowPayTRPaymentModal(true);
        } else if (gatewayId === 'mollie') {
            setShowMolliePaymentModal(true);
        } else if (gatewayId === 'toyyibpay') {
            setShowToyyibPayPaymentModal(true);
        } else if (gatewayId === 'benefit') {
            setShowBenefitPaymentModal(true);
        } else if (gatewayId === 'iyzipay') {
            setShowIyzipayPaymentModal(true);
        } else if (gatewayId === 'aamarpay') {
            setShowAamarpayPaymentModal(true);
        } else if (gatewayId === 'midtrans') {
            setShowMidtransPaymentModal(true);
        } else if (gatewayId === 'yookassa') {
            setShowYooKassaPaymentModal(true);
        } else if (gatewayId === 'paiement') {
            setShowPaiementPaymentModal(true);
        } else if (gatewayId === 'cinetpay') {
            setShowCinetPayPaymentModal(true);
        } else if (gatewayId === 'payhere') {
            setShowPayHerePaymentModal(true);
        } else if (gatewayId === 'fedapay') {
            setShowFedaPayPaymentModal(true);
        } else if (gatewayId === 'authorizenet') {
            setShowAuthorizeNetPaymentModal(true);
        } else if (gatewayId === 'khalti') {
            setShowKhaltiPaymentModal(true);
        } else if (gatewayId === 'easebuzz') {
            setShowEasebuzzPaymentModal(true);
        } else if (gatewayId === 'ozow') {
            setShowOzowPaymentModal(true);
        } else if (gatewayId === 'cashfree') {
            setShowCashfreePaymentModal(true);
        } else if (gatewayId === 'paytabs') {
            setShowPayTabsPaymentModal(true);
        } else if (gatewayId === 'skrill') {
            setShowSkrillPaymentModal(true);
        } else if (gatewayId === 'coingate') {
            setShowCoinGatePaymentModal(true);
        } else if (gatewayId === 'payfast') {
            setShowPayfastPaymentModal(true);
        }
    };

    return (
        <>
            <Dialog open={isOpen} onOpenChange={onClose}>
                <DialogContent className="max-w-md max-h-[90vh] flex flex-col">
                    <DialogHeader className="flex-shrink-0">
                        <DialogTitle>Pay Invoice #{invoice.invoice_number}</DialogTitle>
                    </DialogHeader>
                    
                    <div className="overflow-y-auto flex-1 pr-2">
                        <div className="space-y-4">
                            <div className="p-4 rounded-lg border">
                                <div className="flex justify-between items-center">
                                    <span className="text-sm">Invoice #{invoice.invoice_number}</span>
                                    <span className="font-bold">{formatAmount(invoice.total_amount)}</span>
                                </div>
                                <div className="text-xs mt-1">{invoice.client?.name}</div>
                                <div className="text-xs mt-1">Remaining: {formatAmount(remainingAmount)}</div>
                            </div>

                            <div>
                                <label className="text-sm font-medium mb-2 block">Payment Amount</label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={remainingAmount}
                                    value={paymentAmount}
                                    onChange={(e) => onPaymentAmountChange(Number(e.target.value))}
                                    placeholder="Enter amount to pay"
                                    className="w-full"
                                />
                                <div className="flex gap-2 mt-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onPaymentAmountChange(remainingAmount / 2)}
                                    >
                                        50%
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onPaymentAmountChange(remainingAmount)}
                                    >
                                        Full Amount
                                    </Button>
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium mb-3 block">Select Payment Method</label>
                                <div className="space-y-3 max-h-64 overflow-y-auto">
                                    {gateways.map((gateway) => (
                                        <div
                                            key={gateway.id}
                                            className={`flex items-center p-4 border rounded-lg cursor-pointer transition-all ${selectedGateway === gateway.id
                                                    ? 'border-blue-500 bg-blue-50'
                                                    : 'border-gray-200 hover:border-gray-300'
                                                }`}
                                            onClick={() => setSelectedGateway(gateway.id)}
                                        >
                                            <div className="mr-3">
                                                {gateway.icon}
                                            </div>
                                            <span className="text-sm font-medium">{gateway.name}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="flex gap-3 pt-4">
                                <Button
                                    variant="outline"
                                    className="flex-1"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    className="flex-1"
                                    onClick={() => {
                                        if (!selectedGateway) {
                                            toast.error('Please select a payment method first.');
                                            return;
                                        }
                                        handleGatewaySelect(selectedGateway);
                                    }}
                                    disabled={!selectedGateway || !paymentAmount || paymentAmount <= 0}
                                >
                                    Pay {formatAmount(paymentAmount)}
                                </Button>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <InvoiceBankPaymentModal isOpen={showBankPaymentModal} onClose={() => setShowBankPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <StripePaymentModal isOpen={showStripePaymentModal} onClose={() => setShowStripePaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <PayPalPaymentModal isOpen={showPayPalPaymentModal} onClose={() => setShowPayPalPaymentModal(false)} invoice={invoice} amount={paymentAmount} paypalClientId={paypalClientId} />
            <RazorpayPaymentModal isOpen={showRazorpayPaymentModal} onClose={() => setShowRazorpayPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <MercadoPagoPaymentModal isOpen={showMercadoPagoPaymentModal} onClose={() => setShowMercadoPagoPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <PaystackPaymentModal isOpen={showPaystackPaymentModal} onClose={() => setShowPaystackPaymentModal(false)} invoice={invoice} amount={paymentAmount} paystackKey={paystackPublicKey} />
            <FlutterwavePaymentModal isOpen={showFlutterwavePaymentModal} onClose={() => setShowFlutterwavePaymentModal(false)} invoice={invoice} amount={paymentAmount} flutterwavePublicKey={flutterwavePublicKey} />
            <TapPaymentModal isOpen={showTapPaymentModal} onClose={() => setShowTapPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <XenditPaymentModal isOpen={showXenditPaymentModal} onClose={() => setShowXenditPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <PayTRPaymentModal isOpen={showPayTRPaymentModal} onClose={() => setShowPayTRPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <MolliePaymentModal isOpen={showMolliePaymentModal} onClose={() => setShowMolliePaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <ToyyibPayPaymentModal isOpen={showToyyibPayPaymentModal} onClose={() => setShowToyyibPayPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <BenefitInvoiceModal isOpen={showBenefitPaymentModal} onClose={() => setShowBenefitPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <IyzipayInvoiceModal isOpen={showIyzipayPaymentModal} onClose={() => setShowIyzipayPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <AamarpayInvoiceModal isOpen={showAamarpayPaymentModal} onClose={() => setShowAamarpayPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <MidtransInvoiceModal isOpen={showMidtransPaymentModal} onClose={() => setShowMidtransPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <YooKassaInvoiceModal isOpen={showYooKassaPaymentModal} onClose={() => setShowYooKassaPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <PaiementInvoiceModal isOpen={showPaiementPaymentModal} onClose={() => setShowPaiementPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <CinetPayInvoiceModal isOpen={showCinetPayPaymentModal} onClose={() => setShowCinetPayPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <PayHereInvoiceModal isOpen={showPayHerePaymentModal} onClose={() => setShowPayHerePaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoiceFedaPayModal isOpen={showFedaPayPaymentModal} onClose={() => setShowFedaPayPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoiceAuthorizeNetModal isOpen={showAuthorizeNetPaymentModal} onClose={() => setShowAuthorizeNetPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoiceKhaltiModal isOpen={showKhaltiPaymentModal} onClose={() => setShowKhaltiPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoiceEasebuzzModal isOpen={showEasebuzzPaymentModal} onClose={() => setShowEasebuzzPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoiceOzowModal isOpen={showOzowPaymentModal} onClose={() => setShowOzowPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoiceCashfreeModal isOpen={showCashfreePaymentModal} onClose={() => setShowCashfreePaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoicePayTabsModal isOpen={showPayTabsPaymentModal} onClose={() => setShowPayTabsPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoiceSkrillModal isOpen={showSkrillPaymentModal} onClose={() => setShowSkrillPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoiceCoingateModal isOpen={showCoinGatePaymentModal} onClose={() => setShowCoinGatePaymentModal(false)} invoice={invoice} amount={paymentAmount} />
            <InvoicePayfastModal isOpen={showPayfastPaymentModal} onClose={() => setShowPayfastPaymentModal(false)} invoice={invoice} amount={paymentAmount} />
        </>
    );
}
