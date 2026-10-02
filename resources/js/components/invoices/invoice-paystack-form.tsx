import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CreditCard, AlertCircle, Loader2 } from 'lucide-react';

interface InvoicePaystackFormProps {
  invoice: {
    id: number;
    balance_due: number;
    payment_token: string;
  };
  paystackPublicKey: string;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoicePaystackForm({ 
  invoice, 
  paystackPublicKey,
  amount,
  onSuccess, 
  onCancel 
}: InvoicePaystackFormProps) {
  const { t } = useTranslation();
  const paystackRef = useRef<HTMLDivElement>(null);
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    if (!paystackPublicKey) return;

    // Load Paystack SDK
    const script = document.createElement('script');
    script.src = 'https://js.paystack.co/v1/inline.js';
    script.async = true;
    
    script.onload = () => {
      if (window.PaystackPop && paystackRef.current) {
        const handler = window.PaystackPop.setup({
          key: paystackPublicKey,
          email: 'customer@example.com', // You might want to get this from user data
          amount: Math.round(amount * 100), // Paystack expects amount in kobo (cents)
          currency: 'NGN',
          ref: 'inv_' + Date.now(),
          callback: function(response: any) {
            console.log('Paystack payment successful:', response);
            
            // Process payment using invoice payment route
            router.post(route('invoices.payment.process', invoice.payment_token), {
              payment_method: 'paystack',
              invoice_token: invoice.payment_token,
              amount: amount,
              payment_id: response.reference
            }, {
              onSuccess: () => {
                onSuccess();
              },
              onError: (errors) => {
                console.error('Payment error:', errors);
                toast.error(errors.message || t('Payment failed'));
              }
            });
          },
          onClose: function() {
            onCancel();
          }
        });

        // Handler is set up and will be used in form submit
      }
    };

    document.head.appendChild(script);

    return () => {
      if (document.head.contains(script)) {
        document.head.removeChild(script);
      }
    };
  }, [paystackPublicKey, invoice.id, amount]);

  if (!paystackPublicKey) {
    return <div className="p-4 text-center text-red-500">{t('Paystack not configured')}</div>;
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);
    
    if (window.PaystackPop) {
      const handler = window.PaystackPop.setup({
        key: paystackPublicKey,
        email: 'customer@example.com',
        amount: Math.round(amount * 100),
        currency: 'NGN',
        ref: 'inv_' + Date.now(),
        callback: function(response: any) {
          router.post(route('invoices.payment.process', invoice.payment_token), {
            payment_method: 'paystack',
            invoice_token: invoice.payment_token,
            amount: amount,
            payment_id: response.reference
          }, {
            onSuccess: () => {
              onSuccess();
            },
            onError: (errors) => {
              toast.error(errors.message || t('Payment failed'));
              setProcessing(false);
            }
          });
        },
        onClose: function() {
          setProcessing(false);
        }
      });
      handler.openIframe();
    }
  };

  return (
    <div className="space-y-4" ref={paystackRef}>
      <div className="flex items-center gap-2 mb-4">
        <CreditCard className="h-5 w-5" />
        <h3 className="text-lg font-semibold">{t('Paystack Payment')}</h3>
      </div>

      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          {t('You will be redirected to Paystack to complete your payment securely.')}
        </AlertDescription>
      </Alert>

      <div className="bg-muted p-4 rounded-lg">
        <div className="flex justify-between items-center">
          <span className="font-medium">{t('Amount to Pay')}</span>
          <span className="text-lg font-bold">
            ${amount.toFixed(2)}
          </span>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="flex gap-3 pt-4">
          <Button type="button" variant="outline" onClick={onCancel} className="flex-1" disabled={processing}>
            {t('Cancel')}
          </Button>
          <Button type="submit" disabled={processing} className="flex-1">
            {processing ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                {t('Processing...')}
              </>
            ) : (
              t('Pay with Paystack')
            )}
          </Button>
        </div>
      </form>

      <div className="text-xs text-muted-foreground text-center">
        {t('Powered by Paystack - Secure payment processing')}
      </div>
    </div>
  );
}

// Extend window object for Paystack
declare global {
  interface Window {
    PaystackPop?: any;
  }
}