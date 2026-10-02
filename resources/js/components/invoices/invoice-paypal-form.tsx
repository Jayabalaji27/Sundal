import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { router } from '@inertiajs/react';

interface InvoicePayPalFormProps {
  invoice: {
    id: number;
    balance_due: number;
    payment_token: string;
  };
  paypalClientId: string;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoicePayPalForm({ 
  invoice, 
  paypalClientId,
  amount,
  onSuccess, 
  onCancel 
}: InvoicePayPalFormProps) {
  const { t } = useTranslation();
  const paypalRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!paypalClientId || !paypalRef.current) return;

    // Load PayPal SDK
    const script = document.createElement('script');
    script.src = `https://www.paypal.com/sdk/js?client-id=${paypalClientId}&currency=USD&disable-funding=credit,card`;
    script.async = true;
    
    script.onload = () => {
      if (window.paypal && paypalRef.current) {
        window.paypal.Buttons({
          createOrder: (data: any, actions: any) => {
            const numericAmount = parseFloat(amount.toString()).toFixed(2);
            console.log('Creating PayPal order for amount:', numericAmount);
            
            return actions.order.create({
              purchase_units: [{
                amount: {
                  value: numericAmount,
                  currency_code: 'USD'
                }
              }]
            });
          },
          onApprove: (data: any, actions: any) => {
            console.log('PayPal payment approved:', data);
            
            // Process payment directly using invoice payment route
            router.post(route('invoices.payment.process', invoice.payment_token), {
              payment_method: 'paypal',
              invoice_token: invoice.payment_token,
              amount: amount,
              order_id: data.orderID,
              payment_id: data.paymentID || data.orderID
            }, {
              onSuccess: () => {
                onSuccess();
              },
              onError: (errors) => {
                console.error('Payment error:', errors);
                toast.error(errors.message || t('Payment failed'));
              }
            });
          },
          onError: (err: any) => {
            console.error('PayPal error:', err);
            if (err.message && err.message.includes('declined')) {
              toast.error(t('Card was declined. Please try a different payment method.'));
            } else {
              toast.error(t('Payment failed. Please try again.'));
            }
          },
          onCancel: () => {
            onCancel();
          }
        }).render(paypalRef.current);
      }
    };

    document.head.appendChild(script);

    return () => {
      if (document.head.contains(script)) {
        document.head.removeChild(script);
      }
    };
  }, [paypalClientId, invoice.id, amount]);

  if (!paypalClientId) {
    return <div className="p-4 text-center text-red-500">{t('PayPal not configured')}</div>;
  }

  return (
    <div className="space-y-4">
      <div ref={paypalRef}></div>
    </div>
  );
}

// Extend window object for PayPal
declare global {
  interface Window {
    paypal?: any;
  }
}