import { PageCrudWrapper } from '@/components/PageCrudWrapper';
import { couponsConfig } from '@/config/crud/coupons';
import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

const CopyableCode = ({ code, t }: { code: string, t: any }) => {
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    const handleReset = (e: CustomEvent) => {
      if (e.detail.code !== code) {
        setCopied(false);
      }
    };
    window.addEventListener('coupon-copied', handleReset as EventListener);
    return () => window.removeEventListener('coupon-copied', handleReset as EventListener);
  }, [code]);

  const handleCopyCode = async () => {
    try {
      await navigator.clipboard.writeText(code);
      setCopied(true);
      window.dispatchEvent(new CustomEvent('coupon-copied', { detail: { code } }));
      setTimeout(() => {
        setCopied((prev) => prev ? false : prev);
      }, 2000);
    } catch (err) {
      console.error('Failed to copy:', err);
    }
  };

  return (
    <div className="flex flex-col items-start">
      <button
        onClick={handleCopyCode}
        className="px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition-colors font-mono text-sm cursor-pointer"
        type="button"
      >
        {code}
      </button>
      {copied && (
        <span className="text-xs text-red-600 dark:text-red-400 mt-1 font-medium ml-3">
          {t('Copied!')}
        </span>
      )}
    </div>
  );
};

export default function CouponsPage() {
  const { t } = useTranslation();
  const { flash } = usePage().props as any;
  const [config, setConfig] = useState(couponsConfig);

  // Customize the config with translations and hooks
  useEffect(() => {
    setConfig({
      ...couponsConfig,
      table: {
        ...couponsConfig.table,
        columns: couponsConfig.table.columns.map(col => {
          if (col.key === 'code') {
            return {
              ...col,
              label: t(col.label),
              render: (value: string) => <CopyableCode code={value} t={t} />
            };
          }
          return {
            ...col,
            label: t(col.label)
          };
        })
      },
      form: {
        ...couponsConfig.form,
        fields: couponsConfig.form.fields.map(field => ({
          ...field,
          label: t(field.label),
          placeholder: field.placeholder ? t(field.placeholder) : undefined,
          options: field.options ? field.options.map(opt => ({
            ...opt,
            label: t(opt.label)
          })) : undefined
        }))
      },
      filters: couponsConfig.filters?.map(filter => ({
        ...filter,
        label: t(filter.label),
        options: filter.options ? filter.options.map(opt => ({
          ...opt,
          label: t(opt.label)
        })) : undefined
      })),
      hooks: {
        beforeCreate: (data: any) => {
          // Set default values
          if (!data.code_type) data.code_type = 'manual';
          if (data.status === undefined || data.status === null) data.status = true;
          // Ensure numeric fields are properly formatted
          if (data.minimum_spend) data.minimum_spend = parseFloat(data.minimum_spend);
          if (data.maximum_spend) data.maximum_spend = parseFloat(data.maximum_spend);
          if (data.discount_amount) data.discount_amount = parseFloat(data.discount_amount);
          if (data.use_limit_per_coupon) data.use_limit_per_coupon = parseInt(data.use_limit_per_coupon);
          if (data.use_limit_per_user) data.use_limit_per_user = parseInt(data.use_limit_per_user);
          return data;
        },
        beforeUpdate: (data: any) => {
          // Ensure boolean values are properly set
          if (data.status === undefined || data.status === null) data.status = true;
          // Ensure numeric fields are properly formatted
          if (data.minimum_spend) data.minimum_spend = parseFloat(data.minimum_spend);
          if (data.maximum_spend) data.maximum_spend = parseFloat(data.maximum_spend);
          if (data.discount_amount) data.discount_amount = parseFloat(data.discount_amount);
          if (data.use_limit_per_coupon) data.use_limit_per_coupon = parseInt(data.use_limit_per_coupon);
          if (data.use_limit_per_user) data.use_limit_per_user = parseInt(data.use_limit_per_user);
          return data;
        },
        afterCreate: (data: any) => {
          // Flash message from controller will be shown
        },
        afterUpdate: (data: any) => {
          // Flash message from controller will be shown
        },
        afterDelete: () => {
          // Flash message from controller will be shown
        }
      }
    });
  }, [t]);

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Coupons') }
  ];

  return (
    <PageCrudWrapper 
      config={config} 
      url="/coupons" 
      breadcrumbs={breadcrumbs}
    />
  );
}