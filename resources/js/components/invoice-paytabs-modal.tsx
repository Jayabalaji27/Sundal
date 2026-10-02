import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Loader2, CreditCard, ExternalLink } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import axios from 'axios';

interface InvoicePayTabsModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function InvoicePayTabsModal({
  isOpen,
  onClose,
  invoice,
  amount,
}: InvoicePayTabsModalProps) {
  const { t } = useTranslation();
  const [isProcessing, setIsProcessing] = useState(false);

  const handlePayment = async () => {
    setIsProcessing(true);

    try {
      const response = await axios.post(
        route('paytabs.create-invoice-payment-from-link', invoice.payment_token),
        { amount: amount },
        {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          timeout: 30000
        }
      );

      if (response.data.success && response.data.redirect_url) {
        // Store payment info in session before redirect
        const cartId = 'invoice_' + invoice.id + '_' + amount + '_link_' + Date.now();
        sessionStorage.setItem('paytabs_invoice_payment', JSON.stringify({
          invoice_id: invoice.id,
          amount: amount,
          cartId: cartId,
          token: invoice.payment_token
        }));

        toast.success(t('Redirecting to PayTabs payment page...'));
        setTimeout(() => {
          window.location.href = response.data.redirect_url;
        }, 1000);
      } else {
        throw new Error(response.data.message || 'Payment initialization failed');
      }
    } catch (error: any) {
      console.error('PayTabs payment error:', error);

      let errorMessage = t('Payment failed. Please try again.');

      if (error.response?.status === 400) {
        errorMessage = error.response.data?.message || t('Invalid payment request. Please check your details.');
      } else if (error.response?.status === 500) {
        errorMessage = t('Server error. Please try again later.');
      } else if (error.response?.data?.message) {
        errorMessage = error.response.data.message;
      } else if (error.message) {
        errorMessage = error.message;
      } else if (error.code === 'ECONNABORTED') {
        errorMessage = t('Request timeout. Please try again.');
      }

      toast.error(errorMessage);
      setIsProcessing(false);
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
            <CreditCard className="h-5 w-5" />
            {t('PayTabs Payment')}
          </DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex items-start gap-3">
              <ExternalLink className="h-5 w-5 text-blue-600 mt-0.5" />
              <div>
                <h4 className="font-medium text-blue-900 mb-1">
                  {t('Secure Payment with PayTabs')}
                </h4>
                <p className="text-sm text-blue-700">
                  {t('You will be redirected to PayTabs secure payment page to complete your transaction.')}
                </p>
              </div>
            </div>
          </div>

          <div className="border rounded-lg p-4 bg-gray-50">
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium text-gray-600">{t('Amount')}:</span>
              <span className="text-lg font-bold text-gray-900">{formatAmount(amount)}</span>
            </div>
          </div>

          <div className="flex gap-3 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              className="flex-1"
              disabled={isProcessing}
            >
              {t('Cancel')}
            </Button>
            <Button
              type="button"
              onClick={handlePayment}
              disabled={isProcessing}
              className="flex-1"
            >
              {isProcessing ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  {t('Redirecting...')}
                </>
              ) : (
                <>
                  <ExternalLink className="mr-2 h-4 w-4" />
                  {t('Pay {{amount}}', { amount: formatAmount(amount) })}
                </>
              )}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
