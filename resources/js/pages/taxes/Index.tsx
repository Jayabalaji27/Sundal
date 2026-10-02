import { usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import TaxSettings from '@/pages/settings/components/tax-settings';
import { useTranslation } from 'react-i18next';

interface Tax {
  id: number;
  name: string;
  rate: number;
}

interface Props {
  taxes: Tax[];
}

export default function TaxIndex() {
  const { t } = useTranslation();
  const { taxes } = usePage().props as Props;

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Tax Settings') }
  ];

  return (
    <PageTemplate
      title={t('Tax Settings')}
      url="/taxes"
      breadcrumbs={breadcrumbs}
    >
      <TaxSettings taxes={taxes} />
    </PageTemplate>
  );
}