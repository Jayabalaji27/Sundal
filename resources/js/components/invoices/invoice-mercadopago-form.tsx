import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-react';
import axios from 'axios';

interface InvoiceMercadoPagoFormProps {
  invoice: {
    id: number;
    balance_due: number;
    payment_token: string;
  };
  mercadopagoAccessToken: string;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceMercadoPagoForm({ 
  invoice, 
  mercadopagoAccessToken,
  amount,
  onSuccess, 
  onCancel 
}: InvoiceMercadoPagoFormProps) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);

  const handlePayment = async () => {
    try {
      setProcessing(true);
      
      // Create a preference and redirect to MercadoPago checkout
      const response = await axios.post(route('invoices.payment.process', { token: invoice.payment_token }), {
        payment_method: 'mercadopago',
        amount: amount
      }, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (response.data.redirect_url) {
        // Redirect to MercadoPago checkout
        window.location.href = response.data.redirect_url;
      } else {
        toast.error(t('Failed to create payment preference'));
        setProcessing(false);
      }
    } catch (error: any) {
      const errorMsg = error.response?.data?.error || error.message || t('Failed to create payment preference');
      toast.error(errorMsg);
      setProcessing(false);
    }
  };

  if (!mercadopagoAccessToken) {
    return <div className="p-4 text-center text-red-500">{t('Mercado Pago not configured')}</div>;
  }

  return (
    <div className="space-y-4">
      <div className="text-center p-4">
        <div className="text-4xl mb-2">💳</div>
        <h3 className="text-lg font-semibold mb-2">{t('Pay with Mercado Pago')}</h3>
        <p className="text-xl font-bold text-blue-600">
          ${amount.toFixed(2)}
        </p>
      </div>

      <div className="space-y-3">
        <Button
          onClick={handlePayment}
          disabled={processing}
          className="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-3 px-4 rounded-md transition-colors"
        >
          {processing ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              {t('Processing...')}
            </>
          ) : (
            t('Pay with Mercado Pago')
          )}
        </Button>
        
        <div className="text-xs text-gray-500 text-center">
          {t('You will be redirected to Mercado Pago to complete your payment')}
        </div>
      </div>

      <div className="flex justify-center">
        <button
          type="button"
          onClick={onCancel}
          disabled={processing}
          className="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
        >
          {t('Cancel')}
        </button>
      </div>
    </div>
  );
}