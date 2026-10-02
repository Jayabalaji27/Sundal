import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Loader2, CreditCard, DollarSign, IndianRupee, Banknote } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';
import { InvoiceStripeForm } from '@/components/invoices/invoice-stripe-form';
import { InvoicePayPalForm } from '@/components/invoices/invoice-paypal-form';
import { InvoiceRazorpayForm } from '@/components/invoices/invoice-razorpay-form';
import { InvoiceMercadoPagoForm } from '@/components/invoices/invoice-mercadopago-form';
import { InvoicePaystackForm } from '@/components/invoices/invoice-paystack-form';
import { InvoiceFlutterwaveForm } from '@/components/invoices/invoice-flutterwave-form';
import { InvoiceTapForm } from '@/components/invoices/invoice-tap-form';
import { InvoiceXenditForm } from '@/components/invoices/invoice-xendit-form';
import { InvoicePayTRForm } from '@/components/invoices/invoice-paytr-form';
import { InvoiceMollieForm } from '@/components/invoices/invoice-mollie-form';
import { InvoiceToyyibPayForm } from '@/components/invoices/invoice-toyyibpay-form';
import { InvoiceBenefitForm } from '@/components/invoices/invoice-benefit-form';
import { InvoiceAamarpayForm } from '@/components/invoices/invoice-aamarpay-form';
import { InvoiceMidtransForm } from '@/components/invoices/invoice-midtrans-form';
import { InvoiceYooKassaForm } from '@/components/invoices/invoice-yookassa-form';
import { InvoicePaymentForm } from '@/components/invoice-payment-form';
import { InvoicePaiementForm } from '@/components/invoices/invoice-paiement-form';
import { InvoiceCinetPayForm } from '@/components/invoices/invoice-cinetpay-form';
import { InvoicePayHereForm } from '@/components/invoices/invoice-payhere-form';
import { InvoiceFedaPayForm } from '@/components/invoices/invoice-fedapay-form';
import { InvoiceAuthorizeNetForm } from '@/components/invoices/invoice-authorizenet-form';
import { InvoiceKhaltiForm } from '@/components/invoices/invoice-khalti-form';
import { InvoiceEasebuzzForm } from '@/components/invoices/invoice-easebuzz-form';
import { InvoiceSkrillForm } from '@/components/invoices/invoice-skrill-form';
import { InvoiceCoinGateForm } from '@/components/invoices/invoice-coingate-form';
import { InvoicePayfastForm } from '@/components/invoices/invoice-payfast-form';
import { InvoiceIyzipayForm } from '@/components/invoices/invoice-iyzipay-form';
import { InvoiceOzowForm } from '@/components/invoices/invoice-ozow-form';
import { InvoiceCashfreeForm } from '@/components/invoices/invoice-cashfree-form';
import { InvoicePayTabsForm } from '@/components/invoices/invoice-paytabs-form';
import { formatCurrency } from '@/utils/currency';
import axios from 'axios';

interface PaymentMethod {
  id: string;
  name: string;
  enabled: boolean;
  config: any;
}

interface Invoice {
  id: number;
  invoice_number: string;
  total_amount: number;
  balance_due?: number;
  paid_amount?: number;
  payment_token: string;
}

interface InvoicePaymentModalProps {
  invoice: Invoice;
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
  enabledGateways?: any[];
  remainingAmount?: number;
}

