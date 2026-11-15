import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { toast } from '@/components/custom-toast';

type Options = { preserveState?: boolean; onSuccess?: () => void };

const REASON_EVENT = 'task-stage:needs-reason';

/**
 * Every stage change goes through here. The server enforces the process
 * (unassigned tasks stay in the first stage, reopening Done is for managers,
 * moving back needs a reason). A refusal is shown as a toast; a missing reason
 * opens the dialog below, which sends the move again with the reason.
 */
export function changeTaskStage(taskId: number | string, stageId: number | string, options: Options = {}, reason?: string) {
    router.put(
        route('tasks.change-stage', taskId),
        { task_stage_id: stageId, ...(reason ? { reason } : {}) },
        {
            preserveScroll: true,
            preserveState: options.preserveState ?? true,
            onSuccess: () => options.onSuccess?.(),
            onError: (errors: Record<string, string>) => {
                if (errors.reason && !reason) {
                    window.dispatchEvent(new CustomEvent(REASON_EVENT, { detail: { taskId, stageId, options, message: errors.reason } }));
                    return;
                }
                toast.error(errors.task_stage_id || errors.reason || Object.values(errors)[0]);
            },
        },
    );
}

type Pending = { taskId: number | string; stageId: number | string; options: Options; message: string };

/** Mounted once in the app layout. */
export function TaskStageReasonDialog() {
    const { t } = useTranslation();
    const [pending, setPending] = useState<Pending | null>(null);
    const [reason, setReason] = useState('');

    useEffect(() => {
        const open = (e: Event) => {
            setPending((e as CustomEvent<Pending>).detail);
            setReason('');
        };
        window.addEventListener(REASON_EVENT, open);
        return () => window.removeEventListener(REASON_EVENT, open);
    }, []);

    const submit = () => {
        if (!pending || !reason.trim()) return;
        changeTaskStage(pending.taskId, pending.stageId, pending.options, reason.trim());
        setPending(null);
    };

    return (
        <Dialog open={!!pending} onOpenChange={(open) => !open && setPending(null)}>
            {/* Opens over the task window, which can re-register in the modal stack after
                this one; a fixed z-index and its own backdrop keep it on top and the window dimmed. */}
            {pending && <div className="fixed inset-0 z-[998] bg-black/40" aria-hidden="true" />}
            <DialogContent className="sm:max-w-md" style={{ zIndex: 999 }}>
                <DialogHeader>
                    <DialogTitle>{t('Reason for moving back')}</DialogTitle>
                    <DialogDescription>{pending?.message}</DialogDescription>
                </DialogHeader>
                <div className="space-y-2">
                    <Label htmlFor="stage-back-reason">{t('Reason')}</Label>
                    <Textarea
                        id="stage-back-reason"
                        autoFocus
                        rows={3}
                        maxLength={1000}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        onKeyDown={(e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) submit(); }}
                        placeholder={t('e.g. Failed QA, the login button does nothing on Safari')}
                    />
                    <p className="text-xs text-ds-fg-muted">{t('Saved as a comment on the task so the assignee knows why it came back.')}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setPending(null)}>{t('Cancel')}</Button>
                    <Button onClick={submit} disabled={!reason.trim()}>{t('Move back')}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
