import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, CreditCard, AlertCircle, ExternalLink } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { formatCurrency } from '@/utils/currency';

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
  const baseUrl = (typeof window !== 'undefined' && (window as any).baseUrl) || window.location.origin;
  
  // Fallback URLs for common routes
  const routes: Record<string, string> = {
    'fedapay.create-invoice-payment': `${baseUrl}/fedapay/create-invoice-payment`,
  };
  
  return routes[name] || `${baseUrl}/${name.replace('.', '/')}`;
};

interface InvoiceFedaPayFormProps {
  invoice: any;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
  fedapaySecretKey?: string;
}

export function InvoiceFedaPayForm({ 
  invoice, 
  amount, 
  onSuccess, 
  onCancel,
  fedapaySecretKey 
}: InvoiceFedaPayFormProps) {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handlePayment = async () => {
    if (!fedapaySecretKey) {
      setError(t('FedaPay not configured'));
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch(route('fedapay.create-invoice-payment'), {
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

      if (!response.ok) {
        const text = await response.text();
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();

      if (data.success && data.payment_url) {
        window.location.href = data.payment_url;
      } else {
        throw new Error(data.error || t('Payment creation failed'));
      }
    } catch (error: any) {
      console.error('FedaPay payment error:', error);
      setError(error.message || t('Payment initialization failed'));
      setLoading(false);
    }
  };

  return (
    <Card>
      <CardContent className="p-6 space-y-4">
        <div className="flex items-center gap-2 mb-4">
          <CreditCard className="h-5 w-5 text-primary" />
          <h3 className="text-lg font-semibold">{t('FedaPay Payment')}</h3>
        </div>

        {error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <div className="bg-muted p-4 rounded-lg">
          <div className="flex justify-between items-center">
            <span className="font-medium">{t('Invoice')} #{invoice.invoice_number}</span>
            <span className="text-lg font-bold">{formatCurrency(amount)}</span>
          </div>
        </div>

        <Alert>
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>
            {t('You will be redirected to FedaPay to complete your payment securely.')}
          </AlertDescription>
        </Alert>

        <div className="bg-orange-50 p-4 rounded-lg border border-orange-200">
          <h4 className="font-medium text-orange-900 mb-2">{t('Supported Payment Methods')}</h4>
          <ul className="text-sm text-orange-800 space-y-1">
            <li>• Mobile Money (MTN, Moov, Orange)</li>
            <li>• Visa/Mastercard</li>
            <li>• Bank Transfers</li>
            <li>• Digital Wallets</li>
          </ul>
        </div>

        <div className="flex gap-3 pt-4">
          <Button
            variant="outline"
            onClick={onCancel}
            disabled={loading}
            className="flex-1"
          >
            {t('Cancel')}
          </Button>
          <Button
            onClick={handlePayment}
            disabled={loading || !fedapaySecretKey}
            className="flex-1"
          >
            {loading ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                {t('Redirecting...')}
              </>
            ) : (
              <>
                <ExternalLink className="mr-2 h-4 w-4" />
                {t('Pay with FedaPay')}
              </>
            )}
          </Button>
        </div>

        <div className="text-xs text-muted-foreground text-center">
          {t('Powered by FedaPay - West Africa\'s payment gateway')}
        </div>
      </CardContent>
    </Card>
  );
}