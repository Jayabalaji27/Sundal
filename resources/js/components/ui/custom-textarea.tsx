import { cn } from "@/lib/utils";
import * as React from "react";
import { Textarea } from "@headlessui/react";

export const CustomTextarea = ({className, ...props}: React.ComponentProps<"textarea">) => {
    return (
        <Textarea
            data-slot="textarea"
            className={cn(
                "flex w-full min-w-0 rounded-md border border-ds-border-strong bg-ds-surface px-3 py-2.5 text-base text-ds-fg shadow-ds-xs outline-none transition-[border-color,box-shadow] duration-150 ease-out md:text-sm",
                "placeholder:text-ds-fg-placeholder hover:border-ds-border-hover focus-visible:border-ds-border-focus focus-visible:ring-[3px] focus-visible:ring-brand-500/12",
                "disabled:cursor-not-allowed disabled:border-ds-border disabled:bg-ds-surface-sunken disabled:text-ds-fg-disabled disabled:shadow-none",
                "aria-invalid:border-ds-danger-input aria-invalid:focus-visible:ring-ds-danger-input/12",
            className
            )}
            {...props} />
    );
}
