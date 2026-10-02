import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

// Declare Cashfree global
declare global {
  interface Window {
    Cashfree: any;
  }
}

interface InvoiceCashfreeFormProps {
  invoice: any;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceCashfreeForm({ invoice, amount, onSuccess, onCancel }: InvoiceCashfreeFormProps) {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);
  const [sdkLoaded, setSdkLoaded] = useState(false);

  // Load Cashfree SDK dynamically
  useEffect(() => {
    if (!window.Cashfree) {
      const script = document.createElement('script');
      script.src = 'https://sdk.cashfree.com/js/v3/cashfree.js';
      script.async = true;
      script.onload = () => {
        setSdkLoaded(true);
      };
      script.onerror = () => {
        toast.error('Failed to load Cashfree SDK');
        setSdkLoaded(false);
      };
      document.head.appendChild(script);
      
      return () => {
        document.head.removeChild(script);
      };
    } else {
      setSdkLoaded(true);
    }
  }, []);

  const verifyPayment = async (orderId: string, retryCount = 0) => {
    const maxRetries = 3;
    const delay = 2000; // 2 seconds delay

    try {
      // Add delay before verification to allow Cashfree to process
      if (retryCount === 0) {
        await new Promise(resolve => setTimeout(resolve, delay));
      }

      const response = await fetch(route('cashfree.verify-invoice-payment'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          order_id: orderId,
          invoice_token: invoice.payment_token,
        }),
      });

      const data = await response.json();

      if (data.success) {
        toast.success(t('Payment completed successfully!'));
        onSuccess();
      } else {
        // Retry if payment not yet processed and we haven't exceeded max retries
        if (retryCount < maxRetries && (data.error?.includes('not completed') || data.error?.includes('not found'))) {
          setTimeout(() => verifyPayment(orderId, retryCount + 1), delay);
          return;
        }
        toast.error(data.error || t('Payment verification failed'));
        setLoading(false);
      }
    } catch (error: any) {
      if (retryCount < maxRetries) {
        setTimeout(() => verifyPayment(orderId, retryCount + 1), delay);
        return;
      }
      toast.error(t('Payment verification failed'));
      setLoading(false);
    }
  };

  const handlePayment = async () => {
    setLoading(true);

    try {
      const response = await fetch(route('cashfree.create-invoice-payment'), {
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

      const data = await response.json();

      if (data.success && data.payment_session_id) {
        if (!window.Cashfree) {
          throw new Error('Cashfree SDK not loaded');
        }

        // Initialize Cashfree payment
        const cashfree = window.Cashfree({
          mode: data.mode || 'sandbox'
        });

        const checkoutOptions = {
          paymentSessionId: data.payment_session_id,
          redirectTarget: '_modal'
        };

        cashfree.checkout(checkoutOptions).then((result: any) => {
          if (result.error) {
            console.error('Cashfree Error:', result.error);
            toast.error(result.error.message || 'Payment failed');
            setLoading(false);
          }
          if (result.redirect) {
            console.log('Cashfree redirect');
          }
          if (result.paymentDetails) {
            console.log('Payment completed:', result.paymentDetails);
            // Verify payment
            verifyPayment(data.order_id);
          }
        }).catch((error: any) => {
          console.error('Cashfree checkout error:', error);
          toast.error('Payment initialization failed');
          setLoading(false);
        });
      } else {
        throw new Error(data.error || 'Payment creation failed');
      }
    } catch (error: any) {
      console.error('Cashfree Payment Error:', error);
      toast.error(error.message || t('Payment failed'));
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex gap-3">
        <Button
          onClick={handlePayment}
          disabled={loading || !sdkLoaded}
          className="flex-1"
        >
          {loading ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {t('Processing...')}
            </>
          ) : !sdkLoaded ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {t('Loading...')}
            </>
          ) : (
            t('Pay with Cashfree')
          )}
        </Button>
        <Button variant="outline" onClick={onCancel} disabled={loading}>
          {t('Cancel')}
        </Button>
      </div>
    </div>
  );
}