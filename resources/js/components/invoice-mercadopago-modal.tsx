import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/custom-toast';
import axios from 'axios';
import { Loader2 } from 'lucide-react';

interface MercadoPagoPaymentModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function MercadoPagoPaymentModal({ isOpen, onClose, invoice, amount }: MercadoPagoPaymentModalProps) {
  const [processing, setProcessing] = useState(false);

  const formatAmount = (amount: number | string) => {
    if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
      return window.appSettings.formatCurrency(amount);
    }
    return `$${(typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2)}`;
  };

  const handlePayment = async () => {
    try {
      setProcessing(true);
      
      const response = await axios.post(route('mercadopago.invoice.payment.link', { token: invoice.payment_token }), {
        payment_method: 'mercadopago',
        amount: amount
      }, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      if (response.data.redirect_url) {
        window.location.href = response.data.redirect_url;
      } else {
        toast.error('Failed to create payment preference');
        setProcessing(false);
      }
    } catch (error: any) {
      const errorMsg = error.response?.data?.error || error.message || 'Failed to create payment preference';
      toast.error(errorMsg);
      setProcessing(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>MercadoPago Payment</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="text-center p-4">
            <div className="text-4xl mb-2">💳</div>
            <h3 className="text-lg font-semibold mb-2">Pay with MercadoPago</h3>
            <p className="text-xl font-bold text-blue-600">{formatAmount(amount)}</p>
          </div>

          <div className="bg-blue-50 p-4 rounded-lg">
            <p className="text-sm text-blue-800">
              You will be redirected to MercadoPago to complete your payment securely.
            </p>
          </div>

          <div className="flex gap-3 pt-4">
            <Button type="button" variant="outline" onClick={onClose} className="flex-1">
              Cancel
            </Button>
            <Button onClick={handlePayment} disabled={processing} className="flex-1 bg-blue-600 hover:bg-blue-700">
              {processing ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Processing...
                </>
              ) : (
                `Pay ${formatAmount(amount)}`
              )}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}