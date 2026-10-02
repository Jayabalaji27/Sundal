import { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

declare global {
  interface Window {
    Cashfree: any;
  }
}

interface InvoiceCashfreeModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function InvoiceCashfreeModal({ isOpen, onClose, invoice, amount }: InvoiceCashfreeModalProps) {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);
  const [sdkLoaded, setSdkLoaded] = useState(false);

  useEffect(() => {
    if (isOpen && !window.Cashfree) {
      const script = document.createElement('script');
      script.src = 'https://sdk.cashfree.com/js/v3/cashfree.js';
      script.async = true;
      script.onload = () => setSdkLoaded(true);
      script.onerror = () => {
        toast.error('Failed to load Cashfree SDK');
        setSdkLoaded(false);
      };
      document.head.appendChild(script);
      return () => {
        document.head.removeChild(script);
      };
    } else if (window.Cashfree) {
      setSdkLoaded(true);
    }
  }, [isOpen]);

  const verifyPayment = async (orderId: string) => {
    try {
      const response = await fetch(route('cashfree.verify-invoice-payment-from-link', invoice.payment_token), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          order_id: orderId,
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success('Payment processed successfully.');
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } else {
        toast.error(data.error || t('Payment verification failed'));
      }
    } catch (error: any) {
      toast.error(t('Payment verification failed'));
    } finally {
      setLoading(false);
    }
  };

  const handlePayment = async () => {
    setLoading(true);

    try {
      const response = await fetch(route('cashfree.create-invoice-payment-from-link', invoice.payment_token), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          invoice_token: invoice.payment_token,
          amount: amount,
        }),
      });

      const responseText = await response.text();
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        throw new Error(`Server returned invalid response`);
      }

      if (data.success && data.payment_session_id) {
        if (!window.Cashfree) {
          throw new Error('Cashfree SDK not loaded');
        }

        const cashfree = window.Cashfree({
          mode: data.mode || 'sandbox'
        });

        const checkoutOptions = {
          paymentSessionId: data.payment_session_id,
          redirectTarget: '_modal'
        };

        cashfree.checkout(checkoutOptions).then((result: any) => {
          if (result.error) {
            toast.error(result.error.message || 'Payment failed');
            setLoading(false);
          }
          if (result.paymentDetails) {
            verifyPayment(data.order_id);
          }
        }).catch((error: any) => {
          toast.error('Payment initialization failed');
          setLoading(false);
        });
      } else {
        throw new Error(data.error || 'Payment creation failed');
      }
    } catch (error: any) {
      toast.error(error.message || t('Payment failed'));
      setLoading(false);
    }
  };

  const formatAmount = (amt: number | string) => {
    const numAmount = typeof amt === 'string' ? parseFloat(amt) : amt;
    return `₹${numAmount.toFixed(2)}`;
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <span className="text-2xl">💳</span>
            {t('Cashfree Payment')}
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
              disabled={loading || !sdkLoaded}
              className="flex-1"
            >
              {loading ? (
                t('Loading...')
              ) : !sdkLoaded ? (
                t('Loading SDK...')
              ) : (
                t('Pay with Cashfree')
              )}
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
