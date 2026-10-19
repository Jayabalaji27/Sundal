import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface Props {
    open: boolean;
    title: string;
    onCancel: () => void;
    onConfirm: (reason: string) => void;
}

/** Rejecting always asks why, so the submitter knows what to fix. */
export function RejectDialog({ open, title, onCancel, onConfirm }: Props) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');

    useEffect(() => {
        if (open) setReason('');
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={value => !value && onCancel()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>
                <div className="space-y-2">
                    <Label htmlFor="reject-reason" required>{t('Reason for rejection')}</Label>
                    <Textarea
                        id="reject-reason"
                        value={reason}
                        onChange={e => setReason(e.target.value)}
                        placeholder={t('Tell the submitter what to fix...')}
                        rows={3}
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onCancel}>{t('Cancel')}</Button>
                    <Button variant="destructive" disabled={!reason.trim()} onClick={() => onConfirm(reason.trim())}>
                        {t('Reject')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
