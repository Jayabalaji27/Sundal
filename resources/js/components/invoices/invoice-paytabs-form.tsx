import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Loader2, CreditCard, ExternalLink } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { formatCurrency } from '@/utils/currency';

interface InvoicePayTabsFormProps {
  invoice: any;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoicePayTabsForm({ invoice, amount, onSuccess, onCancel }: InvoicePayTabsFormProps) {
  const { t } = useTranslation();
  const [isProcessing, setIsProcessing] = useState(false);

  const handlePayment = async () => {
    setIsProcessing(true);

    try {
      const response = await fetch(route('paytabs.create-invoice-payment'), {
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

      // Debug: Get response text first
      const responseText = await response.text();
      console.log('PayTabs Response:', responseText);
      
      let data;
      try {
        // Handle concatenated JSON responses by taking the first valid JSON
        const firstJsonEnd = responseText.indexOf('}{');
        const jsonToParse = firstJsonEnd !== -1 ? responseText.substring(0, firstJsonEnd + 1) : responseText;
        data = JSON.parse(jsonToParse);
      } catch (parseError) {
        console.error('JSON Parse Error:', parseError);
        throw new Error(`Server returned invalid JSON. Response: ${responseText.substring(0, 200)}...`);
      }

      if (data.success && data.redirect_url) {
        toast.success(t('Redirecting to PayTabs payment page...'));
        setTimeout(() => {
          window.location.href = data.redirect_url;
        }, 1000);
      } else {
        throw new Error(data.message || 'Payment initialization failed');
      }
    } catch (error: any) {
      console.error('PayTabs payment error:', error);
      toast.error(error.message || t('Payment failed. Please try again.'));
      setIsProcessing(false);
    }
  };

  return (
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
          <span className="text-lg font-bold text-gray-900">
            {formatCurrency(amount)}
          </span>
        </div>
      </div>

      <div className="flex gap-3">
        <Button
          variant="outline"
          onClick={onCancel}
          disabled={isProcessing}
          className="flex-1"
        >
          {t('Cancel')}
        </Button>
        <Button
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
              {t('Pay with PayTabs')}
            </>
          )}
        </Button>
      </div>
    </div>
  );
}