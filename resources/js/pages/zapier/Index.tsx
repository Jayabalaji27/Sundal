import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Plus, Trash2, Play, Zap, ToggleLeft, ToggleRight, ArrowLeft } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

export default function ZapierIndex() {
    const { t } = useTranslation();
    const { hooks, availableEvents, flash } = usePage().props as any;
    const [isOpen, setIsOpen] = useState(false);
    const [name, setName] = useState('');
    const [url, setUrl] = useState('');
    const [event, setEvent] = useState('');

    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);

    const handleCreate = () => {
        router.post(route('zapier.store'), { name, url, event }, { onSuccess: () => { setIsOpen(false); setName(''); setUrl(''); setEvent(''); } });
    };

    return (
        <PageTemplate title={t('Zapier / Webhooks')} description={t('Trigger webhooks when things happen in your workspace. Connect to Zapier, Make, or any custom endpoint.')}
            url={route('zapier.index')} breadcrumbs={[{ title: t('Integrations'), href: `${route('settings')}#integrations` }, { title: t('Zapier') }]}
            actions={[
                { label: t('Back'), icon: <ArrowLeft className="mr-1.5 h-4 w-4" />, variant: 'outline', onClick: () => router.get(`${route('settings')}#integrations`) },
                { label: t('New Webhook'), icon: <Plus className="mr-1.5 h-4 w-4" />, variant: 'default', onClick: () => setIsOpen(true) },
            ]}>

            {/* Available events reference */}
            <div className="mb-6 rounded-xl bg-muted/40 p-4">
                <p className="text-xs font-semibold text-muted-foreground mb-2">{t('AVAILABLE TRIGGER EVENTS')}</p>
                <div className="flex flex-wrap gap-2">
                    {Object.entries(availableEvents).map(([key, label]: [string, any]) => (
                        <code key={key} className="rounded bg-background border px-2 py-0.5 text-xs">{key}</code>
                    ))}
                </div>
            </div>

            {hooks.length === 0 ? (
                <div className="flex flex-col items-center py-16 text-center">
                    <Zap className="mb-3 h-10 w-10 text-muted-foreground" />
                    <p className="text-muted-foreground">{t('No webhooks yet.')}</p>
                    <Button className="mt-4" onClick={() => setIsOpen(true)}><Plus className="mr-2 h-4 w-4" />{t('Add first webhook')}</Button>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[680px] text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">{t('Name')}</th>
                                <th className="px-4 py-3 text-left font-medium">{t('Event')}</th>
                                <th className="px-4 py-3 text-left font-medium">{t('URL')}</th>
                                <th className="px-4 py-3 text-center font-medium">{t('Status')}</th>
                                <th className="px-4 py-3 text-center font-medium">{t('Triggers')}</th>
                                <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {hooks.map((h: any) => (
                                <tr key={h.id} className="bg-background hover:bg-muted/30">
                                    <td className="px-4 py-3 font-medium">{h.name}</td>
                                    <td className="px-4 py-3"><code className="rounded bg-muted px-2 py-0.5 text-xs">{h.event}</code></td>
                                    <td className="px-4 py-3 text-muted-foreground text-xs max-w-[200px] truncate">{h.url}</td>
                                    <td className="px-4 py-3 text-center">
                                        <button onClick={() => router.patch(route('zapier.toggle', h.id))}>
                                            <Badge variant={h.is_active ? 'default' : 'secondary'}>{h.is_active ? t('Active') : t('Paused')}</Badge>
                                        </button>
                                    </td>
                                    <td className="px-4 py-3 text-center text-muted-foreground">{h.trigger_count}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button size="icon" variant="ghost" className="h-7 w-7" title={t('Send test ping')}
                                                onClick={() => router.post(route('zapier.test', h.id))}>
                                                <Play className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button size="icon" variant="ghost" className="h-7 w-7 text-destructive hover:text-destructive"
                                                onClick={() => { if (confirm(t('Delete this webhook?'))) router.delete(route('zapier.destroy', h.id)); }}>
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{t('New Webhook')}</DialogTitle></DialogHeader>
                    <div className="space-y-3 py-2">
                        <div><Label>{t('Name')} *</Label><Input value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Notify Slack on task" className="mt-1" /></div>
                        <div><Label>{t('Trigger Event')} *</Label>
                            <Select value={event} onValueChange={setEvent}>
                                <SelectTrigger className="mt-1"><SelectValue placeholder={t('Select event…')} /></SelectTrigger>
                                <SelectContent side="bottom" avoidCollisions={false}>{Object.entries(availableEvents).map(([key, label]: [string, any]) => (
                                    <SelectItem key={key} value={key}>{label}</SelectItem>
                                ))}</SelectContent>
                            </Select>
                        </div>
                        <div><Label>{t('Webhook URL')} *</Label><Input value={url} onChange={e => setUrl(e.target.value)} placeholder="https://hooks.zapier.com/…" className="mt-1" /></div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsOpen(false)}>{t('Cancel')}</Button>
                        <Button onClick={handleCreate} disabled={!name.trim() || !url.trim() || !event}>{t('Create')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PageTemplate>
    );
}
