import * as React from "react"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"

/**
 * Confirmation for actions with real consequences (delete, deactivate, remove
 * access). Say what will happen in `description` rather than "Are you sure?".
 * The confirm button is solid red for `tone="danger"`: this is the final step.
 * Focus is trapped while open and returns to the trigger on close; Escape cancels.
 */
function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel,
  cancelLabel,
  loadingLabel,
  tone = "danger",
  loading = false,
  onConfirm,
  children,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: React.ReactNode
  description: React.ReactNode
  confirmLabel: React.ReactNode
  cancelLabel?: React.ReactNode
  /** e.g. "Deactivating..." */
  loadingLabel?: React.ReactNode
  tone?: "danger" | "default"
  loading?: boolean
  onConfirm: () => void
  children?: React.ReactNode
}) {
  const { t } = useTranslation()

  return (
    <Dialog open={open} onOpenChange={(next) => !loading && onOpenChange(next)}>
      <DialogContent role="alertdialog" className="max-w-[480px]">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        {children}
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
            {cancelLabel ?? t("Cancel")}
          </Button>
          <Button
            variant={tone === "danger" ? "destructive" : "default"}
            onClick={onConfirm}
            loading={loading}
            loadingText={loadingLabel}
          >
            {confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export { ConfirmDialog }
