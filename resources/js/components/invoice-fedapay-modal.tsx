import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/custom-toast';

interface InvoiceFedaPayModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function InvoiceFedaPayModal({ isOpen, onClose, invoice, amount }: InvoiceFedaPayModalProps) {
  const [loading, setLoading] = useState(false);

  const formatAmount = (amount) => {
    const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount);
    return `$${numericAmount.toFixed(2)}`;
  };

  const handlePayment = async () => {
    setLoading(true);

    try {
      const response = await fetch(route('fedapay.create-invoice-payment-link'), {
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
        const errorData = await response.json();
        throw new Error(errorData.error || 'Payment creation failed');
      }

      const data = await response.json();

      if (data.success && data.payment_url) {
        window.location.href = data.payment_url;
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
            FedaPay Payment
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
              {loading ? 'Redirecting...' : 'Pay with FedaPay'}
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