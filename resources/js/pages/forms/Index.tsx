import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Plus, Edit, Trash2, ExternalLink, BarChart2, Copy, Check, ToggleLeft, ToggleRight, Eye } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { hasPermission } from '@/utils/authorization';

interface Form {
    id: number;
    title: string;
    description: string | null;
    token: string;
    is_active: boolean;
    fields_count: number;
    submissions_count: number;
    created_at: string;
    creator: { id: number; name: string };
}

export default function FormsIndex() {
    const { t } = useTranslation();
    const { forms, flash, auth } = usePage().props as any;
    const { base_url } = (usePage().props as any).globalSettings ?? {};
    const appUrl = base_url || window.location.origin;
    const permissions = auth?.permissions || [];
    const canCreate = hasPermission(permissions, 'form_create');
    const canManage = hasPermission(permissions, 'form_update');
    const canDelete = hasPermission(permissions, 'form_delete');
    const canViewSubmissions = hasPermission(permissions, 'form_view_submissions');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [current, setCurrent] = useState<Form | null>(null);
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [copied, setCopied] = useState<number | null>(null);
    // Toggled status shown right away; rolled back if the request fails
    const [activeOverrides, setActiveOverrides] = useState<Record<number, boolean>>({});
    const isActive = (form: Form) => activeOverrides[form.id] ?? form.is_active;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const handleCreate = () => {
        if (!title.trim()) return;
        router.post(route('forms.store'), { title, description }, {
            onSuccess: () => { setIsCreateOpen(false); setTitle(''); setDescription(''); },
        });
    };

    const copyLink = async (form: Form) => {
        const link = `${appUrl}/f/${form.token}`;
        try {
            await navigator.clipboard.writeText(link);
            setCopied(form.id);
            setTimeout(() => setCopied(null), 2000);
            toast.success(t('Link copied to clipboard'));
        } catch {
            toast.error(t('Could not copy to clipboard — your browser may have blocked clipboard access.'));
        }
    };

    const toggleActive = (form: Form) => {
        const previous = isActive(form);
        setActiveOverrides(prev => ({ ...prev, [form.id]: !previous }));
        router.put(route('forms.update', form.id), {
            title: form.title,
            description: form.description,
            is_active: !previous,
        }, {
            preserveScroll: true,
            onError: () => setActiveOverrides(prev => ({ ...prev, [form.id]: previous })),
        });
    };

    return (
        <PageTemplate
            title={t('Forms')}
            description={t('Build and share forms to collect responses.')}
            url={route('forms.index')}
            breadcrumbs={[{ title: t('Forms'), href: route('forms.index') }]}
            actions={canCreate ? [{
                label: t('New Form'),
                icon: <Plus className="mr-1.5 h-4 w-4" />,
                variant: 'default',
                onClick: () => setIsCreateOpen(true),
            }] : []}
        >
                {forms.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed py-20 text-center">
                        <BarChart2 className="mb-3 h-10 w-10 text-muted-foreground" />
                        <p className="font-medium text-muted-foreground">{t('No forms yet')}</p>
                        {canCreate && <Button className="mt-4" onClick={() => setIsCreateOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" /> {t('Create your first form')}
                        </Button>}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">{t('Title')}</th>
                                    <th className="px-4 py-3 text-center font-medium">{t('Fields')}</th>
                                    <th className="px-4 py-3 text-center font-medium">{t('Responses')}</th>
                                    <th className="px-4 py-3 text-center font-medium">{t('Status')}</th>
                                    <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {forms.map((f: Form) => (
                                    <tr key={f.id} className="bg-background hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            {canManage ? (
                                                <button
                                                    onClick={() => router.get(route('forms.builder', f.id))}
                                                    className="text-left hover:underline underline-offset-2">
                                                    <p className="font-medium">{f.title}</p>
                                                    {f.description && (
                                                        <p className="text-xs text-muted-foreground line-clamp-1">{f.description}</p>
                                                    )}
                                                </button>
                                            ) : (
                                                <div>
                                                    <p className="font-medium">{f.title}</p>
                                                    {f.description && (
                                                        <p className="text-xs text-muted-foreground line-clamp-1">{f.description}</p>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">{f.fields_count}</td>
                                        <td className="px-4 py-3 text-center font-medium">
                                            {f.submissions_count}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {canManage ? (
                                                <button onClick={() => toggleActive(f)} title="Toggle active">
                                                    {isActive(f)
                                                        ? <Badge variant="default" className="gap-1"><ToggleRight className="h-3 w-3" />{t('Active')}</Badge>
                                                        : <Badge variant="secondary" className="gap-1"><ToggleLeft className="h-3 w-3" />{t('Closed')}</Badge>}
                                                </button>
                                            ) : (
                                                isActive(f)
                                                    ? <Badge variant="default" className="gap-1"><ToggleRight className="h-3 w-3" />{t('Active')}</Badge>
                                                    : <Badge variant="secondary" className="gap-1"><ToggleLeft className="h-3 w-3" />{t('Closed')}</Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {canViewSubmissions && (
                                                    <Button size="sm" variant="outline" className="h-7 gap-1 px-2 text-xs"
                                                        title={t('See Responses')}
                                                        onClick={() => router.get(route('forms.submissions', f.id))}>
                                                        <Eye className="h-3.5 w-3.5" /> {t('Responses')}
                                                    </Button>
                                                )}
                                                <Button size="icon" variant="ghost" className="h-7 w-7"
                                                    title={t('Copy link')} onClick={() => copyLink(f)}>
                                                    {copied === f.id ? <Check className="h-3.5 w-3.5 text-green-500" /> : <Copy className="h-3.5 w-3.5" />}
                                                </Button>
                                                <Button size="icon" variant="ghost" className="h-7 w-7"
                                                    title={t('Open form')}
                                                    onClick={() => window.open(`/f/${f.token}`, '_blank')}>
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </Button>
                                                {canManage && (
                                                    <Button size="icon" variant="ghost" className="h-7 w-7"
                                                        title={t('Edit / Build')}
                                                        onClick={() => router.get(route('forms.builder', f.id))}>
                                                        <Edit className="h-3.5 w-3.5" />
                                                    </Button>
                                                )}
                                                {canDelete && (
                                                    <Button size="icon" variant="ghost"
                                                        className="h-7 w-7 text-destructive hover:text-destructive"
                                                        title={t('Delete')}
                                                        onClick={() => { setCurrent(f); setIsDeleteOpen(true); }}>
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{t('New Form')}</DialogTitle></DialogHeader>
                    <div className="space-y-4 py-2">
                        <div>
                            <Label>{t('Title')} *</Label>
                            <Input value={title} onChange={e => setTitle(e.target.value)}
                                placeholder={t('e.g. Contact Form')} className="mt-1" />
                        </div>
                        <div>
                            <Label>{t('Description')}</Label>
                            <Textarea value={description} onChange={e => setDescription(e.target.value)}
                                placeholder={t('Optional intro text shown on the form')} className="mt-1" rows={2} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateOpen(false)}>{t('Cancel')}</Button>
                        <Button onClick={handleCreate} disabled={!title.trim()}>{t('Create & Build')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EnhancedDeleteModal
                isOpen={isDeleteOpen}
                onClose={() => setIsDeleteOpen(false)}
                onConfirm={() => { if (current) router.delete(route('forms.destroy', current.id), { onSuccess: () => setIsDeleteOpen(false) }); }}
                entityName={t('form')}
                warningMessage={t('This will permanently delete the form and all its submissions.')}
                itemName={current?.title ?? ''}
            />
        </PageTemplate>
    );
}
