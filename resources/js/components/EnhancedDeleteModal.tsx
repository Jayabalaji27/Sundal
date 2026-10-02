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
}

export function EnhancedDeleteModal({
  isOpen,
  onClose,
  onConfirm,
  itemName,
  entityName,
  warningMessage,
  additionalInfo = [],
  isSubmitting = false
}: EnhancedDeleteModalProps) {
  const { t } = useTranslation();
  
  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-red-600">
            <AlertTriangle className="h-5 w-5" />
            {t("Delete")} {entityName}
          </DialogTitle>
          <DialogDescription className="space-y-3">
            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
              <p className="text-red-800 font-medium">
                {t("Are you sure you want to delete")} "{itemName}"?
              </p>
              <p className="text-red-700 text-sm mt-1">
                {warningMessage || t("This action cannot be undone.")}
              </p>
            </div>
            
            {additionalInfo.length > 0 && (
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p className="text-yellow-800 font-medium text-sm mb-2">
                  {t("This will also delete:")}
                </p>
                <ul className="text-yellow-700 text-sm space-y-1">
                  {additionalInfo.map((info, index) => (
                    <li key={index} className="flex items-center gap-2">
                      <span className="w-1 h-1 bg-yellow-600 rounded-full"></span>
                      {info}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter className="sm:justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
            {t("Cancel")}
          </Button>
          <Button
            type="button"
            variant="destructive"
            onClick={onConfirm}
            disabled={isSubmitting}
            className="bg-red-600 hover:bg-red-700"
          >
            <Trash2 className="h-4 w-4 mr-2" />
            {isSubmitting ? t("Deleting...") : `${t("Delete")} ${entityName}`}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}