import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CreditCard, AlertCircle, Loader2 } from 'lucide-react';

interface InvoiceBenefitFormProps {
  invoice: {
    id: number;
    payment_token: string;
  };
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceBenefitForm({
  invoice,
  amount,
  onSuccess,
  onCancel
}: InvoiceBenefitFormProps) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handlePayment = async () => {
    setProcessing(true);
    setError(null);

    try {
      const response = await fetch(route('benefit.create-invoice-payment'), {
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

      if (data.success) {
        if (data.redirect_url) {
          // Redirect to Benefit payment page
          window.open(data.redirect_url, '_blank');
          toast.success(t('Redirecting to Benefit payment gateway...'));
          onCancel(); // Close modal
        } else {
          throw new Error(t('Payment initialization failed'));
        }
      } else {
        throw new Error(data.error || t('Payment initialization failed'));
      }
    } catch (err) {
      console.error('Benefit payment error:', err);
      setError(err instanceof Error ? err.message : t('Payment processing failed'));
    } finally {
      setProcessing(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 mb-4">
        <CreditCard className="h-5 w-5" />
        <h3 className="text-lg font-semibold">{t('Benefit Payment')}</h3>
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
          {t('You will be redirected to Benefit to complete your payment securely.')}
        </AlertDescription>
      </Alert>

      <div className="bg-muted p-4 rounded-lg">
        <div className="flex justify-between items-center">
          <span className="font-medium">{t('Amount to Pay')}</span>
          <span className="text-lg font-bold">
            {amount.toFixed(3)} BHD
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
              {t('Pay with Benefit')}
            </>
          )}
        </Button>
      </div>

      <div className="text-xs text-muted-foreground text-center">
        {t('Powered by Benefit - Secure payment processing')}
      </div>
    </div>
  );
}