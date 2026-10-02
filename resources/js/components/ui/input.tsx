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
        "border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        "focus-visible:border-primary",
        "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
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
