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
import { Plus, Bot, MessageSquare, Pencil, Trash2, Settings, BookOpen } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { hasPermission } from '@/utils/authorization';

const MODELS = [
    { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo (Fast, cheap)' },
    { value: 'gpt-4o-mini',   label: 'GPT-4o Mini (Smart, affordable)' },
    { value: 'gpt-4o',        label: 'GPT-4o (Most capable)' },
    { value: 'gpt-4-turbo',   label: 'GPT-4 Turbo' },
];

export default function AgentsIndex() {
    const { t } = useTranslation();
    const { agents, categories, hasKey, flash, auth } = usePage().props as any;
    const permissions: string[] = auth?.permissions || [];
    const canCreate = hasPermission(permissions, 'agent_create');

    const [modal, setModal] = useState(false);
    const [deleteModal, setDeleteModal] = useState(false);
    const [current, setCurrent] = useState<any>(null);
    const [mode, setMode] = useState<'create'|'edit'>('create');

    // form state
    const [name, setName] = useState('');
    const [desc, setDesc] = useState('');
    const [prompt, setPrompt] = useState('');
    const [greeting, setGreeting] = useState('');
    const [selCats, setSelCats] = useState<number[]>([]);
    const [model, setModel] = useState('gpt-3.5-turbo');
    const [isActive, setIsActive] = useState(true);

    useEffect(() => { if (flash?.success) toast.success(flash.success); if (flash?.error) toast.error(flash.error); }, [flash]);

    const openCreate = () => {
        setMode('create'); setCurrent(null);
        setName(''); setDesc(''); setPrompt(''); setGreeting('');
        setSelCats([]); setModel('gpt-3.5-turbo'); setIsActive(true);
        setModal(true);
    };

    const openEdit = (a: any) => {
        setMode('edit'); setCurrent(a);
        setName(a.name); setDesc(a.description || ''); setPrompt(a.system_prompt || '');
        setGreeting(a.greeting_message || ''); setSelCats(a.kb_category_ids || []);
        setModel(a.model || 'gpt-3.5-turbo'); setIsActive(a.is_active);
        setModal(true);
    };

    const toggleCat = (id: number) =>
        setSelCats(p => p.includes(id) ? p.filter(x => x !== id) : [...p, id]);

    const save = () => {
        const data = { name, description: desc, system_prompt: prompt, greeting_message: greeting, kb_category_ids: selCats, model, is_active: isActive };
        if (mode === 'create') {
            router.post(route('agents.store'), data, { onSuccess: () => setModal(false), onError: e => toast.error(Object.values(e)[0] as string) });
        } else {
            router.put(route('agents.update', current.id), data, { onSuccess: () => setModal(false), onError: e => toast.error(Object.values(e)[0] as string) });
        }
    };

    return (
        <PageTemplate title={t('Agents')} description={t('Create AI agents powered by ChatGPT and your Knowledge Base.')}
            url={route('agents.index')} breadcrumbs={[{ title: t('Agents'), href: route('agents.index') }]}
            actions={canCreate ? [{ label: t('New Agent'), icon: <Plus className="mr-1.5 h-4 w-4" />, variant: 'default', onClick: openCreate }] : []}>

            {/* No API key warning */}
            {!hasKey && (
                <div className="mb-6 flex items-center gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                    <Settings className="h-5 w-5 shrink-0 text-amber-600" />
                    <div className="flex-1">
                        <p className="font-medium text-amber-800 dark:text-amber-200">OpenAI API key not configured</p>
                        <p className="text-sm text-amber-700 dark:text-amber-300">
                            {hasPermission(permissions, 'settings_chatgpt')
                                ? t('Agents need a ChatGPT API key to respond.')
                                : t('Agents need a ChatGPT API key to respond. Please contact your admin or manager to configure it.')}
                        </p>
                    </div>
                    {hasPermission(permissions, 'settings_chatgpt') && (
                        <Button size="sm" variant="outline" onClick={() => router.get(route('settings') + '#chatgpt-settings')}>
                            Configure
                        </Button>
                    )}
                </div>
            )}

            {agents.length === 0 ? (
                <div className="flex flex-col items-center py-24 text-center">
                    <div className="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-primary/10">
                        <Bot className="h-10 w-10 text-primary" />
                    </div>
                    <h2 className="text-xl font-bold">{t('No Agents Yet')}</h2>
                    <p className="mt-2 text-sm text-muted-foreground max-w-sm">
                        {t('Create your first agent — give it a name, a personality, and connect it to your Knowledge Base.')}
                    </p>
                    {canCreate && (
                        <Button className="mt-6" onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" /> {t('Create Your First Agent')}
                        </Button>
                    )}
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {agents.map((a: any) => (
                        <Card key={a.id} className="group overflow-hidden transition-shadow hover:shadow-md">
                            <CardHeader className="pb-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-2.5">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10">
                                            <Bot className="h-5 w-5 text-primary" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base">{a.name}</CardTitle>
                                            {a.description && <p className="text-xs text-muted-foreground">{a.description}</p>}
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <Button size="icon" variant="ghost" className="h-7 w-7" onClick={() => openEdit(a)}>
                                            <Pencil className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button size="icon" variant="ghost" className="h-7 w-7 text-destructive hover:text-destructive"
                                            onClick={() => { setCurrent(a); setDeleteModal(true); }}>
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-0">
                                <div className="flex flex-wrap gap-1.5 mb-3">
                                    <Badge variant="outline" className="text-xs">{a.model}</Badge>
                                    <Badge variant={a.is_active ? 'default' : 'secondary'} className="text-xs">
                                        {a.is_active ? t('Active') : t('Inactive')}
                                    </Badge>
                                    {(a.kb_category_ids?.length > 0) && (
                                        <Badge variant="secondary" className="gap-1 text-xs">
                                            <BookOpen className="h-2.5 w-2.5" /> {a.kb_category_ids.length} {t('KB categories')}
                                        </Badge>
                                    )}
                                </div>
                                <Button className="w-full" size="sm"
                                    onClick={() => router.get(route('agents.chat', a.id))}>
                                    <MessageSquare className="mr-1.5 h-4 w-4" /> {t('Chat with Agent')}
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Create / Edit Dialog */}
            <Dialog open={modal} onOpenChange={setModal}>
                <DialogContent className="sm:max-w-xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{mode === 'create' ? t('Create Agent') : t('Edit Agent')}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="col-span-2">
                                <Label>{t('Agent Name')} *</Label>
                                <Input value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Support Bot" className="mt-1" />
                            </div>
                            <div className="col-span-2">
                                <Label>{t('Description')}</Label>
                                <Input value={desc} onChange={e => setDesc(e.target.value)} placeholder="What does this agent do?" className="mt-1" />
                            </div>
                        </div>

                        <div>
                            <Label>{t('System Prompt')}</Label>
                            <Textarea value={prompt} onChange={e => setPrompt(e.target.value)} rows={4} className="mt-1 font-mono text-sm"
                                placeholder="You are a helpful support agent for Acme Corp. Be concise and professional. If you don't know the answer, say so." />
                            <p className="mt-1 text-xs text-muted-foreground">Defines the agent's personality and behaviour.</p>
                        </div>

                        <div>
                            <Label>{t('Greeting Message')}</Label>
                            <Input value={greeting} onChange={e => setGreeting(e.target.value)} className="mt-1"
                                placeholder="Hi! How can I help you today?" />
                        </div>

                        <div>
                            <Label>{t('AI Model')}</Label>
                            <Select value={model} onValueChange={setModel}>
                                <SelectTrigger className="mt-1"><SelectValue /></SelectTrigger>
                                <SelectContent>{MODELS.map(m => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}</SelectContent>
                            </Select>
                        </div>

                        {categories.length > 0 && (
                            <div>
                                <Label>{t('Knowledge Base Categories')}</Label>
                                <p className="text-xs text-muted-foreground mb-2">{t('Agent will search these categories for context when answering.')}</p>
                                <div className="flex flex-wrap gap-2">
                                    {categories.map((c: any) => (
                                        <button key={c.id} type="button"
                                            onClick={() => toggleCat(c.id)}
                                            className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                                                selCats.includes(c.id)
                                                    ? 'bg-primary text-primary-foreground border-primary'
                                                    : 'bg-background border-border hover:bg-muted'
                                            }`}>
                                            <BookOpen className="mr-1 inline h-3 w-3" />{c.name}
                                            {c.published_articles_count > 0 && ` (${c.published_articles_count})`}
                                        </button>
                                    ))}
                                </div>
                                {selCats.length === 0 && (
                                    <p className="mt-1 text-xs text-muted-foreground italic">{t('No categories selected — agent will search all published KB articles.')}</p>
                                )}
                            </div>
                        )}

                        <div className="flex items-center gap-2">
                            <Switch checked={isActive} onCheckedChange={setIsActive} />
                            <Label>{t('Active')}</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setModal(false)}>{t('Cancel')}</Button>
                        <Button onClick={save} disabled={!name.trim()}>{mode === 'create' ? t('Create Agent') : t('Save Changes')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EnhancedDeleteModal isOpen={deleteModal} onClose={() => setDeleteModal(false)}
                onConfirm={() => { if (current) router.delete(route('agents.destroy', current.id), { onSuccess: () => setDeleteModal(false) }); }}
                entityName={t('agent')} warningMessage={t('This agent will be permanently deleted.')}
                itemName={current?.name || ''} />
        </PageTemplate>
    );
}
