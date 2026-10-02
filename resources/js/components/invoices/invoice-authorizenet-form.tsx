import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, CreditCard } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { formatCurrency } from '@/utils/currency';

interface InvoiceAuthorizeNetFormProps {
  invoice: any;
  amount: number;
  onSuccess: () => void;
  onCancel: () => void;
}

export function InvoiceAuthorizeNetForm({ invoice, amount, onSuccess, onCancel }: InvoiceAuthorizeNetFormProps) {
  const { t } = useTranslation();
  const [loading, setLoading] = useState(false);
  const [cardData, setCardData] = useState({
    card_number: '',
    expiry_month: '',
    expiry_year: '',
    cvv: '',
    cardholder_name: ''
  });

  const handlePayment = async () => {
    if (!cardData.card_number || !cardData.expiry_month || !cardData.expiry_year || !cardData.cvv || !cardData.cardholder_name) {
      toast.error(t('Please fill in all card details'));
      return;
    }

    setLoading(true);

    try {
      const url = route('authorizenet.process-invoice-payment');

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken || '',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          invoice_token: invoice.payment_token,
          amount: amount,
          ...cardData
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP ${response.status}`);
      }

      const data = await response.json();

      if (data.success) {
        toast.success(data.message || t('Payment successful'));
        onSuccess();
        
        if (data.redirect_url) {
          setTimeout(() => {
            window.location.href = data.redirect_url;
          }, 1500);
        } else {
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        }
      } else {
        throw new Error(data.message || t('Payment failed'));
      }
    } catch (error: any) {
      toast.error(error.message || t('Payment failed'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="border rounded-lg p-4">
        <div className="flex items-center gap-2 mb-4">
          <CreditCard className="h-5 w-5" />
          <span className="font-semibold text-lg">{t('AuthorizeNet Payment')}</span>
        </div>
        
        <div className="flex justify-between items-center mb-4">
          <div>
            <div className="font-medium">{t('Total Amount')}</div>
            <div className="text-sm text-muted-foreground">
              {t('Invoice')}: {invoice.invoice_number}
            </div>
          </div>
          <div className="text-2xl font-bold">
            {formatCurrency(amount.toFixed(2))}
          </div>
        </div>
      </div>

      <div className="space-y-3">
        <div>
          <Label htmlFor="cardholder_name">{t('Cardholder Name')}</Label>
          <Input
            id="cardholder_name"
            value={cardData.cardholder_name}
            onChange={(e) => setCardData({...cardData, cardholder_name: e.target.value})}
            placeholder={t('Enter cardholder name')}
          />
        </div>

        <div>
          <Label htmlFor="card_number">{t('Card Number')}</Label>
          <Input
            id="card_number"
            value={cardData.card_number}
            onChange={(e) => setCardData({...cardData, card_number: e.target.value.replace(/\s/g, '')})}
            placeholder="1234 5678 9012 3456"
            maxLength={19}
          />
        </div>

        <div className="grid grid-cols-3 gap-2">
          <div>
            <Label htmlFor="expiry_month">{t('Month')}</Label>
            <Input
              id="expiry_month"
              value={cardData.expiry_month}
              onChange={(e) => setCardData({...cardData, expiry_month: e.target.value})}
              placeholder="MM"
              maxLength={2}
            />
          </div>
          <div>
            <Label htmlFor="expiry_year">{t('Year')}</Label>
            <Input
              id="expiry_year"
              value={cardData.expiry_year}
              onChange={(e) => setCardData({...cardData, expiry_year: e.target.value})}
              placeholder="YY"
              maxLength={2}
            />
          </div>
          <div>
            <Label htmlFor="cvv">{t('CVV')}</Label>
            <Input
              id="cvv"
              value={cardData.cvv}
              onChange={(e) => setCardData({...cardData, cvv: e.target.value})}
              placeholder="123"
              maxLength={4}
            />
          </div>
        </div>
      </div>

      <div className="flex gap-3">
        <Button variant="outline" onClick={onCancel} disabled={loading}>
          {t('Cancel')}
        </Button>
        <Button
          onClick={handlePayment}
          disabled={loading}
          className="flex-1"
        >
          {loading ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              {t('Processing...')}
            </>
          ) : (
            <>
              <CreditCard className="mr-2 h-4 w-4" />
              {t('Pay with AuthorizeNet')}
            </>
          )}
        </Button>
      </div>
      
      <div className="text-center text-xs text-muted-foreground space-y-1">
        <div className="flex items-center justify-center gap-1">
          <span>✓</span>
          <span>{t('Powered by AuthorizeNet - Secure payment processing')}</span>
        </div>
        <div>{t('Supported currencies')}: USD, CAD, CHF, DKK, EUR, GBP, NOK, PLN, SEK, AUD, NZD</div>
        <div>{t('Supported countries')}: US, CA, GB, AU</div>
      </div>
    </div>
  );
}