import * as React from "react"
import * as CheckboxPrimitive from "@radix-ui/react-checkbox"
import { Check, Minus } from "lucide-react"

import { cn } from "@/lib/utils"

interface IndeterminateCheckboxProps extends React.ComponentPropsWithoutRef<typeof CheckboxPrimitive.Root> {
  indeterminate?: boolean;
}

const IndeterminateCheckbox = React.forwardRef<
  React.ElementRef<typeof CheckboxPrimitive.Root>,
  IndeterminateCheckboxProps
>(({ className, indeterminate, ...props }, ref) => {
  // Use a ref to access the DOM element
  const checkboxRef = React.useRef<HTMLButtonElement>(null);
  
  // Forward the ref
  React.useImperativeHandle(ref, () => checkboxRef.current!);
  
  // Set the indeterminate property on the DOM element
  React.useEffect(() => {
    if (checkboxRef.current) {
      checkboxRef.current.dataset.state = indeterminate ? 'indeterminate' : props.checked ? 'checked' : 'unchecked';
    }
  }, [indeterminate, props.checked]);

  return (
    <CheckboxPrimitive.Root
      ref={checkboxRef}
      className={cn(
        "peer h-4 w-4 shrink-0 cursor-pointer rounded-[4px] border border-ds-border-strong bg-ds-surface shadow-ds-xs transition-colors duration-150 hover:border-brand-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus focus-visible:ring-offset-2 focus-visible:ring-offset-ds-surface disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground",
        className
      )}
      {...props}
    >
      <CheckboxPrimitive.Indicator
        className={cn("flex items-center justify-center text-current")}
      >
        {indeterminate ? (
          <Minus className="h-3 w-3" strokeWidth={3} aria-hidden="true" />
        ) : (
          <Check className="h-3.5 w-3.5" strokeWidth={3} aria-hidden="true" />
        )}
      </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
  )
})
IndeterminateCheckbox.displayName = "IndeterminateCheckbox"

export { IndeterminateCheckbox }