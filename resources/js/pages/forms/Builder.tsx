import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ArrowLeft, Plus, Trash2, ChevronUp, ChevronDown, Save, ExternalLink, GripVertical, Eye } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

type FieldType = 'text' | 'email' | 'number' | 'textarea' | 'checkbox' | 'select' | 'date';

interface Field {
    id?: number;
    type: FieldType;
    label: string;
    placeholder: string;
    required: boolean;
    options: string[]; // for select
    _key: string; // local key for React
}

const FIELD_TYPES: { value: FieldType; label: string }[] = [
    { value: 'text', label: 'Short Text' },
    { value: 'email', label: 'Email' },
    { value: 'number', label: 'Number' },
    { value: 'textarea', label: 'Long Text' },
    { value: 'date', label: 'Date' },
    { value: 'checkbox', label: 'Checkbox' },
    { value: 'select', label: 'Dropdown' },
];

let keyCounter = 0;
const newKey = () => `field-${++keyCounter}-${Date.now()}`;

const emptyField = (type: FieldType = 'text'): Field => ({
    type,
    label: '',
    placeholder: '',
    required: false,
    options: type === 'select' ? [''] : [],
    _key: newKey(),
});

export default function FormBuilder() {
    const { t } = useTranslation();
    const { form, flash } = usePage().props as any;
    const appUrl = (usePage().props as any).globalSettings?.base_url || window.location.origin;

    const [fields, setFields] = useState<Field[]>(
        (form.fields ?? []).map((f: any) => ({ ...f, options: f.options ?? [], _key: newKey() }))
    );
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const addField = () => setFields(prev => [...prev, emptyField()]);

    const removeField = (key: string) => setFields(prev => prev.filter(f => f._key !== key));

    const moveUp = (idx: number) => {
        if (idx === 0) return;
        setFields(prev => { const a = [...prev]; [a[idx - 1], a[idx]] = [a[idx], a[idx - 1]]; return a; });
    };

    const moveDown = (idx: number) => {
        setFields(prev => {
            if (idx === prev.length - 1) return prev;
            const a = [...prev]; [a[idx], a[idx + 1]] = [a[idx + 1], a[idx]]; return a;
        });
    };

    const update = (key: string, patch: Partial<Field>) =>
        setFields(prev => prev.map(f => f._key === key ? { ...f, ...patch } : f));

    const updateOption = (key: string, i: number, val: string) =>
        setFields(prev => prev.map(f => {
            if (f._key !== key) return f;
            const opts = [...f.options];
            opts[i] = val;
            return { ...f, options: opts };
        }));

    const addOption = (key: string) =>
        setFields(prev => prev.map(f => f._key === key ? { ...f, options: [...f.options, ''] } : f));

    const removeOption = (key: string, i: number) =>
        setFields(prev => prev.map(f => f._key === key ? { ...f, options: f.options.filter((_, j) => j !== i) } : f));

    const save = () => {
        if (fields.length === 0) {
            toast.error(t('Add at least one field before saving.'));
            return;
        }
        if (fields.some(f => !f.label.trim())) {
            toast.error(t('All fields must have a label.'));
            return;
        }
        setSaving(true);
        router.post(route('forms.saveFields', form.id), { fields }, {
            // No onSuccess toast here - the backend's back()->with('success', ...)
            // flash message is already surfaced by the useEffect above, so adding
            // one here doubled up into two identical "Fields saved." toasts.
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(typeof firstError === 'string' ? firstError : t('Failed to save fields.'));
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <PageTemplate
            title={`${t('Builder')} — ${form.title}`}
            description={form.description || t('Design your form fields')}
            url={route('forms.builder', form.id)}
            breadcrumbs={[
                { title: t('Forms'), href: route('forms.index') },
                { title: form.title, href: route('forms.builder', form.id) },
            ]}
            actions={[
                { label: t('Responses'), icon: <Eye className="mr-1.5 h-4 w-4" />, variant: 'outline', onClick: () => router.get(route('forms.submissions', form.id)) },
                { label: t('Preview'), icon: <ExternalLink className="mr-1.5 h-4 w-4" />, variant: 'outline', onClick: () => window.open(`/f/${form.token}`, '_blank') },
                { label: saving ? t('Saving…') : t('Save'), icon: <Save className="mr-1.5 h-4 w-4" />, variant: 'default', onClick: save },
            ]}
        >
            <div className="mx-auto max-w-2xl">
                {/* Field list */}
                <div className="space-y-3">
                    {fields.length === 0 && (
                        <div className="rounded-xl border-2 border-dashed py-10 text-center text-muted-foreground">
                            {t('No fields yet. Click "Add Field" to start.')}
                        </div>
                    )}

                    {fields.map((field, idx) => (
                        <div key={field._key}
                            className="rounded-xl border bg-card p-4 shadow-sm">
                            <div className="flex items-start gap-3">
                                {/* Sort handles */}
                                <div className="flex flex-col pt-1">
                                    <button onClick={() => moveUp(idx)} disabled={idx === 0}
                                        className="text-muted-foreground hover:text-foreground disabled:opacity-20">
                                        <ChevronUp className="h-4 w-4" />
                                    </button>
                                    <GripVertical className="h-4 w-4 text-muted-foreground/40" />
                                    <button onClick={() => moveDown(idx)} disabled={idx === fields.length - 1}
                                        className="text-muted-foreground hover:text-foreground disabled:opacity-20">
                                        <ChevronDown className="h-4 w-4" />
                                    </button>
                                </div>

                                <div className="flex-1 space-y-3">
                                    {/* Row 1: type + label */}
                                    <div className="flex gap-3">
                                        <div className="w-36 shrink-0">
                                            <Label className="text-xs">{t('Type')}</Label>
                                            <Select value={field.type}
                                                onValueChange={v => update(field._key, { type: v as FieldType, options: v === 'select' ? [''] : [] })}>
                                                <SelectTrigger className="mt-1 h-8 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {FIELD_TYPES.map(t => (
                                                        <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="flex-1">
                                            <Label className="text-xs">{t('Label')} *</Label>
                                            <Input value={field.label}
                                                onChange={e => update(field._key, { label: e.target.value })}
                                                placeholder={t('Question label')}
                                                className={cn('mt-1 h-8 text-sm', !field.label.trim() && 'border-destructive')} />
                                        </div>
                                    </div>

                                    {/* Placeholder (not for checkbox) */}
                                    {field.type !== 'checkbox' && field.type !== 'select' && (
                                        <div>
                                            <Label className="text-xs">{t('Placeholder')}</Label>
                                            <Input value={field.placeholder}
                                                onChange={e => update(field._key, { placeholder: e.target.value })}
                                                placeholder={t('Optional hint text')}
                                                className="mt-1 h-8 text-sm" />
                                        </div>
                                    )}

                                    {/* Options for select */}
                                    {field.type === 'select' && (
                                        <div>
                                            <Label className="text-xs">{t('Options')}</Label>
                                            <div className="mt-1 space-y-1">
                                                {field.options.map((opt, i) => (
                                                    <div key={i} className="flex gap-2">
                                                        <Input value={opt}
                                                            onChange={e => updateOption(field._key, i, e.target.value)}
                                                            placeholder={`${t('Option')} ${i + 1}`}
                                                            className="h-8 text-sm" />
                                                        <Button size="icon" variant="ghost" className="h-8 w-8 text-destructive"
                                                            onClick={() => removeOption(field._key, i)}
                                                            disabled={field.options.length <= 1}>
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </div>
                                                ))}
                                                <Button size="sm" variant="outline" className="h-7 text-xs"
                                                    onClick={() => addOption(field._key)}>
                                                    <Plus className="mr-1 h-3 w-3" /> {t('Add option')}
                                                </Button>
                                            </div>
                                        </div>
                                    )}

                                    {/* Required toggle */}
                                    <div className="flex items-center gap-2">
                                        <Switch id={`req-${field._key}`} checked={field.required}
                                            onCheckedChange={v => update(field._key, { required: v })} />
                                        <Label htmlFor={`req-${field._key}`} className="text-xs cursor-pointer">
                                            {t('Required')}
                                        </Label>
                                    </div>
                                </div>

                                {/* Delete */}
                                <Button size="icon" variant="ghost"
                                    className="mt-1 h-7 w-7 text-destructive hover:text-destructive"
                                    onClick={() => removeField(field._key)}>
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>

                <Button variant="outline" className="mt-4 w-full" onClick={addField}>
                    <Plus className="mr-2 h-4 w-4" /> {t('Add Field')}
                </Button>

                {fields.length > 0 && (
                    <Button className="mt-3 w-full" onClick={save} disabled={saving}>
                        <Save className="mr-2 h-4 w-4" /> {saving ? t('Saving…') : t('Save Fields')}
                    </Button>
                )}
            </div>
        </PageTemplate>
    );
}
