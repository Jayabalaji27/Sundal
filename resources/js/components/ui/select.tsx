import * as React from "react"
import * as SelectPrimitive from "@radix-ui/react-select"
import { Check, ChevronDown, ChevronUp, Search } from "lucide-react"

import { cn } from "@/lib/utils"

const Select = SelectPrimitive.Root

const SelectGroup = SelectPrimitive.Group

const SelectValue = SelectPrimitive.Value

const SelectTrigger = React.forwardRef<
  React.ElementRef<typeof SelectPrimitive.Trigger>,
  React.ComponentPropsWithoutRef<typeof SelectPrimitive.Trigger>
>(({ className, children, ...props }, ref) => (
  <SelectPrimitive.Trigger
    ref={ref}
    className={cn(
      "flex h-9 w-full cursor-pointer items-center justify-between gap-2 rounded-md border border-ds-border-strong bg-ds-surface px-3 py-2 text-sm text-ds-fg shadow-ds-xs outline-none transition-[border-color,box-shadow] duration-150 ease-out [&>span]:line-clamp-1",
      "data-[placeholder]:text-ds-fg-placeholder hover:border-ds-border-hover focus-visible:border-ds-border-focus focus-visible:ring-[3px] focus-visible:ring-brand-500/12 data-[state=open]:border-ds-border-focus data-[state=open]:ring-[3px] data-[state=open]:ring-brand-500/12",
      "disabled:cursor-not-allowed disabled:border-ds-border disabled:bg-ds-surface-sunken disabled:text-ds-fg-disabled disabled:shadow-none",
      "aria-invalid:border-ds-danger-input aria-invalid:focus-visible:ring-ds-danger-input/12",
      className
    )}
    {...props}
  >
    {children}
    <SelectPrimitive.Icon asChild>
      <ChevronDown className="ml-auto h-4 w-4 shrink-0 text-ds-fg-muted" aria-hidden="true" />
    </SelectPrimitive.Icon>
  </SelectPrimitive.Trigger>
))
SelectTrigger.displayName = SelectPrimitive.Trigger.displayName

const SelectScrollUpButton = React.forwardRef<
  React.ElementRef<typeof SelectPrimitive.ScrollUpButton>,
  React.ComponentPropsWithoutRef<typeof SelectPrimitive.ScrollUpButton>
>(({ className, ...props }, ref) => (
  <SelectPrimitive.ScrollUpButton
    ref={ref}
    className={cn(
      "flex cursor-pointer items-center justify-center py-1",
      className
    )}
    {...props}
  >
    <ChevronUp className="h-4 w-4" />
  </SelectPrimitive.ScrollUpButton>
))
SelectScrollUpButton.displayName = SelectPrimitive.ScrollUpButton.displayName

const SelectScrollDownButton = React.forwardRef<
  React.ElementRef<typeof SelectPrimitive.ScrollDownButton>,
  React.ComponentPropsWithoutRef<typeof SelectPrimitive.ScrollDownButton>
>(({ className, ...props }, ref) => (
  <SelectPrimitive.ScrollDownButton
    ref={ref}
    className={cn(
      "flex cursor-pointer items-center justify-center py-1",
      className
    )}
    {...props}
  >
    <ChevronDown className="h-4 w-4" />
  </SelectPrimitive.ScrollDownButton>
))
SelectScrollDownButton.displayName =
  SelectPrimitive.ScrollDownButton.displayName

const SelectContent = React.forwardRef<
  React.ElementRef<typeof SelectPrimitive.Content>,
  React.ComponentPropsWithoutRef<typeof SelectPrimitive.Content> & {
    searchable?: boolean;
  }
