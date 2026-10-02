import { useRef, useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Eye, Calendar, User, Paperclip, Download, Trash2, Upload, FileText } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';

function formatBytes(bytes: number) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

export default function KbArticle() {
    const { t } = useTranslation();
    const { article, flash, auth } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const canManageAttachments = hasPermission(permissions, 'kb_manage_attachments');
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);

    const handleUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);
        const formData = new FormData();
        formData.append('file', file);
        router.post(route('kb.attachments.upload', article.id), formData as any, {
            forceFormData: true,
            onSuccess: () => toast.success(t('File attached.')),
            onError: (errors) => toast.error((errors as any).file || t('Failed to attach file.')),
            onFinish: () => { setUploading(false); if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    return (
        <PageTemplate title={article.title} description={article.category?.name || ''}
            url={route('kb.articles.show', article.id)}
            breadcrumbs={[
                { title: t('Knowledge Base'), href: route('kb.index') },
                { title: article.title, href: route('kb.articles.show', article.id) },
            ]}>
            <div className="mx-auto max-w-3xl">
                {/* Meta */}
                <div className="mb-6 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <div className="flex items-center gap-1"><User className="h-4 w-4" /> {article.creator?.name}</div>
                    <div className="flex items-center gap-1"><Calendar className="h-4 w-4" /> {new Date(article.updated_at).toLocaleDateString()}</div>
                    <div className="flex items-center gap-1"><Eye className="h-4 w-4" /> {article.views} {t('views')}</div>
                    <Badge variant={article.is_published ? 'default' : 'secondary'}>{article.is_published ? t('Published') : t('Draft')}</Badge>
                </div>

                {/* Content */}
                <div className="rounded-xl border bg-background p-6 text-sm text-foreground whitespace-pre-wrap leading-relaxed">
                    {article.content}
                </div>

                {/* Attachments */}
                <div className="mt-6">
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="flex items-center gap-2 font-semibold">
                            <Paperclip className="h-4 w-4" />
                            {t('Attachments')} ({article.attachments?.length ?? 0})
                        </h3>
                        {canManageAttachments && (
                            <div>
                                <input ref={fileRef} type="file" className="hidden" onChange={handleUpload} />
                                <Button type="button" size="sm" variant="outline" disabled={uploading}
                                    onClick={() => fileRef.current?.click()}>
                                    <Upload className="mr-1.5 h-3.5 w-3.5" />
                                    {uploading ? t('Uploading…') : t('Attach File')}
                                </Button>
                            </div>
                        )}
                    </div>

                    {article.attachments?.length === 0 && (
                        <div className="rounded-xl border-2 border-dashed py-8 text-center text-sm text-muted-foreground">
                            {canManageAttachments
                                ? t('No attachments yet. Click "Attach File" to upload.')
                                : t('No attachments yet.')}
                        </div>
                    )}

                    {article.attachments?.length > 0 && (
                        <div className="space-y-2">
                            {article.attachments.map((att: any) => (
                                <div key={att.id} className="flex items-center gap-3 rounded-lg border bg-muted/30 p-3">
                                    <FileText className="h-5 w-5 shrink-0 text-muted-foreground" />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium truncate">{att.name}</p>
                                        <p className="text-xs text-muted-foreground">{formatBytes(att.size)}</p>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button type="button" size="icon" variant="ghost" className="h-7 w-7"
                                            title={t('Download')}
                                            onClick={() => window.open(att.download_url, '_blank')}>
                                            <Download className="h-3.5 w-3.5" />
                                        </Button>
                                        {canManageAttachments && (
                                            <Button type="button" size="icon" variant="ghost"
                                                className="h-7 w-7 text-destructive hover:text-destructive"
                                                title={t('Remove')}
                                                onClick={() => { if (confirm(t('Remove this attachment?'))) router.delete(route('kb.attachments.destroy', att.id)); }}>
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </PageTemplate>
    );
}

