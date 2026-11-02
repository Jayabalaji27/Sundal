import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

const alertVariants = cva(
  "relative w-full rounded-xl border px-4 py-3 text-sm grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current",
  {
    variants: {
      variant: {
        default: "border-ds-border bg-ds-surface text-ds-fg",
        destructive:
          "border-ds-danger-border/60 bg-ds-danger-bg text-ds-danger-fg [&>svg]:text-current *:data-[slot=alert-description]:text-ds-danger-fg/90",
        success:
          "border-ds-success/25 bg-ds-success-bg text-ds-success-fg *:data-[slot=alert-description]:text-ds-success-fg/90",
        warning:
          "border-ds-warning/25 bg-ds-warning-bg text-ds-warning-fg *:data-[slot=alert-description]:text-ds-warning-fg/90",
        info:
          "border-ds-info/20 bg-ds-info-bg text-ds-info-fg *:data-[slot=alert-description]:text-ds-info-fg/90",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Alert({
  className,
  variant,
  ...props
}: React.ComponentProps<"div"> & VariantProps<typeof alertVariants>) {
  return (
    <div
      data-slot="alert"
      role="alert"
      className={cn(alertVariants({ variant }), className)}
      {...props}
    />
  )
}

function AlertTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-title"
      className={cn(
        "col-start-2 line-clamp-1 min-h-4 font-medium tracking-tight",
        className
      )}
      {...props}
    />
  )
}

function AlertDescription({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-description"
      className={cn(
        "text-ds-fg-secondary col-start-2 grid justify-items-start gap-1 text-sm [&_p]:leading-relaxed",
        className
      )}
      {...props}
    />
  )
}

export { Alert, AlertTitle, AlertDescription }
