import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Search, Plus, BookOpen, Pencil, Trash2, Eye, FileText, ChevronRight } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { hasPermission } from '@/utils/authorization';

export default function KnowledgeBaseIndex() {
    const { t } = useTranslation();
    const { categories, articles, search: initSearch, flash, auth } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const canManageCategories = hasPermission(permissions, 'kb_manage_categories');
    const canCreateArticles = hasPermission(permissions, 'kb_create');
    const canUpdateArticles = hasPermission(permissions, 'kb_update');
    const canDeleteArticles = hasPermission(permissions, 'kb_delete');
    const canManageAttachments = hasPermission(permissions, 'kb_manage_attachments');

    const [search, setSearch] = useState(initSearch || '');
    const [catModal, setCatModal] = useState(false);
    const [artModal, setArtModal] = useState(false);
    const [deleteModal, setDeleteModal] = useState(false);
    const [deleteType, setDeleteType] = useState<'category'|'article'>('article');
    const [current, setCurrent] = useState<any>(null);
    const [mode, setMode] = useState<'create'|'edit'>('create');

    // category form
    const [catName, setCatName] = useState('');
    const [catDesc, setCatDesc] = useState('');

    // article form
    const [artTitle, setArtTitle] = useState('');
    const [artContent, setArtContent] = useState('');
    const [artCatId, setArtCatId] = useState('');
    const [artPublished, setArtPublished] = useState(false);
    const [artFile, setArtFile] = useState<File | null>(null);

    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);

    const openCat = (cat?: any) => {
        setMode(cat ? 'edit' : 'create'); setCurrent(cat || null);
        setCatName(cat?.name || ''); setCatDesc(cat?.description || ''); setCatModal(true);
    };
    const openArt = (art?: any) => {
        // Fix: only set 'edit' mode if the article has an actual id (not just a prefill object)
        setMode(art?.id ? 'edit' : 'create'); setCurrent(art?.id ? art : null);
        setArtTitle(art?.title || ''); setArtContent(art?.content || '');
        setArtCatId(String(art?.kb_category_id || categories[0]?.id || '')); setArtPublished(art?.is_published ?? false);
        setArtFile(null);
        setArtModal(true);
    };

    const saveCat = () => {
        const data = { name: catName, description: catDesc };
        mode === 'create' ? router.post(route('kb.categories.store'), data, { onSuccess: () => setCatModal(false), onError: (e) => toast.error(Object.values(e)[0] as string) })
            : router.put(route('kb.categories.update', current.id), data, { onSuccess: () => setCatModal(false), onError: (e) => toast.error(Object.values(e)[0] as string) });
    };
    const saveArt = () => {
        if (mode === 'create') {
            router.post(route('kb.articles.store'), {
                title: artTitle, content: artContent, kb_category_id: artCatId, is_published: artPublished, file: artFile,
            }, {
                forceFormData: true,
                onSuccess: () => setArtModal(false),
                onError: (e) => toast.error(Object.values(e)[0] as string),
            });
        } else {
            const data = { title: artTitle, content: artContent, kb_category_id: artCatId, is_published: artPublished };
            router.put(route('kb.articles.update', current.id), data, { onSuccess: () => setArtModal(false), onError: (e) => toast.error(Object.values(e)[0] as string) });
        }
    };
    const confirmDelete = () => {
        if (deleteType === 'category') router.delete(route('kb.categories.destroy', current.id), { onSuccess: () => setDeleteModal(false) });
        else router.delete(route('kb.articles.destroy', current.id), { onSuccess: () => setDeleteModal(false) });
    };

    return (
        <PageTemplate title={t('Knowledge Base')} description={t('Create articles and FAQs for your team.')}
            url={route('kb.index')} breadcrumbs={[{ title: t('Knowledge Base'), href: route('kb.index') }]}
            actions={[
                ...(canManageCategories ? [{ label: t('New Category'), icon: <Plus className="mr-1.5 h-4 w-4" />, variant: 'outline' as const, onClick: () => openCat() }] : []),
                ...(canCreateArticles ? [{ label: t('New Article'), icon: <Plus className="mr-1.5 h-4 w-4" />, variant: 'default' as const, onClick: () => openArt() }] : []),
            ]}>

            {/* Search */}
            <form onSubmit={e => { e.preventDefault(); router.get(route('kb.index'), { search }); }}
                className="mb-6 flex gap-2">
                <div className="relative flex-1 max-w-sm">
                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input value={search} onChange={e => setSearch(e.target.value)} placeholder={t('Search articles…')} className="pl-9" />
                </div>
                <Button type="submit" variant="outline">{t('Search')}</Button>
                {initSearch && <Button type="button" variant="ghost" onClick={() => { setSearch(''); router.get(route('kb.index')); }}>{t('Clear')}</Button>}
            </form>

            {/* Categories + articles */}
            {categories.length === 0 && !initSearch ? (
                <div className="flex flex-col items-center py-20 text-center">
                    <BookOpen className="mb-3 h-10 w-10 text-muted-foreground" />
                    <p className="text-muted-foreground">{t('No categories yet.')}</p>
                    {canManageCategories && <Button className="mt-4" onClick={() => openCat()}><Plus className="mr-2 h-4 w-4" />{t('Create first category')}</Button>}
                </div>
            ) : initSearch ? (
                <div className="space-y-2">
                    <p className="text-sm text-muted-foreground">{articles.length} {t('result(s) for')} "{initSearch}"</p>
                    {articles.map((a: any) => (
                        <div key={a.id} onClick={() => router.get(route('kb.articles.show', a.id))}
                            className="flex cursor-pointer items-center justify-between rounded-lg border p-3 hover:bg-muted/40">
                            <div>
                                <p className="font-medium text-sm">{a.title}</p>
                                <p className="text-xs text-muted-foreground">{a.category?.name}</p>
                            </div>
                            <ChevronRight className="h-4 w-4 text-muted-foreground" />
                        </div>
                    ))}
                </div>
            ) : (
                <div className="space-y-6">
                    {categories.map((cat: any) => (
                        <div key={cat.id}>
                            <div className="mb-3 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <BookOpen className="h-5 w-5 text-primary" />
                                    <h2 className="font-semibold">{cat.name}</h2>
                                    <Badge variant="secondary">{cat.published_articles_count} / {cat.articles_count}</Badge>
                                </div>
                                <div className="flex gap-1">
                                    {canCreateArticles && <Button size="icon" variant="ghost" className="h-7 w-7" onClick={() => openArt({ kb_category_id: cat.id })}><Plus className="h-3.5 w-3.5" /></Button>}
                                    {canManageCategories && <Button size="icon" variant="ghost" className="h-7 w-7" onClick={() => openCat(cat)}><Pencil className="h-3.5 w-3.5" /></Button>}
                                    {canManageCategories && <Button size="icon" variant="ghost" className="h-7 w-7 text-destructive hover:text-destructive" onClick={() => { setCurrent(cat); setDeleteType('category'); setDeleteModal(true); }}><Trash2 className="h-3.5 w-3.5" /></Button>}
                                </div>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {articles.filter((a: any) => a.kb_category_id === cat.id).map((art: any) => (
                                    <div key={art.id} className="group flex items-start justify-between rounded-lg border p-3 hover:bg-muted/30 cursor-pointer"
                                        onClick={() => router.get(route('kb.articles.show', art.id))}>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium truncate">{art.title}</p>
                                            <div className="flex items-center gap-2 mt-1">
                                                <Badge variant={art.is_published ? 'default' : 'secondary'} className="text-[10px]">
                                                    {art.is_published ? t('Published') : t('Draft')}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">{art.views} {t('views')}</span>
                                            </div>
                                        </div>
                                        <div className="flex gap-1 opacity-0 group-hover:opacity-100 ml-2">
                                            {canUpdateArticles && <Button size="icon" variant="ghost" className="h-6 w-6" onClick={e => { e.stopPropagation(); openArt(art); }}><Pencil className="h-3 w-3" /></Button>}
                                            {canDeleteArticles && <Button size="icon" variant="ghost" className="h-6 w-6 text-destructive hover:text-destructive" onClick={e => { e.stopPropagation(); setCurrent(art); setDeleteType('article'); setDeleteModal(true); }}><Trash2 className="h-3 w-3" /></Button>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Category Dialog */}
            <Dialog open={catModal} onOpenChange={setCatModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{mode === 'create' ? t('New Category') : t('Edit Category')}</DialogTitle></DialogHeader>
                    <div className="space-y-3 py-2">
                        <div><Label>{t('Name')} *</Label><Input value={catName} onChange={e => setCatName(e.target.value)} className="mt-1" /></div>
                        <div><Label>{t('Description')}</Label><Textarea value={catDesc} onChange={e => setCatDesc(e.target.value)} className="mt-1" rows={2} /></div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCatModal(false)}>{t('Cancel')}</Button>
                        <Button onClick={saveCat} disabled={!catName.trim()}>{mode === 'create' ? t('Create') : t('Save')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Article Dialog */}
            <Dialog open={artModal} onOpenChange={setArtModal}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader><DialogTitle>{mode === 'create' ? t('New Article') : t('Edit Article')}</DialogTitle></DialogHeader>
                    <div className="space-y-3 py-2">
                        <div><Label>{t('Title')} *</Label><Input value={artTitle} onChange={e => setArtTitle(e.target.value)} className="mt-1" /></div>
                        <div><Label>{t('Category')} *</Label>
                            <Select value={String(artCatId)} onValueChange={setArtCatId}>
                                <SelectTrigger className="mt-1"><SelectValue /></SelectTrigger>
                                <SelectContent className="z-[9999]">{categories.map((c: any) => <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>)}</SelectContent>
                            </Select>
                        </div>
                        <div><Label>{t('Content')} {mode === 'create' && canManageAttachments ? '' : '*'}</Label><Textarea value={artContent} onChange={e => setArtContent(e.target.value)} className="mt-1 font-mono text-sm" rows={10} placeholder={t('Write your article here…')} /></div>
                        {mode === 'create' && canManageAttachments && (
                            <div>
                                <Label>{t('Attach Document')} {!artContent.trim() ? '*' : ''}</Label>
                                <Input type="file" className="mt-1" onChange={e => setArtFile(e.target.files?.[0] || null)} />
                                <p className="text-xs text-muted-foreground mt-1">{t('Optional if you\'ve written content above — upload a document (PDF, Word, etc.) instead of or in addition to text content.')}</p>
                            </div>
                        )}
                        <div className="flex items-center gap-2"><Switch checked={artPublished} onCheckedChange={setArtPublished} /><Label>{t('Published')}</Label></div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setArtModal(false)}>{t('Cancel')}</Button>
                        <Button onClick={saveArt} disabled={!artTitle.trim() || (!artContent.trim() && !artFile)}>{mode === 'create' ? t('Create') : t('Save')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EnhancedDeleteModal isOpen={deleteModal} onClose={() => setDeleteModal(false)} onConfirm={confirmDelete}
                title={t(`Delete ${deleteType}`)} description={deleteType === 'category' ? t('All articles in this category will also be deleted.') : t('This article will be permanently deleted.')}
                itemName={current?.name || current?.title || ''} />
        </PageTemplate>
    );
}
