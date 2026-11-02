import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

/*
 * Workflow state pill (Active, Pending, Failed, Suspended ...). Always shows
 * a dot or an icon next to the text, so the state never relies on colour alone.
 *
 *   <StatusBadge tone="success">Active</StatusBadge>
 *   <StatusBadge tone="warning" icon={<Clock />}>Pending</StatusBadge>
 */
const statusBadgeVariants = cva(
  "inline-flex h-6 w-fit shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-xs font-medium [&>svg]:size-3.5 [&>svg]:shrink-0",
  {
    variants: {
      tone: {
        success: "bg-ds-success-bg text-ds-success-fg",
        warning: "bg-ds-warning-bg text-ds-warning-fg",
        danger: "bg-ds-danger-bg text-ds-danger-fg",
        info: "bg-ds-info-bg text-ds-info-fg",
        neutral: "bg-ds-neutral-bg text-ds-neutral-fg",
        brand: "bg-brand-soft text-brand-soft-fg",
      },
    },
    defaultVariants: {
      tone: "neutral",
    },
  }
)

const DOT: Record<NonNullable<VariantProps<typeof statusBadgeVariants>["tone"]>, string> = {
  success: "bg-ds-success-dot",
  warning: "bg-ds-warning",
  danger: "bg-ds-danger",
  info: "bg-ds-info",
  neutral: "bg-ds-fg-muted",
  brand: "bg-brand-500",
}

function StatusBadge({
  className,
  tone,
  icon,
  children,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof statusBadgeVariants> & {
    /** Replaces the dot, e.g. a clock for Pending. */
    icon?: React.ReactNode
  }) {
  return (
    <span data-slot="status-badge" className={cn(statusBadgeVariants({ tone }), className)} {...props}>
      {icon ?? <span aria-hidden="true" className={cn("size-1.5 shrink-0 rounded-full", DOT[tone ?? "neutral"])} />}
      {children}
    </span>
  )
}

export { StatusBadge, statusBadgeVariants }
