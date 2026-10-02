import React from 'react';
import { useForm, router } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { PageForm } from './components/PageForm';
import { toast } from '@/components/custom-toast';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { ArrowLeft } from 'lucide-react';

declare const route: any;

interface EditCustomPageProps {
  page: {
    id: number;
    title: string;
    slug: string;
    content: string;
    meta_title?: string;
    meta_description?: string;
    is_active: boolean;
    sort_order: number;
  };
}

export default function EditCustomPage({ page }: EditCustomPageProps) {
  const { t } = useTranslation();

  const { data, setData, put, processing, errors, clearErrors, setError } = useForm({
    title: page.title || '',
    content: page.content || '',
    meta_title: page.meta_title || '',
    meta_description: page.meta_description || '',
    is_active: !!page.is_active,
    sort_order: page.sort_order || 0
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

    put(route('landing-page.custom-pages.update', page.id), {
      onSuccess: (response: any) => {
        if (response.props.flash?.error) {
          toast.error(response.props.flash.error);
          return;
        }
        toast.success(t('Page updated successfully!'));
      },
      onError: () => {
        toast.error(t('Failed to update page. Please check the form.'));
      }
    });
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Landing Page'), href: route('landing-page.settings') },
    { title: t('Custom Pages'), href: route('landing-page.custom-pages.index') },
    { title: t('Edit') }
  ];

  return (
    <PageTemplate
      title={t('Edit Custom Page')}
      url={`/landing-page/custom-pages/${page.id}/edit`}
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
          <CardDescription>{t('Update your custom page content and settings')}</CardDescription>
        </CardHeader>
        <CardContent>
          <PageForm
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            onSubmit={handleSubmit}
            onCancel={() => router.get(route('landing-page.custom-pages.index'))}
            isEditing={true}
            slug={page.slug}
          />
        </CardContent>
      </Card>
    </PageTemplate>
  );
}
