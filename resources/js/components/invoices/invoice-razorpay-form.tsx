import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { router } from '@inertiajs/react';

interface InvoiceRazorpayFormProps {
  invoice: {
    id: number;
    balance_due: number;
    payment_token: string;
  };
  razorpayKey: string;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceRazorpayForm({ 
  invoice, 
  razorpayKey,
  amount,
  onSuccess, 
  onCancel 
}: InvoiceRazorpayFormProps) {
  const { t } = useTranslation();
  const razorpayRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!razorpayKey || !razorpayRef.current) return;

    // Load Razorpay SDK
    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js';
    script.async = true;
    
    script.onload = () => {
      if (window.Razorpay && razorpayRef.current) {
        const options = {
          key: razorpayKey,
          amount: Math.round(amount * 100), // Amount in paise
          currency: 'INR',
          name: 'Invoice Payment',
          description: `Invoice ${invoice.id} Payment`,
          handler: function (response: any) {
            console.log('Razorpay payment success:', response);
            
            // Process payment directly using invoice payment route
            router.post(route('invoices.payment.process', invoice.payment_token), {
              payment_method: 'razorpay',
              invoice_token: invoice.payment_token,
              amount: amount,
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_order_id: response.razorpay_order_id || null,
              razorpay_signature: response.razorpay_signature || null
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
          prefill: {
            name: 'Customer',
            email: 'customer@example.com'
          },
          theme: {
            color: '#3399cc'
          },
          modal: {
            ondismiss: function() {
              console.log('Razorpay payment cancelled');
              onCancel();
            }
          }
        };

        const rzp = new window.Razorpay(options);
        
        // Create pay button
        const payButton = document.createElement('button');
        payButton.className = 'w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-md transition-colors';
        payButton.textContent = `Pay ₹${amount.toFixed(2)}`;
        payButton.onclick = () => rzp.open();
        
        razorpayRef.current.appendChild(payButton);
      }
    };
    
    script.onerror = () => {
      console.error('Failed to load Razorpay SDK');
      toast.error(t('Failed to load Razorpay. Please check your internet connection.'));
    };

    document.head.appendChild(script);

    return () => {
      if (document.head.contains(script)) {
        document.head.removeChild(script);
      }
      if (razorpayRef.current) {
        razorpayRef.current.innerHTML = '';
      }
    };
  }, [razorpayKey, invoice.id, amount]);

  if (!razorpayKey) {
    return <div className="p-4 text-center text-red-500">{t('Razorpay not configured')}</div>;
  }

  return (
    <div className="space-y-4">
      <div className="text-center p-4">
        <div className="text-4xl mb-2">💰</div>
        <h3 className="text-lg font-semibold mb-2">{t('Pay with Razorpay')}</h3>
        <p className="text-xl font-bold text-blue-600">₹{amount.toFixed(2)}</p>
      </div>
      <div ref={razorpayRef}></div>
      <div className="flex justify-center">
        <button
          type="button"
          onClick={onCancel}
          className="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
        >
          {t('Cancel')}
        </button>
      </div>
    </div>
  );
}

// Extend window object for Razorpay
declare global {
  interface Window {
    Razorpay?: any;
  }
}