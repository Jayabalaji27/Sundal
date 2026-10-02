import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Plus, Trash2, Copy, Check, Key, AlertTriangle, ToggleLeft, ToggleRight, ArrowLeft } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

export default function ApiKeysIndex() {
    const { t } = useTranslation();
    const { apiKeys, flash } = usePage().props as any;
    const [isOpen, setIsOpen] = useState(false);
    const [name, setName] = useState('');
    const [expiresAt, setExpiresAt] = useState('');
    const [copied, setCopied] = useState(false);
    const newKey = flash?.new_api_key;

    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);

    const copyKey = (key: string) => {
        navigator.clipboard.writeText(key);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleCreate = () => {
        router.post(route('api-keys.store'), { name, expires_at: expiresAt || undefined }, {
            onSuccess: () => { setIsOpen(false); setName(''); setExpiresAt(''); }
        });
    };

    return (
        <PageTemplate title={t('BYOA — API Keys')} description={t('Generate API keys to connect external apps to your workspace.')}
            url={route('api-keys.index')} breadcrumbs={[{ title: t('Integrations'), href: `${route('settings')}#integrations` }, { title: t('API Keys') }]}
            actions={[
                { label: t('Back'), icon: <ArrowLeft className="mr-1.5 h-4 w-4" />, variant: 'outline', onClick: () => router.get(`${route('settings')}#integrations`) },
                { label: t('New Key'), icon: <Plus className="mr-1.5 h-4 w-4" />, variant: 'default', onClick: () => setIsOpen(true) },
            ]}>

            {/* New key reveal banner */}
            {newKey && (
                <div className="mb-6 rounded-xl border border-yellow-300 bg-yellow-50 p-4 dark:border-yellow-700 dark:bg-yellow-900/20">
                    <div className="flex items-start gap-3">
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-yellow-600" />
                        <div className="flex-1">
                            <p className="font-semibold text-yellow-800 dark:text-yellow-200">Save your API key now — it won't be shown again</p>
                            <div className="mt-2 flex items-center gap-2">
                                <code className="flex-1 rounded bg-yellow-100 dark:bg-yellow-900/40 px-3 py-1.5 text-sm font-mono break-all text-yellow-900 dark:text-yellow-100">
                                    {newKey}
                                </code>
                                <Button size="sm" variant="outline" onClick={() => copyKey(newKey)}>
                                    {copied ? <Check className="h-4 w-4 text-green-500" /> : <Copy className="h-4 w-4" />}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {apiKeys.length === 0 ? (
                <div className="flex flex-col items-center py-16 text-center">
                    <Key className="mb-3 h-10 w-10 text-muted-foreground" />
                    <p className="text-muted-foreground">{t('No API keys yet.')}</p>
                    <Button className="mt-4" onClick={() => setIsOpen(true)}><Plus className="mr-2 h-4 w-4" />{t('Create first key')}</Button>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">{t('Name')}</th>
                                <th className="px-4 py-3 text-left font-medium">{t('Prefix')}</th>
                                <th className="px-4 py-3 text-center font-medium">{t('Status')}</th>
                                <th className="px-4 py-3 text-left font-medium">{t('Last Used')}</th>
                                <th className="px-4 py-3 text-left font-medium">{t('Expires')}</th>
                                <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {apiKeys.map((k: any) => (
                                <tr key={k.id} className="bg-background hover:bg-muted/30">
                                    <td className="px-4 py-3 font-medium">{k.name}</td>
                                    <td className="px-4 py-3"><code className="rounded bg-muted px-2 py-0.5 text-xs">{k.key_prefix}…</code></td>
                                    <td className="px-4 py-3 text-center">
                                        <button onClick={() => router.patch(route('api-keys.toggle', k.id))}>
                                            <Badge variant={k.is_active ? 'default' : 'secondary'}>
                                                {k.is_active ? t('Active') : t('Inactive')}
                                            </Badge>
                                        </button>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground text-xs">{k.last_used_at ? new Date(k.last_used_at).toLocaleDateString() : '—'}</td>
                                    <td className="px-4 py-3 text-muted-foreground text-xs">{k.expires_at ? new Date(k.expires_at).toLocaleDateString() : t('Never')}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Button size="icon" variant="ghost" className="h-7 w-7 text-destructive hover:text-destructive"
                                            onClick={() => { if (confirm(t('Revoke this API key?'))) router.delete(route('api-keys.destroy', k.id)); }}>
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader><DialogTitle>{t('Create API Key')}</DialogTitle></DialogHeader>
                    <div className="space-y-3 py-2">
                        <div><Label>{t('Key Name')} *</Label><Input value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Zapier Integration" className="mt-1" /></div>
                        <div><Label>{t('Expires At')} <span className="text-muted-foreground text-xs">({t('optional')})</span></Label>
                            <Input type="date" value={expiresAt} onChange={e => setExpiresAt(e.target.value)} className="mt-1" /></div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsOpen(false)}>{t('Cancel')}</Button>
                        <Button onClick={handleCreate} disabled={!name.trim()}>{t('Create')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PageTemplate>
    );
}
