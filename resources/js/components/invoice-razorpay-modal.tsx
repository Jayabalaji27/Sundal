import { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import axios from 'axios';

interface RazorpayPaymentModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function RazorpayPaymentModal({ isOpen, onClose, invoice, amount }: RazorpayPaymentModalProps) {
  const [processing, setProcessing] = useState(false);

  const formatAmount = (amount: number | string) => {
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
      return window.appSettings.formatCurrency(numAmount);
    }
    return `₹${numAmount.toFixed(2)}`;
  };

  useEffect(() => {
    if (!isOpen) return;
    
    if (!(window as any).Razorpay) {
      const script = document.createElement('script');
      script.src = 'https://checkout.razorpay.com/v1/checkout.js';
      script.async = true;
      document.body.appendChild(script);
    }
  }, [isOpen]);

  const handlePayment = async () => {
    setProcessing(true);

    try {
      const response = await axios.post(route('razorpay.create-invoice-order'), {
        invoice_token: invoice.payment_token,
        amount: amount
      });
      
      if (response.data.error) {
        toast.error(response.data.error);
        setProcessing(false);
        return;
      }
      
      const { order_id, amount: razorpayAmount, key } = response.data;
      
      if (!(window as any).Razorpay) {
        toast.error('Razorpay SDK not loaded');
        setProcessing(false);
        return;
      }
      
      const options = {
        key: key,
        amount: razorpayAmount,
        currency: 'INR',
        name: 'Invoice Payment',
        description: `Payment for Invoice ${invoice.invoice_number}`,
        order_id: order_id,
        handler: function(response: any) {
          router.post(route('razorpay.invoice.payment.link', invoice.payment_token), {
            payment_method: 'razorpay',
            invoice_token: invoice.payment_token,
            amount: amount,
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_order_id: response.razorpay_order_id
          }, {
            onSuccess: () => {
              onClose();
            },
            onError: (errors) => {
              toast.error(Object.values(errors).join(', '));
              setProcessing(false);
            }
          });
        },
        prefill: {
          name: invoice.client?.name || '',
          email: invoice.client?.email || ''
        },
        theme: {
          color: '#3B82F6'
        },
        modal: {
          ondismiss: () => {
            setProcessing(false);
          }
        }
      };
      
      const razorpay = new (window as any).Razorpay(options);
      razorpay.open();
    } catch (error: any) {
      toast.error(error.response?.data?.error || 'Failed to initialize payment');
      setProcessing(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Razorpay Payment</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="text-center p-4">
            <div className="text-4xl mb-2">💳</div>
            <h3 className="text-lg font-semibold mb-2">Pay with Razorpay</h3>
            <p className="text-xl font-bold text-blue-600">{formatAmount(amount)}</p>
          </div>

          <div className="bg-blue-50 p-4 rounded-lg">
            <p className="text-sm text-blue-800">
              You will be redirected to Razorpay to complete your payment securely.
            </p>
          </div>

          <div className="flex gap-3 pt-4">
            <Button type="button" variant="outline" onClick={onClose} className="flex-1">
              Cancel
            </Button>
            <Button onClick={handlePayment} disabled={processing} className="flex-1 bg-blue-600 hover:bg-blue-700">
              {processing ? 'Processing...' : `Pay ${formatAmount(amount)}`}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

declare global {
  interface Window {
    Razorpay?: any;
  }
}