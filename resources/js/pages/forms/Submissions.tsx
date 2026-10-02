import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Trash2, ExternalLink, Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function FormSubmissions() {
    const { t } = useTranslation();
    const { form, submissions } = usePage().props as any;

    const deleteSubmission = (submissionId: number) => {
        if (!confirm(t('Delete this submission?'))) return;
        router.delete(route('forms.submissions.destroy', { form: form.id, submission: submissionId }));
    };

    const fieldById = (id: number) => form.fields.find((f: any) => f.id === id);

    return (
        <PageTemplate
            title={`${t('Submissions')} — ${form.title}`}
            description={`${submissions.length} ${t('response(s)')}`}
            url={route('forms.submissions', form.id)}
            breadcrumbs={[
                { title: t('Forms'), href: route('forms.index') },
                { title: form.title, href: route('forms.builder', form.id) },
                { title: t('Submissions'), href: route('forms.submissions', form.id) },
            ]}
            actions={[
                { label: t('Edit Form'), icon: <Pencil className="mr-1.5 h-4 w-4" />, variant: 'outline', onClick: () => router.get(route('forms.builder', form.id)) },
                { label: t('Open Form'), icon: <ExternalLink className="mr-1.5 h-4 w-4" />, variant: 'outline', onClick: () => window.open(`/f/${form.token}`, '_blank') },
            ]}
        >
                {submissions.length === 0 ? (
                    <div className="rounded-xl border-2 border-dashed py-16 text-center text-muted-foreground">
                        {t('No submissions yet.')}
                    </div>
                ) : (
                    <div className="space-y-4">
                        {submissions.map((sub: any, idx: number) => (
                            <div key={sub.id} className="rounded-xl border bg-card p-4 shadow-sm">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="text-sm font-medium text-muted-foreground">
                                        #{submissions.length - idx} &middot; {new Date(sub.created_at).toLocaleString()}
                                    </span>
                                    <Button size="icon" variant="ghost"
                                        className="h-7 w-7 text-destructive hover:text-destructive"
                                        onClick={() => deleteSubmission(sub.id)}>
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {Object.entries(sub.data ?? {}).map(([fieldId, value]: [string, any]) => {
                                        const field = fieldById(parseInt(fieldId));
                                        if (!field) return null;
                                        return (
                                            <div key={fieldId}>
                                                <p className="text-xs font-medium text-muted-foreground">{field.label}</p>
                                                <p className="mt-0.5 text-sm break-words">
                                                    {field.type === 'checkbox'
                                                        ? <Badge variant={value ? 'default' : 'secondary'}>{value ? 'Yes' : 'No'}</Badge>
                                                        : (value ?? <span className="italic text-muted-foreground">—</span>)}
                                                </p>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
        </PageTemplate>
    );
}
