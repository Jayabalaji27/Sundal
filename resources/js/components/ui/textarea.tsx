import * as React from "react"

import { cn } from "@/lib/utils"

export interface TextareaProps
  extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {}

const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ className, ...props }, ref) => {
    return (
      <textarea
        data-slot="textarea"
        className={cn(
          "flex min-h-20 w-full rounded-md border border-ds-border-strong bg-ds-surface px-3 py-2.5 text-base text-ds-fg shadow-ds-xs outline-none transition-[border-color,box-shadow] duration-150 ease-out md:text-sm",
          "placeholder:text-ds-fg-placeholder hover:border-ds-border-hover focus-visible:border-ds-border-focus focus-visible:ring-[3px] focus-visible:ring-brand-500/12",
          "disabled:cursor-not-allowed disabled:border-ds-border disabled:bg-ds-surface-sunken disabled:text-ds-fg-disabled disabled:shadow-none",
          "aria-invalid:border-ds-danger-input aria-invalid:hover:border-ds-danger-input aria-invalid:focus-visible:border-ds-danger-input aria-invalid:focus-visible:ring-ds-danger-input/12",
          className
        )}
        ref={ref}
        {...props}
      />
    )
  }
)
Textarea.displayName = "Textarea"

export { Textarea }
