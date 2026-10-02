import { PageTemplate } from '@/components/page-template';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, DollarSign, Users, TrendingUp, Calendar } from 'lucide-react';
import { usePage, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface CouponData {
  id: number;
  name: string;
  code: string;
  type: string;
  discount_amount: number;
  minimum_spend?: number;
  maximum_spend?: number;
  use_limit_per_coupon?: number;
  use_limit_per_user?: number;
  used_count: number;
  expiry_date?: string;
  status: boolean;
  created_at: string;
  creator: {
    name: string;
    email: string;
  };
}

export default function CouponDetailsPage() {
  const { t } = useTranslation();
  const { coupon, usage_history } = usePage().props as { coupon: CouponData; usage_history: any };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Coupons'), href: route('coupons.index') },
    { title: coupon.name }
  ];



  const formatDiscount = (type: string, amount: number) => {
    return type === 'percentage' ? `${amount}%` : window.appSettings?.formatCurrency(amount) || `$${amount.toFixed(2)}`;
  };

  return (
    <PageTemplate
      title={coupon.name}
      breadcrumbs={breadcrumbs}
      actions={[
        {
          label: t('Back'),
          icon: <ArrowLeft className="h-4 w-4" />,
          variant: 'outline',
          onClick: () => router.get(route('coupons.index')),
        }
      ]}
    >
      <div className="space-y-6">
        {/* Coupon Details Card */}

        <div className="flex items-center justify-between mb-6">
          <div>
            <p className="text-gray-600">{t('Coupon Details & Usage History')}</p>
          </div>
          {coupon.status && <span className="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{t('Active')}</span>}
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('Discount Value')}</p>
                  <h3 className="mt-2 text-xl font-bold">{formatDiscount(coupon.type, coupon.discount_amount)}</h3>
                </div>
                <div className="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                  <DollarSign className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('Times Used')}</p>
                  <h3 className="mt-2 text-xl font-bold">{coupon.used_count}</h3>
                </div>
                <div className="rounded-full bg-green-100 p-3 dark:bg-green-900">
                  <TrendingUp className="h-5 w-5 text-green-600 dark:text-green-400" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('User Limit')}</p>
                  <h3 className="mt-2 text-xl font-bold">{coupon.use_limit_per_user || t('Unlimited')}</h3>
                </div>
                <div className="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                  <Users className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">{t('Expires')}</p>
                  <h3 className="mt-2 text-xl font-bold">{coupon.expiry_date ? window.appSettings?.formatDateTime(coupon.expiry_date, false) || coupon.expiry_date : t('Never')}</h3>
                </div>
                <div className="rounded-full bg-orange-100 p-3 dark:bg-orange-900">
                  <Calendar className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>


        {/* Coupon Information Card */}
        <Card>
          <CardContent className="p-6">
            <h3 className="text-lg font-semibold mb-4">{t('Coupon Information')}</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-4">
              <div>
                <label className="text-sm font-medium text-gray-600">{t('Coupon Code')}</label>
                <p className="mt-1 text-sm font-medium bg-gray-100 px-3 py-2 rounded dark:bg-gray-800">{coupon.code}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-600">{t('Minimum Spend')}</label>
                <p className="mt-1 text-sm">{window.appSettings?.formatCurrency(coupon.minimum_spend || 0) || `$${(coupon.minimum_spend || 0).toFixed(2)}`}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-600">{t('Type')}</label>
                <div className="mt-1">
                  <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20">{coupon.type === 'percentage' ? t('Percentage') : t('Flat Amount')}</span>
                </div>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-600">{t('Maximum Spend')}</label>
                <p className="mt-1 text-sm">{window.appSettings?.formatCurrency(coupon.maximum_spend || 0) || `$${(coupon.maximum_spend || 0).toFixed(2)}`}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Usage History Card */}
        <Card>
          <CardContent className="p-6">
            <h3 className="text-lg font-semibold mb-4">{t('Usage History')}</h3>
            <div className="border rounded-lg overflow-hidden">
              <Table>
                <TableHeader>
                  <TableRow className="bg-gray-50">
                    <TableHead>{t('User')}</TableHead>
                    <TableHead>{t('Email')}</TableHead>
                    <TableHead>{t('Order ID')}</TableHead>
                    <TableHead>{t('Order Amount')}</TableHead>
                    <TableHead>{t('Discount Applied')}</TableHead>
                    <TableHead>{t('Used At')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {usage_history?.data && usage_history.data.length > 0 ? (
                    usage_history.data.map((item: any, index: number) => (
                      <TableRow key={index}>
                        <TableCell>{item.user_name}</TableCell>
                        <TableCell>{item.user_email}</TableCell>
                        <TableCell>{item.order_number}</TableCell>
                        <TableCell>{window.appSettings?.formatCurrency(item.amount) || `$${item.amount.toFixed(2)}`}</TableCell>
                        <TableCell>{window.appSettings?.formatCurrency(item.discount_amount) || `$${item.discount_amount.toFixed(2)}`}</TableCell>
                        <TableCell>{window.appSettings?.formatDateTime(item.used_at) || item.used_at}</TableCell>
                      </TableRow>
                    ))
                  ) : (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center py-12 text-gray-500">
                        {t('No usage history found')}
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>

      </div>
    </PageTemplate>
  );
}
