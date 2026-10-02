import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Coins, Info } from 'lucide-react';

interface InvoiceCoingateModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function InvoiceCoingateModal({
  isOpen,
  onClose,
  invoice,
  amount
}: InvoiceCoingateModalProps) {
  const { t } = useTranslation();
  const { company } = usePage().props as any;
  const [isProcessing, setIsProcessing] = useState(false);

  const primaryColor = company?.primary_color || '#3b82f6';

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsProcessing(true);

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('coingate.invoice.callback.from-link');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = '_token';
      csrfInput.value = csrfToken;
      form.appendChild(csrfInput);
    }

    const invoiceTokenInput = document.createElement('input');
    invoiceTokenInput.type = 'hidden';
    invoiceTokenInput.name = 'invoice_token';
    invoiceTokenInput.value = invoice.payment_token;
    form.appendChild(invoiceTokenInput);

    const amountInput = document.createElement('input');
    amountInput.type = 'hidden';
    amountInput.name = 'amount';
    amountInput.value = amount.toString();
    form.appendChild(amountInput);

    document.body.appendChild(form);
    form.submit();
  };

  const formatAmount = (amount: number | string) => {
    return `$${(typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2)}`;
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Coins className="h-5 w-5"/>
            {t('CoinGate Cryptocurrency Payment')}
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <Alert>
            <Info className="h-4 w-4" />
            <AlertDescription>
              {t('You will be redirected to CoinGate to complete your cryptocurrency payment securely.')}
            </AlertDescription>
          </Alert>

          <div className="bg-muted p-4 rounded-lg">
            <div className="flex justify-between items-center mb-2">
              <span className="text-sm font-medium">{t('Invoice')}</span>
              <span className="text-sm">#{invoice.invoice_number}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm font-medium">{t('Amount')}</span>
              <span className="text-lg font-bold">{formatAmount(amount)}</span>
            </div>
            <p className="text-xs text-muted-foreground mt-2">
              {t('Final cryptocurrency amount will be calculated at checkout based on current exchange rates')}
            </p>
          </div>

          <div className="bg-blue-50 p-3 rounded-lg border border-blue-200">
            <div className="flex items-start gap-2">
              <Info className="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
              <div className="text-xs text-blue-800">
                <p className="font-medium mb-1">{t('Payment Process:')}</p>
                <ul className="space-y-1 list-disc list-inside">
                  <li>{t('Click "Pay with Crypto" to proceed to CoinGate')}</li>
                  <li>{t('Complete payment using your selected cryptocurrency')}</li>
                  <li>{t('You will be redirected back after payment completion')}</li>
                </ul>
              </div>
            </div>
          </div>

          <div className="flex gap-3 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              className="flex-1"
              disabled={isProcessing}
            >
              {t('Cancel')}
            </Button>
            <Button
              onClick={handleSubmit}
              disabled={isProcessing}
              className="flex-1 hover:opacity-90 text-white"
            >
              {isProcessing ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  {t('Redirecting...')}
                </>
              ) : (
                <>
                  <Coins className="mr-2 h-4 w-4" />
                  {t('Pay with Crypto')}
                </>
              )}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
