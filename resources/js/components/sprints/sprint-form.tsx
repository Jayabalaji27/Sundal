import { useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { AgileSprint, shortDate } from '@/components/tasks/agile-bits';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export interface SprintFormValues {
    name: string;
    goal: string;
    start_date: string;
    end_date: string;
}

export const toIsoDate = (date: Date) => {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
};

export const addDays = (iso: string, days: number) => {
    const date = new Date(`${iso}T00:00:00`);
    date.setDate(date.getDate() + days);
    return toIsoDate(date);
};

export const sprintDateRange = (sprint: Pick<AgileSprint, 'start_date' | 'end_date'>) =>
    sprint.start_date || sprint.end_date ? `${shortDate(sprint.start_date) || '…'} – ${shortDate(sprint.end_date) || '…'}` : '';

/** A new sprint starts the day after the last open one ends (or today) and runs two weeks. */
export function newSprintValues(openSprints: Pick<AgileSprint, 'end_date'>[], name: string): SprintFormValues {
    const lastEnd = openSprints.map(sprint => sprint.end_date).filter(Boolean).sort().pop() as string | undefined;
    const start = lastEnd ? addDays(lastEnd, 1) : toIsoDate(new Date());
    return { name, goal: '', start_date: start, end_date: addDays(start, 13) };
}

export function SprintFields({ form, setForm, errors, hideName = false }: {
    form: SprintFormValues;
    setForm: (form: SprintFormValues) => void;
    errors: Record<string, string>;
    hideName?: boolean;
}) {
    const { t } = useTranslation();
    return (
        <div className="space-y-4">
            {!hideName && (
                <div className="space-y-1.5">
                    <Label htmlFor="sprint-name">{t('Sprint name')}</Label>
                    <Input id="sprint-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} autoFocus />
                    {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                </div>
            )}
            <div className="space-y-1.5">
                <Label htmlFor="sprint-goal">{t('Sprint goal')} <span className="font-normal text-muted-foreground">({t('optional')})</span></Label>
                <Textarea id="sprint-goal" rows={2} value={form.goal} onChange={(e) => setForm({ ...form, goal: e.target.value })} placeholder={t('What should this sprint deliver?')} />
                {errors.goal && <p className="text-sm text-red-600">{errors.goal}</p>}
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                    <Label htmlFor="sprint-start">{t('Start date')}</Label>
                    <Input id="sprint-start" type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
                    {errors.start_date && <p className="text-sm text-red-600">{errors.start_date}</p>}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="sprint-end">{t('End date')}</Label>
                    <Input id="sprint-end" type="date" min={form.start_date || undefined} value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
                    {errors.end_date && <p className="text-sm text-red-600">{errors.end_date}</p>}
                </div>
            </div>
        </div>
    );
}

/**
 * Create a sprint (projectId) or edit one (sprint), starting from `initial`;
 * closes itself after the server accepts it. Callers change `key` each time
 * they open it so the fields start fresh.
 */
export function SprintFormDialog({ open, onClose, projectId, sprint, initial }: {
    open: boolean;
    onClose: () => void;
    projectId: number;
    sprint?: AgileSprint | null;
    initial: SprintFormValues;
}) {
    const { t } = useTranslation();
    const [form, setForm] = useState<SprintFormValues>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const save = () => {
        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess: onClose,
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (sprint) router.put(route('sprints.update', sprint.id), { ...form }, options);
        else router.post(route('sprints.store', projectId), { ...form }, options);
    };

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{sprint ? t('Edit sprint') : t('Create sprint')}</DialogTitle>
                    <DialogDescription>{t('Name the sprint, say what it should achieve and when it runs.')}</DialogDescription>
                </DialogHeader>
                <SprintFields form={form} setForm={setForm} errors={errors} />
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>{t('Cancel')}</Button>
                    <Button onClick={save} disabled={busy || !form.name.trim()}>
                        {sprint ? t('Save') : t('Create sprint')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
