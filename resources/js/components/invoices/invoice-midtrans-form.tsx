import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';

interface InvoiceMidtransFormProps {
  invoice: any;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceMidtransForm({ invoice, amount, onSuccess, onCancel }: InvoiceMidtransFormProps) {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);

  const handlePayment = async () => {
    setLoading(true);

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      const response = await fetch(route('midtrans.create-invoice-payment'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken || '',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          invoice_token: invoice.payment_token,
          amount: amount,
        }),
      });

      const responseText = await response.text();

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: Server returned HTML instead of JSON`);
      }

      let data;
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        throw new Error('Server returned invalid JSON response');
      }

      if (data.success) {
        if (data.snap_token) {
          // Load Midtrans Snap if not already loaded
          if (typeof (window as any).snap === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
            script.setAttribute('data-client-key', 'SB-Mid-client-your-client-key'); // Replace with actual client key
            document.head.appendChild(script);

            script.onload = () => {
              (window as any).snap.pay(data.snap_token, {
                onSuccess: function(result: any) {
                  window.location.href = route('midtrans.invoice.success') + `?order_id=${data.order_id}&invoice_token=${invoice.payment_token}`;
                },
                onPending: function(result: any) {
                  alert(t('Payment is being processed'));
                },
                onError: function(result: any) {
                  alert(t('Payment failed'));
                  setLoading(false);
                },
                onClose: function() {
                  setLoading(false);
                }
              });
            };
          } else {
            (window as any).snap.pay(data.snap_token, {
              onSuccess: function(result: any) {
                window.location.href = route('midtrans.invoice.success') + `?order_id=${data.order_id}&invoice_token=${invoice.payment_token}`;
              },
              onPending: function(result: any) {
                alert(t('Payment is being processed'));
              },
              onError: function(result: any) {
                alert(t('Payment failed'));
                setLoading(false);
              },
              onClose: function() {
                setLoading(false);
              }
            });
          }
        } else if (data.redirect_url) {
          window.location.href = data.redirect_url;
        }
      } else {
        throw new Error(data.error || t('Payment creation failed'));
      }
    } catch (error: any) {
      alert(error.message || t('Payment failed'));
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="bg-muted p-4 rounded-lg">
        <div className="text-sm text-muted-foreground font-medium">
          {t('Invoice')} #{invoice.invoice_number}
        </div>
        <div className="text-2xl font-bold text-foreground">
          {formatCurrency(amount)}
        </div>
      </div>

      <div className="bg-orange-50 p-3 rounded-lg border border-orange-200">
        <p className="text-sm text-orange-800">
          <strong>{t('Test Mode')}:</strong> {t('This will simulate Midtrans payment processing')}
        </p>
      </div>

      <div className="flex gap-3">
        <Button
          onClick={handlePayment}
          disabled={loading}
          className="flex-1"
        >
          {loading ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {t('Processing...')}
            </>
          ) : (
            t('Pay with Midtrans')
          )}
        </Button>
        <Button variant="outline" onClick={onCancel} disabled={loading}>
          {t('Cancel')}
        </Button>
      </div>
    </div>
  );
}