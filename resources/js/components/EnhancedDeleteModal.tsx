import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface EnhancedDeleteModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  itemName: string;
  entityName: string;
  warningMessage?: string;
  additionalInfo?: string[];
  isSubmitting?: boolean;
  confirmDisabled?: boolean;
}

export function EnhancedDeleteModal({
  isOpen,
  onClose,
  onConfirm,
  itemName,
  entityName,
  warningMessage,
  additionalInfo = [],
  isSubmitting = false,
  confirmDisabled = false
}: EnhancedDeleteModalProps) {
  const { t } = useTranslation();
  
  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent role="alertdialog">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5 shrink-0 text-ds-danger" aria-hidden="true" />
            {t("Delete")} {entityName}
          </DialogTitle>
          {/* Rendered as a div: the description holds block content, which a <p> can't */}
          <DialogDescription asChild>
            <div className="space-y-3">
              <div className="rounded-lg border border-ds-danger-border/60 bg-ds-danger-bg p-3">
                <p className="font-medium text-ds-danger-fg">
                  {t("Are you sure you want to delete")} "{itemName}"?
                </p>
                <p className="mt-1 text-sm text-ds-danger-fg/90">
                  {warningMessage || t("This action cannot be undone.")}
                </p>
              </div>
            
              {additionalInfo.length > 0 && (
                <div className="rounded-lg border border-ds-warning/25 bg-ds-warning-bg p-3">
                  <p className="mb-2 text-sm font-medium text-ds-warning-fg">
                    {t("This will also delete:")}
                  </p>
                  <ul className="space-y-1 text-sm text-ds-warning-fg">
                    {additionalInfo.map((info, index) => (
                      <li key={index} className="flex items-center gap-2">
                        <span className="size-1 shrink-0 rounded-full bg-ds-warning" aria-hidden="true"></span>
                        {info}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
            {t("Cancel")}
          </Button>
          <Button
            type="button"
            variant="destructive"
            onClick={onConfirm}
            disabled={confirmDisabled}
            loading={isSubmitting}
            loadingText={t("Deleting...")}
          >
            <Trash2 />
            {`${t("Delete")} ${entityName}`}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}