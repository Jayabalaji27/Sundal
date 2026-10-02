import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CreditCard, AlertCircle, Loader2, ExternalLink } from 'lucide-react';

interface InvoiceMollieFormProps {
  invoice: {
    id: number;
    balance_due: number;
    payment_token: string;
  };
  mollieApiKey: string;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceMollieForm({ 
  invoice, 
  mollieApiKey,
  amount,
  onSuccess, 
  onCancel 
}: InvoiceMollieFormProps) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handlePayment = async () => {
    setProcessing(true);
    setError(null);

    try {
      const response = await fetch(route('mollie.create-invoice-payment'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          invoice_id: invoice.id,
          amount: amount,
        }),
      });

      const data = await response.json();

      if (data.success && data.redirect_url) {
        window.location.href = data.redirect_url;
      } else {
        throw new Error(data.error || t('Payment creation failed'));
      }
    } catch (err) {
      console.error('Mollie payment error:', err);
      setError(err instanceof Error ? err.message : t('Payment initialization failed'));
      setProcessing(false);
    }
  };

  if (!mollieApiKey) {
    return <div className="p-4 text-center text-red-500">{t('Mollie not configured')}</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 mb-4">
        <CreditCard className="h-5 w-5" />
        <h3 className="text-lg font-semibold">{t('Mollie Payment')}</h3>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <div className="bg-muted p-4 rounded-lg">
        <div className="flex justify-between items-center">
          <span className="font-medium">{t('Amount to Pay')}</span>
          <span className="text-lg font-bold">
            €{amount.toFixed(2)}
          </span>
        </div>
      </div>

      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          {t('You will be redirected to Mollie to complete your payment securely.')}
        </AlertDescription>
      </Alert>

      <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
        <h4 className="font-medium text-blue-900 mb-2">{t('Supported Payment Methods')}</h4>
        <ul className="text-sm text-blue-800 space-y-1">
          <li>• Credit Cards (Visa, MasterCard, American Express)</li>
          <li>• iDEAL (Netherlands)</li>
          <li>• SEPA Direct Debit</li>
          <li>• PayPal</li>
          <li>• Bancontact (Belgium)</li>
          <li>• SOFORT Banking</li>
        </ul>
      </div>

      <div className="flex gap-3 pt-4">
        <Button variant="outline" onClick={onCancel} className="flex-1" disabled={processing}>
          {t('Cancel')}
        </Button>
        <Button onClick={handlePayment} disabled={processing} className="flex-1">
          {processing ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {t('Redirecting...')}
            </>
          ) : (
            <>
              <ExternalLink className="mr-2 h-4 w-4" />
              {t('Pay with Mollie')}
            </>
          )}
        </Button>
      </div>

      <div className="text-xs text-muted-foreground text-center">
        {t('Powered by Mollie - Secure payment processing for Europe')}
      </div>
    </div>
  );
}