import * as React from "react"
import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

// Small tags and labels: tinted, 6px radius, quieter than actions.
// For workflow states (Active, Pending, Failed) use StatusBadge.
const badgeVariants = cva(
  "inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden whitespace-nowrap rounded-sm border px-2 py-0.5 text-xs font-medium transition-[color,background-color,box-shadow] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus [&>svg]:pointer-events-none [&>svg]:size-3",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-brand-soft text-brand-soft-fg [a&]:hover:bg-ds-surface-selected",
        secondary:
          "border-transparent bg-ds-neutral-bg text-ds-neutral-fg [a&]:hover:bg-ds-border-subtle",
        neutral:
          "border-transparent bg-ds-neutral-bg text-ds-neutral-fg",
        destructive:
          "border-transparent bg-ds-danger-bg text-ds-danger-fg",
        outline:
          "border-ds-border bg-ds-surface text-ds-fg-secondary [a&]:hover:bg-ds-surface-hover",
        success:
          "border-transparent bg-ds-success-bg text-ds-success-fg",
        warning:
          "border-transparent bg-ds-warning-bg text-ds-warning-fg",
        info:
          "border-transparent bg-ds-info-bg text-ds-info-fg",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