export function InvoicePaymentModal({ invoice, open, onClose, onSuccess, enabledGateways = [], remainingAmount: propRemainingAmount }: InvoicePaymentModalProps) {
  const { t } = useTranslation();
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
  const [selectedMethod, setSelectedMethod] = useState<string>('');
  const [loading, setLoading] = useState(true);
  const [paymentCredentials, setPaymentCredentials] = useState<any>({});
  const [showPaymentForm, setShowPaymentForm] = useState(false);
  const remainingAmount = propRemainingAmount || invoice?.balance_due || (invoice?.total_amount - (invoice?.paid_amount || 0)) || 0;
  const [paymentAmount, setPaymentAmount] = useState(remainingAmount);

  useEffect(() => {
    if (open) {
      fetchPaymentMethods();
    }
  }, [open]);

  const fetchPaymentMethods = async () => {
    try {
      setLoading(true);
      const response = await axios.get(route('api.invoices.payment-methods', invoice.id));
      setPaymentMethods(response.data.gateways);
      setPaymentCredentials(response.data);
    } catch (error) {
      toast.error(t('Failed to load payment methods'));
    } finally {
      setLoading(false);
    }
  };

  const handleMethodSelect = (methodId: string) => {
    setSelectedMethod(methodId);
    setShowPaymentForm(true);
  };

  const handlePaymentSuccess = () => {
    onSuccess();
    onClose();
  };

  const handlePaymentCancel = () => {
    setShowPaymentForm(false);
    setSelectedMethod('');
  };

  const getMethodIcon = (methodId: string) => {
    switch (methodId) {
      case 'stripe':
      case 'mercadopago':
      case 'paystack':
      case 'flutterwave':
      case 'paytabs':
      case 'payfast':
      case 'toyyibpay':
      case 'paytr':
      case 'mollie':
      case 'cashfree':
      case 'iyzipay':
      case 'benefit':
      case 'ozow':
      case 'easebuzz':
      case 'khalti':
      case 'authorizenet':
      case 'fedapay':
      case 'payhere':
      case 'cinetpay':
      case 'paiement':
      case 'nepalste':
      case 'yookassa':
      case 'aamarpay':
      case 'midtrans':
      case 'tap':
      case 'xendit':
        return <CreditCard className="h-5 w-5" />;
      case 'paypal':
      case 'skrill':
        return <DollarSign className="h-5 w-5" />;
      case 'razorpay':
        return <IndianRupee className="h-5 w-5" />;
      case 'bank':
        return <Banknote className="h-5 w-5" />;
      default:
        return <CreditCard className="h-5 w-5" />;
    }
  };

  const renderPaymentForm = () => {
    const method = paymentMethods.find(m => m.id === selectedMethod);
    if (!method) return null;

    const commonProps = {
      invoice,
      onSuccess: handlePaymentSuccess,
      onCancel: handlePaymentCancel,
    };

    switch (selectedMethod) {
      case 'stripe':
        return (
          <InvoiceStripeForm
            invoice={{...invoice, amount: paymentAmount}}
            stripeKey={paymentCredentials.stripeKey || 'pk_test_default'}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'bank':
        return (
          <InvoicePaymentForm
            invoiceToken={invoice.payment_token}
            amount={paymentAmount}
            paymentMethod="bank"
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'paypal':
        return <InvoicePayPalForm {...commonProps} paypalClientId={paymentCredentials.paypalClientId} amount={paymentAmount} />;
      case 'razorpay':
        return <InvoiceRazorpayForm {...commonProps} razorpayKey={paymentCredentials.razorpayKey} amount={paymentAmount} />;
      case 'mercadopago':
        return <InvoiceMercadoPagoForm {...commonProps} mercadopagoAccessToken={paymentCredentials.mercadopagoAccessToken} amount={paymentAmount} />;
      case 'paystack':
        return <InvoicePaystackForm {...commonProps} paystackPublicKey={paymentCredentials.paystackPublicKey} amount={paymentAmount} />;
      case 'flutterwave':
        return <InvoiceFlutterwaveForm {...commonProps} flutterwavePublicKey={paymentCredentials.flutterwavePublicKey} amount={paymentAmount} />;
      case 'tap':
        return <InvoiceTapForm {...commonProps} tapPublicKey={paymentCredentials.tapPublicKey} amount={paymentAmount} />;
      case 'xendit':
        return <InvoiceXenditForm {...commonProps} xenditApiKey={paymentCredentials.xenditApiKey} amount={paymentAmount} />;
      case 'paytr':
        return <InvoicePayTRForm {...commonProps} paytrMerchantId={paymentCredentials.paytrMerchantId} amount={paymentAmount} />;
      case 'mollie':
        return <InvoiceMollieForm {...commonProps} mollieApiKey={paymentCredentials.mollieApiKey} amount={paymentAmount} />;
      case 'toyyibpay':
        return <InvoiceToyyibPayForm {...commonProps} toyyibpayCategoryCode={paymentCredentials.toyyibpayCategoryCode} amount={paymentAmount} />;
      case 'benefit':
        return <InvoiceBenefitForm {...commonProps} amount={paymentAmount} />;
      case 'cinetpay':
        return (
          <InvoiceCinetPayForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
            cinetpaySiteId={paymentCredentials.cinetpaySiteId}
          />
        );
      case 'payhere':
        return (
          <InvoicePayHereForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
            payhereMerchantId={paymentCredentials.payhereMerchantId}
          />
        );
      case 'fedapay':
        return (
          <InvoiceFedaPayForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
            fedapaySecretKey={paymentCredentials.fedapaySecretKey}
          />
        );
      case 'authorizenet':
        return (
          <InvoiceAuthorizeNetForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'ozow':
        return (
          <InvoiceOzowForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'cashfree':
        return (
          <InvoiceCashfreeForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'paytabs':
        return (
          <InvoicePayTabsForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'skrill':
        return (
          <InvoiceSkrillForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'coingate':
        return (
          <InvoiceCoinGateForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'payfast':
        return (
          <InvoicePayfastForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'easebuzz':
        return (
          <InvoiceEasebuzzForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'khalti':
        return (
          <InvoiceKhaltiForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
            khaltiPublicKey={paymentCredentials.khaltiPublicKey}
          />
        );
      case 'iyzipay':
        return (
          <InvoiceIyzipayForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'paiement':
        return (
          <InvoicePaiementForm
            invoice={invoice}
            amount={paymentAmount}
            onSuccess={handlePaymentSuccess}
            onCancel={handlePaymentCancel}
          />
        );
      case 'yookassa':
        return <InvoiceYooKassaForm {...commonProps} amount={paymentAmount} />;
      case 'aamarpay':
        return <InvoiceAamarpayForm {...commonProps} amount={paymentAmount} />;
      case 'midtrans':
        return <InvoiceMidtransForm {...commonProps} amount={paymentAmount} />;
      default:
        return null;
    }
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>
            {t('Pay Invoice')} {invoice.invoice_number}
          </DialogTitle>
        </DialogHeader>

        {loading ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin" />
          </div>
        ) : showPaymentForm ? (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="font-medium">{t('Complete Payment')}</h3>
              <Button variant="outline" size="sm" onClick={handlePaymentCancel}>
                {t('Back')}
              </Button>
            </div>
            {renderPaymentForm()}
          </div>
        ) : (
          <div className="space-y-4">
            {/* Payment Summary */}
            <Card>
              <CardContent className="p-4">
                <div className="flex justify-between items-center">
                  <div>
                    <h3 className="font-medium">{invoice.invoice_number}</h3>
                    <p className="text-sm text-muted-foreground">
                      {t('Invoice Payment')}
                    </p>
                  </div>
                  <div className="text-right">
                    <div className="text-lg font-bold">
                      {formatCurrency(remainingAmount)}
                    </div>
                    <div className="text-sm text-muted-foreground">
                      {t('Amount Due')}
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Payment Amount */}
            <div className="space-y-2">
              <Label htmlFor="payment-amount">{t('Payment Amount')}</Label>
              <Input
                id="payment-amount"
                type="number"
                step="0.01"
                min="0.01"
                max={remainingAmount}
                value={paymentAmount}
                onChange={(e) => setPaymentAmount(Number(e.target.value))}
                placeholder={t('Enter amount to pay')}
              />
              <div className="flex gap-2 mt-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setPaymentAmount(remainingAmount / 2)}
                >
                  50%
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setPaymentAmount(remainingAmount)}
                >
                  {t('Full Amount')}
                </Button>
              </div>
            </div>

            {/* Payment Methods */}
            <div className="space-y-3">
              <h3 className="font-medium">{t('Select Payment Method')}</h3>
              {paymentMethods.length === 0 ? (
                <p className="text-sm text-muted-foreground text-center py-4">
                  {t('No payment methods available')}
                </p>
              ) : (
                <div className="space-y-2 max-h-48 overflow-y-auto">
                  {paymentMethods.map((method) => (
                    <Card
                      key={method.id}
                      className={`cursor-pointer transition-colors ${
                        selectedMethod === method.id
                          ? 'border-primary bg-primary/5'
                          : 'hover:border-primary'
                      }`}
                      onClick={() => setSelectedMethod(method.id)}
                    >
                      <CardContent className="p-3">
                        <div className="flex items-center gap-3">
                          <div className="text-primary">
                            {getMethodIcon(method.id)}
                          </div>
                          <span className="font-medium">{method.name}</span>
                          <Badge variant="secondary" className="ml-auto">
                            {t('Available')}
                          </Badge>
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </div>

            {/* Actions */}
            <div className="flex gap-3 pt-4">
              <Button variant="outline" onClick={onClose} className="flex-1">
                {t('Cancel')}
              </Button>
              <Button
                onClick={() => {
                  if (!selectedMethod) {
                    toast.error(t('Please select a payment method'));
                    return;
                  }
                  if (!paymentAmount || paymentAmount <= 0) {
                    toast.error(t('Please enter a valid payment amount'));
                    return;
                  }
                  if (paymentAmount > remainingAmount) {
                    toast.error(t('Payment amount cannot exceed balance due'));
                    return;
                  }
                  handleMethodSelect(selectedMethod);
                }}
                disabled={!selectedMethod || !paymentAmount || paymentAmount <= 0}
                className="flex-1"
              >
                {t('Pay')} {formatCurrency(paymentAmount)}
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}