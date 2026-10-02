import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { ArrowLeft } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function PublicForm() {
    const { form } = usePage().props as any;
    const [values, setValues] = useState<Record<string, any>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const set = (id: number, val: any) => setValues(prev => ({ ...prev, [`field_${id}`]: val }));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(route('forms.submit', form.token), values, {
            onError: (errs) => { setErrors(errs); setSubmitting(false); },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <>
            <Head title={form.title} />
            <div className="min-h-screen bg-muted/30 py-12 px-4">
                <div className="mx-auto max-w-lg">
                    {/* Back button - only shown if there's browser history */}
                    {window.history.length > 1 && (
                        <button
                            onClick={() => window.history.back()}
                            className="mb-4 flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors">
                            <ArrowLeft className="h-4 w-4" /> Back
                        </button>
                    )}
                    {/* Form card */}
                    <div className="rounded-2xl border bg-background shadow-sm">
                        {/* Header */}
                        <div className="border-b px-6 py-5">
                            <h1 className="text-xl font-bold">{form.title}</h1>
                            {form.description && (
                                <p className="mt-1 text-sm text-muted-foreground">{form.description}</p>
                            )}
                        </div>

                        {form.fields.length === 0 ? (
                            <div className="px-6 py-10 text-center text-sm text-muted-foreground">
                                This form isn't ready to accept responses yet. Please check back later.
                            </div>
                        ) : (
                        <form onSubmit={handleSubmit} className="space-y-5 px-6 py-6">
                            {form.fields.map((field: any) => {
                                const key = `field_${field.id}`;
                                const error = errors[key];
                                const val = values[key];

                                return (
                                    <div key={field.id}>
                                        {field.type !== 'checkbox' && (
                                            <Label className="mb-1.5 block text-sm font-medium">
                                                {field.label}
                                                {field.required && <span className="ml-1 text-destructive">*</span>}
                                            </Label>
                                        )}

                                        {field.type === 'text' && (
                                            <Input value={val ?? ''} onChange={e => set(field.id, e.target.value)}
                                                placeholder={field.placeholder}
                                                className={cn(error && 'border-destructive')} />
                                        )}
                                        {field.type === 'email' && (
                                            <Input type="email" value={val ?? ''} onChange={e => set(field.id, e.target.value)}
                                                placeholder={field.placeholder}
                                                className={cn(error && 'border-destructive')} />
                                        )}
                                        {field.type === 'number' && (
                                            <Input type="number" value={val ?? ''} onChange={e => set(field.id, e.target.value)}
                                                placeholder={field.placeholder}
                                                className={cn(error && 'border-destructive')} />
                                        )}
                                        {field.type === 'textarea' && (
                                            <Textarea value={val ?? ''} onChange={e => set(field.id, e.target.value)}
                                                placeholder={field.placeholder} rows={4}
                                                className={cn(error && 'border-destructive')} />
                                        )}
                                        {field.type === 'date' && (
                                            <Input type="date" value={val ?? ''} onChange={e => set(field.id, e.target.value)}
                                                className={cn(error && 'border-destructive')} />
                                        )}
                                        {field.type === 'select' && (
                                            <Select value={val ?? ''} onValueChange={v => set(field.id, v)}>
                                                <SelectTrigger className={cn(error && 'border-destructive')}>
                                                    <SelectValue placeholder={field.placeholder || 'Select an option'} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {(field.options ?? []).filter(Boolean).map((opt: string) => (
                                                        <SelectItem key={opt} value={opt}>{opt}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                        {field.type === 'checkbox' && (
                                            <div className="flex items-center gap-2">
                                                <Checkbox id={key} checked={!!val}
                                                    onCheckedChange={v => set(field.id, !!v)} />
                                                <Label htmlFor={key} className="cursor-pointer text-sm">
                                                    {field.label}
                                                    {field.required && <span className="ml-1 text-destructive">*</span>}
                                                </Label>
                                            </div>
                                        )}

                                        {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
                                    </div>
                                );
                            })}

                            <Button type="submit" className="w-full" disabled={submitting}>
                                {submitting ? 'Submitting…' : 'Submit'}
                            </Button>
                        </form>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
