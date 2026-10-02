import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';

interface InvoiceAuthorizeNetModalProps {
  isOpen: boolean;
  onClose: () => void;
  invoice: any;
  amount: number;
}

export function InvoiceAuthorizeNetModal({ isOpen, onClose, invoice, amount }: InvoiceAuthorizeNetModalProps) {
  const [loading, setLoading] = useState(false);
  const [cardData, setCardData] = useState({
    card_number: '',
    expiry_month: '',
    expiry_year: '',
    cvv: '',
    cardholder_name: ''
  });

  const formatAmount = (amount: number | string) => {
    const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount);
    return `$${numericAmount.toFixed(2)}`;
  };

  const handlePayment = async () => {
    if (!cardData.card_number || !cardData.expiry_month || !cardData.expiry_year || !cardData.cvv || !cardData.cardholder_name) {
      toast.error('Please fill in all card details');
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(route('authorizenet.process-invoice-payment-link'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
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
        onClose();
        
        setTimeout(() => {
          toast.success(data.message || 'Payment successful');
        }, 100);
        
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
        throw new Error(data.message || 'Payment failed');
      }
    } catch (error: any) {
      toast.error(error.message || 'Payment failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <span className="text-2xl">💳</span>
            Authorize.Net Payment
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

          <div className="space-y-3">
            <div>
              <Label htmlFor="cardholder_name">Cardholder Name</Label>
              <Input
                id="cardholder_name"
                value={cardData.cardholder_name}
                onChange={(e) => setCardData({...cardData, cardholder_name: e.target.value})}
                placeholder="Enter cardholder name"
              />
            </div>

            <div>
              <Label htmlFor="card_number">Card Number</Label>
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
                <Label htmlFor="expiry_month">Month</Label>
                <Input
                  id="expiry_month"
                  value={cardData.expiry_month}
                  onChange={(e) => setCardData({...cardData, expiry_month: e.target.value})}
                  placeholder="MM"
                  maxLength={2}
                />
              </div>
              <div>
                <Label htmlFor="expiry_year">Year</Label>
                <Input
                  id="expiry_year"
                  value={cardData.expiry_year}
                  onChange={(e) => setCardData({...cardData, expiry_year: e.target.value})}
                  placeholder="YY"
                  maxLength={2}
                />
              </div>
              <div>
                <Label htmlFor="cvv">CVV</Label>
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
            <Button
              onClick={handlePayment}
              disabled={loading}
              className="flex-1"
            >
              {loading ? 'Processing...' : 'Pay with Authorize.Net'}
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
