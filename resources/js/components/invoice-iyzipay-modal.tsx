import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/custom-toast';

interface IyzipayInvoiceModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function IyzipayInvoiceModal({ isOpen, onClose, invoice, amount }: IyzipayInvoiceModalProps) {
  const [loading, setLoading] = useState(false);

  const formatAmount = (amount: number | string) => {
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    return `$${numAmount.toFixed(2)}`;
  };

  const handlePayment = async () => {
    setLoading(true);

    try {
      const response = await fetch(route('iyzipay.invoice.payment.link', invoice.payment_token), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          amount: amount,
        }),
      });

      const data = await response.json();

      if (data.success && data.redirect_url) {
        window.location.href = data.redirect_url;
      } else {
        throw new Error(data.error || 'Payment creation failed');
      }
    } catch (error: any) {
      toast.error(error.message || 'Payment failed');
      setLoading(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <span className="text-2xl">💳</span>
            Iyzipay Payment
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="bg-blue-50 p-4 rounded-lg">
            <div className="text-sm text-blue-600 font-medium">
              Invoice #{invoice.invoice_number}
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
              {loading ? 'Redirecting...' : 'Pay with Iyzipay'}
            </Button>
            <Button variant="outline" onClick={onClose} disabled={loading}>
              Cancel
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}