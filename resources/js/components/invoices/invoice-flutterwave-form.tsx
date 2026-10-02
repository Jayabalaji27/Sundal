import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CreditCard, AlertCircle, Loader2 } from 'lucide-react';

interface InvoiceFlutterwaveFormProps {
  invoice: {
    id: number;
    balance_due: number;
    payment_token: string;
  };
  flutterwavePublicKey: string;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceFlutterwaveForm({ 
  invoice, 
  flutterwavePublicKey,
  amount,
  onSuccess, 
  onCancel 
}: InvoiceFlutterwaveFormProps) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    if (!flutterwavePublicKey) return;

    // Load Flutterwave SDK
    const script = document.createElement('script');
    script.src = 'https://checkout.flutterwave.com/v3.js';
    script.async = true;
    document.head.appendChild(script);

    return () => {
      if (document.head.contains(script)) {
        document.head.removeChild(script);
      }
    };
  }, [flutterwavePublicKey]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);
    
    if (window.FlutterwaveCheckout) {
      const txRef = 'inv_' + Date.now();
      
      window.FlutterwaveCheckout({
        public_key: flutterwavePublicKey,
        tx_ref: txRef,
        amount: amount,
        currency: 'USD',
        payment_options: 'card,mobilemoney,ussd',
        customer: {
          email: 'customer@example.com',
          phone_number: '080****4528',
          name: 'Customer Name',
        },
        callback: function (data: any) {
          console.log('Flutterwave payment successful:', data);
          
          // Process payment using invoice payment route
          router.post(route('invoices.payment.process', invoice.payment_token), {
            payment_method: 'flutterwave',
            invoice_token: invoice.payment_token,
            amount: amount,
            payment_id: data.transaction_id,
            tx_ref: txRef
          }, {
            onSuccess: () => {
              onSuccess();
            },
            onError: (errors) => {
              console.error('Payment error:', errors);
              toast.error(errors.message || t('Payment failed'));
              setProcessing(false);
            }
          });
        },
        onclose: function() {
          setProcessing(false);
        },
        customizations: {
          title: 'Invoice Payment',
          description: `Payment for Invoice #${invoice.id}`,
          logo: '',
        },
      });
    } else {
      toast.error(t('Flutterwave SDK not loaded'));
      setProcessing(false);
    }
  };

  if (!flutterwavePublicKey) {
    return <div className="p-4 text-center text-red-500">{t('Flutterwave not configured')}</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 mb-4">
        <CreditCard className="h-5 w-5" />
        <h3 className="text-lg font-semibold">{t('Flutterwave Payment')}</h3>
      </div>

      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          {t('You will be redirected to Flutterwave to complete your payment securely.')}
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
              t('Pay with Flutterwave')
            )}
          </Button>
        </div>
      </form>

      <div className="text-xs text-muted-foreground text-center">
        {t('Powered by Flutterwave - Secure payment processing')}
      </div>
    </div>
  );
}

// Extend window object for Flutterwave
declare global {
  interface Window {
    FlutterwaveCheckout?: any;
  }
}