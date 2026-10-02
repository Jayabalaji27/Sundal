import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';

interface InvoiceBankPaymentModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function InvoiceBankPaymentModal({ isOpen, onClose, invoice, amount }: InvoiceBankPaymentModalProps) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);
  const [receipt, setReceipt] = useState<File | null>(null);
  const { storageSettings } = usePage().props as any;
  
  const allowedTypes = storageSettings?.allowed_file_types || 'jpg,png,webp,gif';
  const acceptAttribute = allowedTypes
      .split(',')
      .map((type) => `.${type.trim()}`)
      .join(',');

  const formatAmount = (amount: number | string) => {
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
      return window.appSettings.formatCurrency(numAmount);
    }
    return `$${numAmount.toFixed(2)}`;
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    setReceipt(file);
  };

  const handleSubmit = () => {
    setProcessing(true);

    const formData = new FormData();
    formData.append('payment_method', 'bank');
    formData.append('invoice_token', invoice.payment_token);
    formData.append('amount', String(amount));
    if (receipt) formData.append('receipt', receipt);

    router.post(route('bank.invoice.payment.link', invoice.payment_token), formData, {
      forceFormData: true,
      onSuccess: () => {
        toast.success(t('Payment request submitted successfully'));
        setReceipt(null);
        onClose();
      },
      onError: (errors) => {
        toast.error(Object.values(errors).join(', '));
        setProcessing(false);
      },
      onFinish: () => setProcessing(false),
    });
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t('Bank Transfer Payment')}</DialogTitle>
        </DialogHeader>
        
        <div className="space-y-4">
          <div className="bg-blue-50 p-4 rounded-lg">
            <h4 className="font-semibold text-blue-900 mb-2">{t('Payment Instructions')}</h4>
            <div className="text-sm text-blue-800 space-y-1">
              <p><strong>{t('Amount')}:</strong> {formatAmount(amount)}</p>
              <p><strong>{t('Invoice')}:</strong> #{invoice.invoice_number}</p>
              <p><strong>{t('Reference')}:</strong> {invoice.payment_token}</p>
            </div>
          </div>

          {/* Receipt Upload */}
          <div>
            <Label htmlFor="bank-receipt">{t('Payment Receipt')} <span className="text-muted-foreground text-xs">({t('optional')})</span></Label>
            <Input id="bank-receipt" type="file" accept={acceptAttribute} className="mt-1" onChange={handleFileChange} />
          </div>

          <div className="bg-yellow-50 p-3 rounded-lg">
            <p className="text-sm text-yellow-800">{t('Your payment request will be reviewed and confirmed by the company.')}</p>
          </div>

          <div className="flex gap-3 pt-2">
            <Button type="button" variant="outline" onClick={onClose} className="flex-1">{t('Cancel')}</Button>
            <Button onClick={handleSubmit} disabled={processing} className="flex-1">
              {processing ? t('Submitting...') : t('Submit Request')}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}