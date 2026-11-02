import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ref, onClick, ...props }: React.ComponentProps<"input">) {
  const innerRef = React.useRef<HTMLInputElement>(null);

  const handleClick = (e: React.MouseEvent<HTMLInputElement>) => {
    if ((type === "date" || type === "datetime-local" || type === "time" || type === "month") && innerRef.current) {
      try {
        innerRef.current.showPicker?.();
      } catch (err) {
        // Ignore errors if showPicker is unsupported or already open
      }
    }
    if (onClick) onClick(e);
  };

  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        // 16px text below md keeps iOS from zooming into the field on focus.
        "flex h-9 w-full min-w-0 rounded-md border border-ds-border-strong bg-ds-surface px-3 py-2 text-base text-ds-fg shadow-ds-xs outline-none transition-[border-color,box-shadow] duration-150 ease-out md:text-sm",
        "placeholder:text-ds-fg-placeholder selection:bg-primary selection:text-primary-foreground",
        "file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-ds-fg",
        "hover:border-ds-border-hover focus-visible:border-ds-border-focus focus-visible:ring-[3px] focus-visible:ring-brand-500/12",
        "disabled:cursor-not-allowed disabled:border-ds-border disabled:bg-ds-surface-sunken disabled:text-ds-fg-disabled disabled:shadow-none",
        "aria-invalid:border-ds-danger-input aria-invalid:hover:border-ds-danger-input aria-invalid:focus-visible:border-ds-danger-input aria-invalid:focus-visible:ring-ds-danger-input/12",
        (type === "date" || type === "datetime-local" || type === "time" || type === "month") && !props.disabled ? "cursor-pointer" : "",
        className
      )}
      ref={(node) => {
        innerRef.current = node;
        if (typeof ref === 'function') {
          ref(node);
        } else if (ref) {
          (ref as React.MutableRefObject<HTMLInputElement | null>).current = node;
        }
      }}
      onClick={handleClick}
      {...props}
    />
  )
}

export { Input }