>(({ className, children, position = "popper", searchable = false, onCloseAutoFocus, ...props }, ref) => {
  const [searchTerm, setSearchTerm] = React.useState('');
  const searchInputRef = React.useRef<HTMLInputElement>(null);

  React.useEffect(() => {
    if (searchable && searchInputRef.current) {
      const timer = setTimeout(() => searchInputRef.current?.focus(), 50);
      return () => clearTimeout(timer);
    }
  }, [searchable]);

  const filteredChildren = React.useMemo(() => {
    if (!searchable || !searchTerm) return children;
    return React.Children.toArray(children).filter((child) => {
      if (React.isValidElement(child) && child.type === SelectItem) {
        const childText = typeof child.props.children === 'string'
          ? child.props.children
          : child.props.children?.toString() || '';
        return childText.toLowerCase().includes(searchTerm.toLowerCase());
      }
      return true;
    });
  }, [children, searchTerm, searchable]);

  return (
    <SelectPrimitive.Portal>
      {/* No exit animation on purpose: while a closing Select animates out, its
          layer stays on top with outside pointer events disabled, so the next
          click (e.g. a dialog's Update button) was swallowed. */}
      <SelectPrimitive.Content
        ref={ref}
        className={cn(
          "relative z-[100] max-h-96 min-w-[8rem] overflow-hidden rounded-lg border border-ds-border-subtle bg-popover text-popover-foreground shadow-ds-md data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2",
          position === "popper" &&
            "data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1",
          className
        )}
        position={position}
        {...props}
        onCloseAutoFocus={(e) => {
          onCloseAutoFocus?.(e);
          // Radix returns focus to the trigger a tick after closing. If the user
          // has already clicked into another field, keep focus there instead of
          // pulling it back (typing right after picking an option went nowhere).
          const active = document.activeElement;
          if (active?.matches('input, textarea, select, button, [contenteditable="true"]')) e.preventDefault();
        }}
      >
        <SelectScrollUpButton />
        {searchable && (
          <div className="flex items-center border-b border-ds-border-subtle px-3 py-2" onPointerDown={(e) => e.preventDefault()}>
            <Search className="mr-2 h-4 w-4 shrink-0 text-ds-fg-muted" aria-hidden="true" />
            <input
              ref={searchInputRef}
              className="flex h-8 w-full bg-transparent py-3 text-sm text-ds-fg outline-none placeholder:text-ds-fg-placeholder"
              placeholder="Search..."
              aria-label="Search options"
              value={searchTerm}
              onChange={(e) => {
                setSearchTerm(e.target.value);
                setTimeout(() => {
                  if (searchInputRef.current) {
                    searchInputRef.current.focus();
                  }
                }, 0);
              }}
              onPointerDown={(e) => e.stopPropagation()}
              onKeyDown={(e) => {
                if (e.key === 'Escape') {
                  setSearchTerm('');
                  return;
                }
                // Let arrow/enter/home/end keys bubble up to Radix so the
                // filtered list stays keyboard-navigable from the search box.
                if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter'].includes(e.key)) {
                  return;
                }
                e.stopPropagation();
              }}
              onBlur={(e) => {
                const relatedTarget = e.relatedTarget as HTMLElement;
                if (relatedTarget && e.currentTarget.closest('[data-radix-select-content]')?.contains(relatedTarget)) {
                  e.preventDefault();
                  setTimeout(() => {
                    if (searchInputRef.current) {
                      searchInputRef.current.focus();
                    }
                  }, 0);
                }
              }}
            />
          </div>
        )}
        <SelectPrimitive.Viewport
          className={cn(
            "p-1.5",
            position === "popper" &&
              "h-[var(--radix-select-trigger-height)] w-full min-w-[var(--radix-select-trigger-width)]"
          )}
        >
          {searchable ? filteredChildren : children}
        </SelectPrimitive.Viewport>
        <SelectScrollDownButton />
      </SelectPrimitive.Content>
    </SelectPrimitive.Portal>
  );
})
SelectContent.displayName = SelectPrimitive.Content.displayName

const SelectLabel = React.forwardRef<
  React.ElementRef<typeof SelectPrimitive.Label>,
  React.ComponentPropsWithoutRef<typeof SelectPrimitive.Label>
>(({ className, ...props }, ref) => (
  <SelectPrimitive.Label
    ref={ref}
    className={cn("px-2.5 py-1.5 text-xs font-semibold text-ds-fg-muted", className)}
    {...props}
  />
))
SelectLabel.displayName = SelectPrimitive.Label.displayName

const SelectItem = React.forwardRef<
  React.ElementRef<typeof SelectPrimitive.Item>,
  React.ComponentPropsWithoutRef<typeof SelectPrimitive.Item>
>(({ className, children, ...props }, ref) => (
  <SelectPrimitive.Item
    ref={ref}
    className={cn(
      "relative flex min-h-8 w-full cursor-pointer select-none items-center rounded-sm py-1.5 pl-2.5 pr-8 text-sm text-ds-fg outline-none transition-colors focus:bg-ds-surface-hover data-[state=checked]:bg-ds-surface-selected data-[state=checked]:font-medium data-[state=checked]:text-ds-fg-accent data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
      className
    )}
    {...props}
  >
    <span className="absolute right-2.5 flex h-4 w-4 items-center justify-center">
      <SelectPrimitive.ItemIndicator>
        <Check className="h-4 w-4" aria-hidden="true" />
      </SelectPrimitive.ItemIndicator>
    </span>

    <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
  </SelectPrimitive.Item>
))
SelectItem.displayName = SelectPrimitive.Item.displayName

const SelectSeparator = React.forwardRef<
  React.ElementRef<typeof SelectPrimitive.Separator>,
  React.ComponentPropsWithoutRef<typeof SelectPrimitive.Separator>
>(({ className, ...props }, ref) => (
  <SelectPrimitive.Separator
    ref={ref}
    className={cn("-mx-1.5 my-1.5 h-px bg-ds-border-subtle", className)}
    {...props}
  />
))
SelectSeparator.displayName = SelectPrimitive.Separator.displayName

export {
  Select,
  SelectGroup,
  SelectValue,
  SelectTrigger,
  SelectContent,
  SelectLabel,
  SelectItem,
  SelectSeparator,
  SelectScrollUpButton,
  SelectScrollDownButton,
}