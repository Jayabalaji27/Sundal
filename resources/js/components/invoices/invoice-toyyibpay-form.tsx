import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CreditCard, AlertCircle, Loader2, ExternalLink } from 'lucide-react';

interface InvoiceToyyibPayFormProps {
  invoice: {
    id: number;
    balance_due: number;
    payment_token: string;
  };
  toyyibpayCategoryCode: string;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceToyyibPayForm({
  invoice,
  toyyibpayCategoryCode,
  amount,
  onSuccess,
  onCancel
}: InvoiceToyyibPayFormProps) {
  const { t } = useTranslation();
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [customerDetails, setCustomerDetails] = useState({
    name: '',
    email: '',
    phone: ''
  });
  const [errors, setErrors] = useState<{ [key: string]: string }>({});

  const validateForm = () => {
    const newErrors: { [key: string]: string } = {};

    if (!customerDetails.name.trim()) {
      newErrors.name = t('Full name is required');
    }

    if (!customerDetails.email.trim()) {
      newErrors.email = t('Email address is required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(customerDetails.email)) {
      newErrors.email = t('Please enter a valid email address');
    }

    if (!customerDetails.phone.trim()) {
      newErrors.phone = t('Phone number is required');
    } else if (customerDetails.phone.length < 10) {
      newErrors.phone = t('Please enter a valid Malaysian phone number');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const formatPhoneNumber = (value: string) => {
    const digits = value.replace(/[^0-9]/g, '');

    if (digits.startsWith('60')) {
      return digits.slice(0, 12);
    }

    if (digits.startsWith('0')) {
      return '60' + digits.slice(1, 11);
    }

    return '60' + digits.slice(0, 10);
  };

  const handlePayment = async () => {
    if (!validateForm()) {
      toast.error(t('Please fill in all required fields'));
      return;
    }

    setProcessing(true);
    setError(null);

    try {
      const response = await fetch(route('toyyibpay.create-invoice-payment'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          invoice_id: invoice.id,
          amount: amount,
          billTo: customerDetails.name.trim(),
          billEmail: customerDetails.email.trim(),
          billPhone: customerDetails.phone.trim(),
        }),
      });

      const data = await response.json();

      if (data.success && data.redirect_url) {
        window.location.href = data.redirect_url;
      } else {
        throw new Error(data.error || t('Payment creation failed'));
      }
    } catch (err) {
      console.error('ToyyibPay payment error:', err);
      setError(err instanceof Error ? err.message : t('Payment initialization failed'));
      setProcessing(false);
    }
  };

  if (!toyyibpayCategoryCode) {
    return <div className="p-4 text-center text-red-500">{t('ToyyibPay not configured')}</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 mb-4">
        <CreditCard className="h-5 w-5" />
        <h3 className="text-lg font-semibold">{t('ToyyibPay Payment')}</h3>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}
      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          {t('You will be redirected to toyyibPay to complete your payment securely via FPX (Malaysian Online Banking)')}
        </AlertDescription>
      </Alert>

      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="name">{t('Full Name')} *</Label>
          <Input
            id="name"
            value={customerDetails.name}
            onChange={(e) => {
              setCustomerDetails(prev => ({ ...prev, name: e.target.value }));
              if (errors.name) setErrors(prev => ({ ...prev, name: '' }));
            }}
            placeholder={t('Enter your full name')}
            className={errors.name ? 'border-red-500' : ''}
            required
          />
          {errors.name && (
            <p className="text-sm text-red-500">{errors.name}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="email">{t('Email Address')} *</Label>
          <Input
            id="email"
            type="email"
            value={customerDetails.email}
            onChange={(e) => {
              setCustomerDetails(prev => ({ ...prev, email: e.target.value }));
              if (errors.email) setErrors(prev => ({ ...prev, email: '' }));
            }}
            placeholder={t('Enter your email address')}
            className={errors.email ? 'border-red-500' : ''}
            required
          />
          {errors.email && (
            <p className="text-sm text-red-500">{errors.email}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="phone">{t('Phone Number')} *</Label>
          <Input
            id="phone"
            value={customerDetails.phone}
            onChange={(e) => {
              const formatted = formatPhoneNumber(e.target.value);
              setCustomerDetails(prev => ({ ...prev, phone: formatted }));
              if (errors.phone) setErrors(prev => ({ ...prev, phone: '' }));
            }}
            placeholder="60123456789"
            className={errors.phone ? 'border-red-500' : ''}
            maxLength={12}
            required
          />
          <p className="text-xs text-muted-foreground">
            {t('Malaysian format: 60123456789 (numbers only)')}
          </p>
          {errors.phone && (
            <p className="text-sm text-red-500">{errors.phone}</p>
          )}
        </div>
      </div>

      <div className="bg-muted p-4 rounded-lg">
        <div className="flex items-center gap-2 mb-2">
          <CreditCard className="h-4 w-4 text-primary" />
          <p className="text-sm font-medium">{t('Payment Method: FPX (Online Banking)')}</p>
        </div>
        <p className="text-xs text-muted-foreground">
          {t('Secure payment via Malaysian banks including Maybank, CIMB, Public Bank, RHB, and more')}
        </p>
      </div>

      <div className="bg-muted rounded-lg">
        <div className="flex justify-between items-center">
          <span className="font-medium">{t('Amount to Pay')}</span>
          <span className="text-lg font-bold">
            RM{amount.toFixed(2)}
          </span>
        </div>
      </div>

      <div className="flex gap-3 pt-2">
        <Button variant="outline" onClick={onCancel} className="flex-1" disabled={processing}>
          {t('Cancel')}
        </Button>
        <Button onClick={handlePayment} disabled={processing || !customerDetails.name || !customerDetails.email || !customerDetails.phone} className="flex-1">
          {processing ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {t('Redirecting...')}
            </>
          ) : (
            <>
              <ExternalLink className="mr-2 h-4 w-4" />
              {t('Pay with ToyyibPay')}
            </>
          )}
        </Button>
      </div>

      <div className="text-xs text-muted-foreground text-center">
        {t('Powered by ToyyibPay - Secure FPX payment processing for Malaysia')}
      </div>
    </div>
  );
}