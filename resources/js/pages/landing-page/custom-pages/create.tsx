import React from 'react';
import { useForm, router } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { PageForm } from './components/PageForm';
import { toast } from '@/components/custom-toast';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { ArrowLeft } from 'lucide-react';

declare const route: any;

export default function CreateCustomPage() {
  const { t } = useTranslation();

  const { data, setData, post, processing, errors, clearErrors, setError } = useForm({
    title: '',
    content: '',
    meta_title: '',
    meta_description: '',
    is_active: true,
    sort_order: 0
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    clearErrors();

    let hasError = false;

    if (!data.title.trim()) {
      setError('title', t('Title is required'));
      hasError = true;
    }

    if (!data.content.trim() || data.content === '<p></p>' || data.content === '<p><br></p>') {
      setError('content', t('Content is required'));
      hasError = true;
    }

    if (hasError) return;

    post(route('landing-page.custom-pages.store'), {
      onSuccess: (page: any) => {
        if (page.props.flash?.error) {
          toast.error(page.props.flash.error);
          return;
        }
        toast.success(t('Page created successfully!'));
      },
      onError: () => {
        // toast.error(t('Failed to create page. Please check the form.'));
      }
    });
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Landing Page'), href: route('landing-page.settings') },
    { title: t('Custom Pages'), href: route('landing-page.custom-pages.index') },
    { title: t('Create') }
  ];

  return (
    <PageTemplate
      title={t('Create Custom Page')}
      url="/landing-page/custom-pages/create"
      breadcrumbs={breadcrumbs}
      actions={[
        {
          label: t('Back'),
          icon: <ArrowLeft className="h-4 w-4 mr-2" />,
          variant: 'outline',
          onClick: () => router.get(route('landing-page.custom-pages.index'))
        }
      ]}
    >
      <Card>
          <CardHeader>
            <CardTitle className="text-lg">{t('Page Information')}</CardTitle>
            <CardDescription>{t('Create a new custom page for your landing site')}</CardDescription>
          </CardHeader>
          <CardContent>
            <PageForm
              data={data}
              setData={setData}
              errors={errors}
              processing={processing}
              onSubmit={handleSubmit}
              onCancel={() => router.get(route('landing-page.custom-pages.index'))}
              isEditing={false}
            />
          </CardContent>
        </Card>
    </PageTemplate>
  );
}
