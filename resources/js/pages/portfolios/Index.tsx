import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Plus, Edit, Trash2, FolderKanban, Eye } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';

interface Portfolio {
    id: number;
    name: string;
    description: string | null;
    color: string;
    projects_count: number;
    created_at: string;
    creator: { id: number; name: string; avatar: string | null };
}

const PRESET_COLORS = [
    '#6366f1', '#8b5cf6', '#ec4899', '#ef4444',
    '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#0ea5e9', '#3b82f6',
];

export default function PortfoliosIndex() {
    const { t } = useTranslation();
    const { portfolios, flash } = usePage().props as any;

    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [current, setCurrent] = useState<Portfolio | null>(null);
    const [mode, setMode] = useState<'create' | 'edit'>('create');

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [color, setColor] = useState('#6366f1');

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const openCreate = () => {
        setMode('create');
        setCurrent(null);
        setName('');
        setDescription('');
        setColor('#6366f1');
        setIsFormOpen(true);
    };

    const openEdit = (p: Portfolio) => {
        setMode('edit');
        setCurrent(p);
        setName(p.name);
        setDescription(p.description ?? '');
        setColor(p.color);
        setIsFormOpen(true);
    };

    const handleSubmit = () => {
        const data = { name, description, color };
        if (mode === 'create') {
            router.post(route('portfolios.store'), data, {
                onSuccess: () => setIsFormOpen(false),
            });
        } else if (current) {
            router.put(route('portfolios.update', current.id), data, {
                onSuccess: () => setIsFormOpen(false),
            });
        }
    };

    const handleDelete = () => {
        if (!current) return;
        router.delete(route('portfolios.destroy', current.id), {
            onSuccess: () => setIsDeleteOpen(false),
        });
    };

    return (
        <PageTemplate
            title={t('Portfolios')}
            description={t('Group projects into portfolios for better organization.')}
            url={route('portfolios.index')}
            breadcrumbs={[{ title: t('Portfolios'), href: route('portfolios.index') }]}
            actions={[{
                label: t('New Portfolio'),
                icon: <Plus className="mr-1.5 h-4 w-4" />,
                variant: 'default',
                onClick: openCreate,
            }]}
        >
                {/* Grid */}
                {portfolios.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed py-20 text-center">
                        <FolderKanban className="mb-3 h-10 w-10 text-muted-foreground" />
                        <p className="font-medium text-muted-foreground">{t('No portfolios yet')}</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('Create a portfolio to group your projects.')}
                        </p>
                        <Button className="mt-4" onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" /> {t('Create Portfolio')}
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {portfolios.map((p: Portfolio) => (
                            <Card key={p.id} className="group overflow-hidden transition-shadow hover:shadow-md">
                                {/* Color bar */}
                                <div className="h-1.5 w-full" style={{ backgroundColor: p.color }} />
                                <CardHeader className="pb-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <CardTitle className="text-base font-semibold leading-snug cursor-pointer hover:text-primary"
                                            onClick={() => router.get(route('portfolios.show', p.id))}>
                                            {p.name}
                                        </CardTitle>
                                        <div className="flex shrink-0 gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                            <Button size="icon" variant="ghost" className="h-7 w-7"
                                                title={t('View projects')}
                                                onClick={() => router.get(route('portfolios.show', p.id))}>
                                                <Eye className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button size="icon" variant="ghost" className="h-7 w-7"
                                                title={t('Edit')} onClick={() => openEdit(p)}>
                                                <Edit className="h-3.5 w-3.5" />
                                            </Button>
                                            <Button size="icon" variant="ghost"
                                                className="h-7 w-7 text-destructive hover:text-destructive"
                                                title={t('Delete')}
                                                onClick={() => { setCurrent(p); setIsDeleteOpen(true); }}>
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    {p.description && (
                                        <p className="mb-3 text-sm text-muted-foreground line-clamp-2">
                                            {p.description}
                                        </p>
                                    )}
                                    <div className="flex items-center justify-between">
                                        <Badge variant="secondary" className="cursor-pointer hover:bg-primary hover:text-primary-foreground transition-colors"
                                            onClick={() => router.get(route('portfolios.show', p.id))}>
                                            {p.projects_count} {p.projects_count === 1 ? t('project') : t('projects')}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {p.creator?.name}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

            {/* Create / Edit Dialog */}
            <Dialog open={isFormOpen} onOpenChange={setIsFormOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'create' ? t('Create Portfolio') : t('Edit Portfolio')}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div>
                            <Label htmlFor="p-name">{t('Name')} *</Label>
                            <Input id="p-name" value={name} onChange={e => setName(e.target.value)}
                                placeholder={t('e.g. Product Suite')} className="mt-1" />
                        </div>
                        <div>
                            <Label htmlFor="p-desc">{t('Description')}</Label>
                            <Textarea id="p-desc" value={description}
                                onChange={e => setDescription(e.target.value)}
                                placeholder={t('Optional description')} className="mt-1" rows={3} />
                        </div>
                        <div>
                            <Label>{t('Color')}</Label>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {PRESET_COLORS.map(c => (
                                    <button key={c} type="button"
                                        onClick={() => setColor(c)}
                                        className="h-7 w-7 rounded-full border-2 transition-transform hover:scale-110"
                                        style={{
                                            backgroundColor: c,
                                            borderColor: color === c ? 'white' : 'transparent',
                                            outline: color === c ? `2px solid ${c}` : 'none',
                                            outlineOffset: '2px',
                                        }} />
                                ))}
                                <input type="color" value={color} onChange={e => setColor(e.target.value)}
                                    className="h-7 w-7 cursor-pointer rounded-full border-2 border-muted p-0.5" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsFormOpen(false)}>{t('Cancel')}</Button>
                        <Button onClick={handleSubmit} disabled={!name.trim()}>
                            {mode === 'create' ? t('Create') : t('Save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <EnhancedDeleteModal
                isOpen={isDeleteOpen}
                onClose={() => setIsDeleteOpen(false)}
                onConfirm={handleDelete}
                title={t('Delete Portfolio')}
                description={t('This will remove the portfolio. All projects inside will be unlinked (not deleted).')}
                itemName={current?.name ?? ''}
            />
        </PageTemplate>
    );
}
