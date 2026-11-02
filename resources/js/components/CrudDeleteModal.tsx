// components/CrudDeleteModal.tsx
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';

interface CrudDeleteModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  itemName: string;
  entityName: string;
}

export function CrudDeleteModal({
  isOpen,
  onClose,
  onConfirm,
  itemName,
  entityName
}: CrudDeleteModalProps) {
  const { t } = useTranslation();
  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent role="alertdialog" style={{ zIndex: 10000 }}>
        <DialogHeader>
          <DialogTitle>{t("Delete")} {entityName}</DialogTitle>
          <DialogDescription>
            {t("Are you sure you want to delete")} <span className="font-medium text-ds-fg">{itemName || `this ${entityName}`}</span>? {t("This action cannot be undone.")}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter className="sm:justify-end">
          <Button type="button" variant="outline" onClick={onClose}>
            {t("Cancel")}
          </Button>
          <Button type="button" variant="destructive" onClick={onConfirm}>
            {t("Delete")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
