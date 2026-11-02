import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { EmptyState } from '@/components/EmptyState';
import { Button, IconButton } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Plus, Trash2, Play, Zap, ArrowLeft } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

export default function ZapierIndex() {
    const { t } = useTranslation();
    const { hooks, availableEvents, flash } = usePage().props as any;
    const [isOpen, setIsOpen] = useState(false);
    const [name, setName] = useState('');
    const [url, setUrl] = useState('');
    const [event, setEvent] = useState('');
    const [creating, setCreating] = useState(false);
    const [hookToDelete, setHookToDelete] = useState<{ id: number; name: string } | null>(null);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);

    const canCreate = !!name.trim() && !!url.trim() && !!event;

    const handleCreate = () => {
        if (!canCreate) return;
        setCreating(true);
        router.post(route('zapier.store'), { name, url, event }, {
            onSuccess: () => { setIsOpen(false); setName(''); setUrl(''); setEvent(''); },
            onFinish: () => setCreating(false),
        });
    };

    const deleteHook = () => {
        if (!hookToDelete) return;
        setDeleting(true);
        router.delete(route('zapier.destroy', hookToDelete.id), {
            preserveScroll: true,
            onFinish: () => { setDeleting(false); setHookToDelete(null); },
        });
    };

    return (
        <PageTemplate title={t('Zapier / Webhooks')} description={t('Trigger webhooks when things happen in your workspace. Connect to Zapier, Make, or any custom endpoint.')}
            url={route('zapier.index')} breadcrumbs={[{ title: t('Integrations'), href: `${route('settings')}#integrations` }, { title: t('Zapier') }]}
            actions={[
                { label: t('Back'), icon: <ArrowLeft className="h-4 w-4" />, variant: 'outline', onClick: () => router.get(`${route('settings')}#integrations`) },
                { label: t('New Webhook'), icon: <Plus className="h-4 w-4" />, variant: 'default', onClick: () => setIsOpen(true) },
            ]}>

            {/* Available events reference */}
            <div className="mb-6 rounded-lg border border-ds-border-subtle bg-ds-surface-subtle p-4">
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-ds-fg-muted">{t('AVAILABLE TRIGGER EVENTS')}</p>
                <div className="flex flex-wrap gap-2">
                    {Object.entries(availableEvents).map(([key]: [string, any]) => (
                        <code key={key} className="rounded-sm border border-ds-border bg-ds-surface px-2 py-0.5 font-mono text-xs text-ds-fg-secondary">{key}</code>
                    ))}
                </div>
            </div>

            {hooks.length === 0 ? (
                <EmptyState
                    icon={Zap}
                    title={t('No webhooks yet')}
                    description={t('Add a webhook to send workspace events to Zapier, Make or your own endpoint.')}
                    action={{ label: t('Add first webhook'), icon: <Plus className="h-4 w-4" />, onClick: () => setIsOpen(true) }}
                />
            ) : (
                <div className="overflow-hidden rounded-lg">
                    <Table className="min-w-[680px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">{t('Name')}</TableHead>
                                <TableHead scope="col">{t('Event')}</TableHead>
                                <TableHead scope="col">{t('URL')}</TableHead>
                                <TableHead scope="col">{t('Status')}</TableHead>
                                <TableHead scope="col" className="text-right">{t('Triggers')}</TableHead>
                                <TableHead scope="col" className="text-right">{t('Actions')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {hooks.map((h: any) => (
                                <TableRow key={h.id}>
                                    <TableCell className="font-medium">{h.name}</TableCell>
                                    <TableCell><code className="rounded-sm bg-ds-neutral-bg px-2 py-0.5 font-mono text-xs text-ds-fg-secondary">{h.event}</code></TableCell>
                                    <TableCell className="max-w-[200px] truncate text-xs text-ds-fg-secondary" title={h.url}>{h.url}</TableCell>
                                    <TableCell>
                                        <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-ds-fg-secondary">
                                            <Switch
                                                checked={!!h.is_active}
                                                onCheckedChange={() => router.patch(route('zapier.toggle', h.id), {}, { preserveScroll: true })}
                                                aria-label={t('{{name}} active', { name: h.name })}
                                            />
                                            {h.is_active ? t('Active') : t('Paused')}
                                        </label>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums text-ds-fg-secondary">{h.trigger_count}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center justify-end gap-1">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <IconButton aria-label={t('Send test ping to {{name}}', { name: h.name })}
                                                        onClick={() => router.post(route('zapier.test', h.id), {}, { preserveScroll: true })}>
                                                        <Play />
                                                    </IconButton>
                                                </TooltipTrigger>
                                                <TooltipContent>{t('Send test ping')}</TooltipContent>
                                            </Tooltip>
                                            <IconButton
                                                className="text-ds-danger-fg hover:bg-ds-danger-bg hover:text-ds-danger-fg"
                                                aria-label={t('Delete {{name}}', { name: h.name })}
                                                onClick={() => setHookToDelete(h)}
                                            >
                                                <Trash2 />
                                            </IconButton>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-md">
                    <form noValidate onSubmit={(e) => { e.preventDefault(); handleCreate(); }} className="grid gap-4">
                        <DialogHeader><DialogTitle>{t('New Webhook')}</DialogTitle></DialogHeader>
                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="webhook-name" required>{t('Name')}</Label>
                                <Input id="webhook-name" required value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Notify Slack on task" />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="webhook-event" required>{t('Trigger Event')}</Label>
                                <Select value={event} onValueChange={setEvent}>
                                    <SelectTrigger id="webhook-event"><SelectValue placeholder={t('Select event…')} /></SelectTrigger>
                                    <SelectContent side="bottom" avoidCollisions={false}>{Object.entries(availableEvents).map(([key, label]: [string, any]) => (
                                        <SelectItem key={key} value={key}>{label}</SelectItem>
                                    ))}</SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="webhook-url" required>{t('Webhook URL')}</Label>
                                <Input id="webhook-url" type="url" required value={url} onChange={e => setUrl(e.target.value)} placeholder="https://hooks.zapier.com/…" />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={!canCreate} loading={creating}>{t('Create')}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={hookToDelete !== null}
                onOpenChange={(open) => { if (!open) setHookToDelete(null); }}
                title={t('Delete {{name}}?', { name: hookToDelete?.name ?? '' })}
                description={t('Events stop being sent to this URL right away. This cannot be undone.')}
                confirmLabel={t('Delete webhook')}
                loading={deleting}
                onConfirm={deleteHook}
            />
        </PageTemplate>
    );
}
