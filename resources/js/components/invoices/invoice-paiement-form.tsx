import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/components/custom-toast';
import { Loader2 } from 'lucide-react';



interface Invoice {
  id: number;
  payment_token: string;
}

interface InvoicePaiementFormProps {
  invoice: Invoice;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoicePaiementForm({ invoice, amount, onSuccess, onCancel }: InvoicePaiementFormProps) {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);
  const [mobileNumber, setMobileNumber] = useState('');
  const [channel, setChannel] = useState('CARD');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!mobileNumber.trim()) {
      toast.error(t('Please enter a mobile number'));
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(route('paiement.create-invoice-payment'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          invoice_token: invoice.payment_token,
          amount: amount,
          mobile_number: mobileNumber,
          channel: channel,
        }),
      });

      const data = await response.json();

      if (data.success && data.payment_url) {
        window.location.href = data.payment_url;
      } else {
        throw new Error(data.error || t('Payment creation failed'));
      }
    } catch (error: any) {
      toast.error(error.message || t('Payment failed'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <Card>
        <CardContent className="p-4">
          <div className="space-y-4">
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-600">{t('Payment Method')}:</span>
              <span className="font-medium">Paiement Pro</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-600">{t('Amount')}:</span>
              <span className="font-bold text-lg">${amount.toFixed(2)}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="mobile_number">{t('Mobile Number')} *</Label>
          <Input
            id="mobile_number"
            type="tel"
            value={mobileNumber}
            onChange={(e) => setMobileNumber(e.target.value)}
            placeholder={t('Enter your mobile number')}
            required
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="channel">{t('Payment Channel')}</Label>
          <select
            id="channel"
            value={channel}
            onChange={(e) => setChannel(e.target.value)}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <option value="CARD">{t('Card Payment')}</option>
            <option value="MOBILE_MONEY">{t('Mobile Money')}</option>
            <option value="BANK_TRANSFER">{t('Bank Transfer')}</option>
            <option value="ALL">{t('All Methods')}</option>
          </select>
        </div>
      </div>

      <Card className="border-orange-200 bg-orange-50">
        <CardContent className="p-4">
          <div className="text-sm text-orange-800">
            <p className="font-medium mb-3">{t('Supported Payment Methods')}</p>
            <ul className="space-y-1 text-xs">
              <li>• Visa/Mastercard</li>
              <li>• Lanka QR</li>
              <li>• eZ Cash</li>
              <li>• mCash</li>
              <li>• Bank Transfers</li>
            </ul>
          </div>
        </CardContent>
      </Card>

      <div className="flex gap-3 pt-4">
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
          disabled={loading}
          className="flex-1"
        >
          {t('Cancel')}
        </Button>
        <Button
          type="submit"
          disabled={loading}
          className="flex-1"
        >
          {loading ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              {t('Processing...')}
            </>
          ) : (
            t('Pay ${{amount}}', { amount: amount.toFixed(2) })
          )}
        </Button>
      </div>
    </form>
  );
}