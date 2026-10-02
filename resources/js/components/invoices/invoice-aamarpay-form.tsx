import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CreditCard, AlertCircle, Loader2 } from 'lucide-react';

// Declare global route function
declare global {
  interface Window {
    route: (name: string, params?: any) => string;
  }
}

// Fallback route function if Ziggy is not available
const route = (name: string, params?: any): string => {
  if (typeof window !== 'undefined' && window.route) {
    return window.route(name, params);
  }
  
  // Get base URL from window.baseUrl or current location
  const baseUrl = (typeof window !== 'undefined' && window.baseUrl) || window.location.origin;
  
  // Fallback URLs for common routes
  const routes: Record<string, string> = {
    'aamarpay.create-invoice-payment': `${baseUrl}/aamarpay/create-invoice-payment`,
  };
  
  return routes[name] || `${baseUrl}/${name.replace('.', '/')}`;
};

interface InvoiceAamarpayFormProps {
  invoice: {
    id: number;
    payment_token: string;
  };
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceAamarpayForm({
  invoice,
  amount,
  onSuccess,
  onCancel
}: InvoiceAamarpayFormProps) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handlePayment = async () => {
    setProcessing(true);
    setError(null);

    try {
      const routeUrl = route('aamarpay.create-invoice-payment');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const requestData = {
        invoice_token: invoice.payment_token,
        amount: amount,
      };

      const response = await fetch(routeUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken || '',
          'Accept': 'application/json',
        },
        body: JSON.stringify(requestData),
      });
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();

      if (data.success) {
        if (data.redirect_url) {
          // Redirect to Aamarpay payment page in same tab
          window.location.href = data.redirect_url;
        } else {
          throw new Error(t('Payment initialization failed'));
        }
      } else {
        throw new Error(data.error || t('Payment initialization failed'));
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : t('Payment processing failed'));
    } finally {
      setProcessing(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 mb-4">
        <CreditCard className="h-5 w-5" />
        <h3 className="text-lg font-semibold">{t('Aamarpay Payment')}</h3>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          {t('You will be redirected to Aamarpay to complete your payment securely.')}
        </AlertDescription>
      </Alert>

      <div className="bg-muted p-4 rounded-lg">
        <div className="flex justify-between items-center">
          <span className="font-medium">{t('Amount to Pay')}</span>
          <span className="text-lg font-bold">
            ৳{amount.toFixed(2)} BDT
          </span>
        </div>
      </div>

      <div className="flex gap-3 pt-2">
        <Button variant="outline" onClick={onCancel} className="flex-1" disabled={processing}>
          {t('Cancel')}
        </Button>
        <Button onClick={handlePayment} className="flex-1" disabled={processing}>
          {processing ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              {t('Processing...')}
            </>
          ) : (
            <>
              <CreditCard className="h-4 w-4 mr-2" />
              {t('Pay with Aamarpay')}
            </>
          )}
        </Button>
      </div>

      <div className="text-xs text-muted-foreground text-center">
        {t('Powered by Aamarpay - Secure payment processing')}
      </div>
      

    </div>
  );
}