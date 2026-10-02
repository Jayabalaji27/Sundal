import { useState, useEffect, useRef } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';

interface PayPalPaymentModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
  paypalClientId?: string;
}

export function PayPalPaymentModal({ isOpen, onClose, invoice, amount, paypalClientId }: PayPalPaymentModalProps) {
  const [processing, setProcessing] = useState(false);
  const paypalRef = useRef<HTMLDivElement>(null);
  const [paypalLoaded, setPaypalLoaded] = useState(false);

  const formatAmount = (amount: number | string) => {
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
      return window.appSettings.formatCurrency(numAmount);
    }
    return `$${numAmount.toFixed(2)}`;
  };

  useEffect(() => {
    if (!isOpen || !paypalClientId) return;

    if (paypalRef.current) {
      paypalRef.current.innerHTML = '';
    }

    if (window.paypal) {
      renderPayPalButtons();
      return;
    }

    const script = document.createElement('script');
    script.src = `https://www.paypal.com/sdk/js?client-id=${paypalClientId}&currency=USD&disable-funding=credit,card`;
    script.async = true;
    script.onload = renderPayPalButtons;
    document.head.appendChild(script);
  }, [isOpen, paypalClientId]);

  const renderPayPalButtons = () => {
    if (!window.paypal || !paypalRef.current) return;

    setPaypalLoaded(true);

    window.paypal.Buttons({
      createOrder: (data: any, actions: any) => {
        return actions.order.create({
          purchase_units: [{
            amount: {
              value: (typeof amount === 'string' ? parseFloat(amount) : amount).toFixed(2),
              currency_code: 'USD'
            }
          }]
        });
      },
      onApprove: (data: any, actions: any) => {
        setProcessing(true);
        router.post(route('paypal.invoice.payment.link', invoice.payment_token), {
          payment_method: 'paypal',
          invoice_token: invoice.payment_token,
          amount: amount,
          order_id: data.orderID,
          payment_id: data.orderID
        }, {
          onSuccess: () => {
            onClose();
          },
          onError: (errors) => {
            toast.error(Object.values(errors).join(', '));
            setProcessing(false);
          }
        });
      }
    }).render(paypalRef.current);
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>PayPal Payment</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="text-center p-4">
            <div className="text-4xl mb-2">🅿️</div>
            <h3 className="text-lg font-semibold mb-2">Pay with PayPal</h3>
            <p className="text-xl font-bold text-blue-600">{formatAmount(amount)}</p>
          </div>

          <div className="space-y-3">
            <div ref={paypalRef} className="min-h-[50px] w-full"></div>
            {!paypalLoaded && (
              <div className="text-center py-4">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p className="text-sm text-gray-600 mt-2">Loading PayPal...</p>
              </div>
            )}
            <p className="text-sm text-gray-500 text-center">
              Click the PayPal button above to complete payment
            </p>
            <div className="flex justify-center">
              <Button type="button" variant="outline" onClick={onClose}>
                Cancel
              </Button>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

declare global {
  interface Window {
    paypal?: any;
  }
}