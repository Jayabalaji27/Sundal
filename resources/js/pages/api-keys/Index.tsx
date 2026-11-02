import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { EmptyState } from '@/components/EmptyState';
import { Button, IconButton } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Plus, Trash2, Copy, Check, Key, AlertTriangle, ArrowLeft } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

export default function ApiKeysIndex() {
    const { t } = useTranslation();
    const { apiKeys, flash } = usePage().props as any;
    const [isOpen, setIsOpen] = useState(false);
    const [name, setName] = useState('');
    const [expiresAt, setExpiresAt] = useState('');
    const [creating, setCreating] = useState(false);
    const [copied, setCopied] = useState(false);
    const [keyToRevoke, setKeyToRevoke] = useState<{ id: number; name: string } | null>(null);
    const [revoking, setRevoking] = useState(false);
    const newKey = flash?.new_api_key;

    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);

    const copyKey = (key: string) => {
        navigator.clipboard.writeText(key);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleCreate = () => {
        if (!name.trim()) return;
        setCreating(true);
        router.post(route('api-keys.store'), { name, expires_at: expiresAt || undefined }, {
            onSuccess: () => { setIsOpen(false); setName(''); setExpiresAt(''); },
            onFinish: () => setCreating(false),
        });
    };

    const revoke = () => {
        if (!keyToRevoke) return;
        setRevoking(true);
        router.delete(route('api-keys.destroy', keyToRevoke.id), {
            preserveScroll: true,
            onFinish: () => { setRevoking(false); setKeyToRevoke(null); },
        });
    };

    return (
        <PageTemplate title={t('BYOA — API Keys')} description={t('Generate API keys to connect external apps to your workspace.')}
            url={route('api-keys.index')} breadcrumbs={[{ title: t('Integrations'), href: `${route('settings')}#integrations` }, { title: t('API Keys') }]}
            actions={[
                { label: t('Back'), icon: <ArrowLeft className="h-4 w-4" />, variant: 'outline', onClick: () => router.get(`${route('settings')}#integrations`) },
                { label: t('New Key'), icon: <Plus className="h-4 w-4" />, variant: 'default', onClick: () => setIsOpen(true) },
            ]}>

            {/* New key reveal banner */}
            {newKey && (
                <div role="status" className="mb-6 rounded-xl border border-ds-warning/30 bg-ds-warning-bg p-4">
                    <div className="flex items-start gap-3">
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-ds-warning" aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold text-ds-warning-fg">{t("Save your API key now — it won't be shown again")}</p>
                            <div className="mt-2 flex items-center gap-2">
                                <code className="flex-1 break-all rounded-md border border-ds-border bg-ds-surface px-3 py-2 font-mono text-sm text-ds-fg">
                                    {newKey}
                                </code>
                                <IconButton variant="outline" onClick={() => copyKey(newKey)} aria-label={copied ? t('Copied') : t('Copy API key')}>
                                    {copied ? <Check className="text-ds-success" /> : <Copy />}
                                </IconButton>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {apiKeys.length === 0 ? (
                <EmptyState
                    icon={Key}
                    title={t('No API keys yet')}
                    description={t('Create a key to let an external app read and write data in this workspace.')}
                    action={{ label: t('Create first key'), icon: <Plus className="h-4 w-4" />, onClick: () => setIsOpen(true) }}
                />
            ) : (
                <div className="overflow-hidden rounded-lg">
                    <Table className="min-w-[640px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">{t('Name')}</TableHead>
                                <TableHead scope="col">{t('Prefix')}</TableHead>
                                <TableHead scope="col">{t('Status')}</TableHead>
                                <TableHead scope="col">{t('Last Used')}</TableHead>
                                <TableHead scope="col">{t('Expires')}</TableHead>
                                <TableHead scope="col" className="text-right">{t('Actions')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {apiKeys.map((k: any) => (
                                <TableRow key={k.id}>
                                    <TableCell className="font-medium">{k.name}</TableCell>
                                    <TableCell><code className="rounded-sm bg-ds-neutral-bg px-2 py-0.5 font-mono text-xs text-ds-fg-secondary">{k.key_prefix}…</code></TableCell>
                                    <TableCell>
                                        {/* Turning a key off is reversible, so it takes effect at once. */}
                                        <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-ds-fg-secondary">
                                            <Switch
                                                checked={!!k.is_active}
                                                onCheckedChange={() => router.patch(route('api-keys.toggle', k.id), {}, { preserveScroll: true })}
                                                aria-label={t('{{name}} active', { name: k.name })}
                                            />
                                            {k.is_active ? t('Active') : t('Inactive')}
                                        </label>
                                    </TableCell>
                                    <TableCell className="text-ds-fg-secondary">{k.last_used_at ? new Date(k.last_used_at).toLocaleDateString() : '—'}</TableCell>
                                    <TableCell className="text-ds-fg-secondary">{k.expires_at ? new Date(k.expires_at).toLocaleDateString() : t('Never')}</TableCell>
                                    <TableCell className="text-right">
                                        <IconButton
                                            className="text-ds-danger-fg hover:bg-ds-danger-bg hover:text-ds-danger-fg"
                                            aria-label={t('Revoke {{name}}', { name: k.name })}
                                            onClick={() => setKeyToRevoke(k)}
                                        >
                                            <Trash2 />
                                        </IconButton>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-sm">
                    <form noValidate onSubmit={(e) => { e.preventDefault(); handleCreate(); }} className="grid gap-4">
                        <DialogHeader><DialogTitle>{t('Create API Key')}</DialogTitle></DialogHeader>
                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="api-key-name" required>{t('Key Name')}</Label>
                                <Input id="api-key-name" required value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Zapier Integration" />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="api-key-expires">{t('Expires At')} <span className="text-xs font-normal text-ds-fg-muted">({t('optional')})</span></Label>
                                <Input id="api-key-expires" type="date" value={expiresAt} onChange={e => setExpiresAt(e.target.value)} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={!name.trim()} loading={creating}>{t('Create')}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={keyToRevoke !== null}
                onOpenChange={(open) => { if (!open) setKeyToRevoke(null); }}
                title={t('Revoke {{name}}?', { name: keyToRevoke?.name ?? '' })}
                description={t('Apps using this key stop working immediately. This cannot be undone; you can create a new key instead.')}
                confirmLabel={t('Revoke key')}
                loading={revoking}
                onConfirm={revoke}
            />
        </PageTemplate>
    );
}
