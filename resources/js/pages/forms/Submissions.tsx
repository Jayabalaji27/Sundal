import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { EmptyState } from '@/components/EmptyState';
import { IconButton } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Trash2, ExternalLink, Pencil, Inbox } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function FormSubmissions() {
    const { t } = useTranslation();
    const { form, submissions } = usePage().props as any;
    const [submissionToDelete, setSubmissionToDelete] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);

    const deleteSubmission = () => {
        if (submissionToDelete === null) return;
        setDeleting(true);
        router.delete(route('forms.submissions.destroy', { form: form.id, submission: submissionToDelete }), {
            preserveScroll: true,
            onFinish: () => { setDeleting(false); setSubmissionToDelete(null); },
        });
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
                { label: t('Edit Form'), icon: <Pencil className="h-4 w-4" />, variant: 'outline', onClick: () => router.get(route('forms.builder', form.id)) },
                { label: t('Open Form'), icon: <ExternalLink className="h-4 w-4" />, variant: 'outline', onClick: () => window.open(`/f/${form.token}`, '_blank') },
            ]}
        >
                {submissions.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title={t('No submissions yet')}
                        description={t('Responses appear here as soon as someone fills in the form.')}
                    />
                ) : (
                    <div className="space-y-4">
                        {submissions.map((sub: any, idx: number) => (
                            <div key={sub.id} className="rounded-lg border border-ds-border-subtle p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="text-sm font-medium text-ds-fg-muted">
                                        #{submissions.length - idx} &middot; {new Date(sub.created_at).toLocaleString()}
                                    </span>
                                    <IconButton
                                        className="-mr-2 text-ds-danger-fg hover:bg-ds-danger-bg hover:text-ds-danger-fg"
                                        aria-label={t('Delete submission #{{number}}', { number: submissions.length - idx })}
                                        onClick={() => setSubmissionToDelete(sub.id)}>
                                        <Trash2 />
                                    </IconButton>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {Object.entries(sub.data ?? {}).map(([fieldId, value]: [string, any]) => {
                                        const field = fieldById(parseInt(fieldId));
                                        if (!field) return null;
                                        return (
                                            <div key={fieldId}>
                                                <p className="text-xs font-medium text-ds-fg-muted">{field.label}</p>
                                                <p className="mt-0.5 text-sm break-words">
                                                    {field.type === 'checkbox'
                                                        ? <Badge variant={value ? 'success' : 'secondary'}>{value ? t('Yes') : t('No')}</Badge>
                                                        : (value ?? <span className="text-ds-fg-placeholder">—</span>)}
                                                </p>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

            <ConfirmDialog
                open={submissionToDelete !== null}
                onOpenChange={(open) => { if (!open) setSubmissionToDelete(null); }}
                title={t('Delete this submission?')}
                description={t('The response and its answers are removed for good. This cannot be undone.')}
                confirmLabel={t('Delete')}
                loading={deleting}
                onConfirm={deleteSubmission}
            />
        </PageTemplate>
    );
}
