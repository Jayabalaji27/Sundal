import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

interface InvoiceOzowModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function InvoiceOzowModal({ isOpen, onClose, invoice, amount }: InvoiceOzowModalProps) {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);

  const handlePayment = async () => {
    setLoading(true);

    try {
      const payload = {
        invoice_token: invoice.payment_token,
        amount: amount,
      };

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      };

      if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
      }

      const response = await fetch(route('ozow.create-invoice-payment-from-link', invoice.payment_token), {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const text = await response.text();
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        throw new Error('Server returned non-JSON response');
      }

      const data = await response.json();

      if (data.success && data.payment_url) {
        window.location.href = data.payment_url;
      } else {
        throw new Error(data.error || t('Payment creation failed'));
      }
    } catch (error: any) {
      toast.error(error.message || t('Payment failed'));
      setLoading(false);
    }
  };

  const formatAmount = (amt: number | string) => {
    const numAmount = typeof amt === 'string' ? parseFloat(amt) : amt;
    return `$${numAmount.toFixed(2)}`;
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <span className="text-2xl">💳</span>
            {t('Ozow Payment')}
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="bg-blue-50 p-4 rounded-lg">
            <div className="text-sm text-blue-600 font-medium">
              {t('Invoice')} #{invoice.invoice_number}
            </div>
            <div className="text-2xl font-bold text-blue-900">
              {formatAmount(amount)}
            </div>
          </div>

          <div className="flex gap-3">
            <Button
              onClick={handlePayment}
              disabled={loading}
              className="flex-1"
            >
              {loading ? (
                <>
                  {t('Redirecting...')}
                </>
              ) : (
                t('Pay with Ozow')
              )}
            </Button>
            <Button variant="outline" onClick={onClose} disabled={loading}>
              {t('Cancel')}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
