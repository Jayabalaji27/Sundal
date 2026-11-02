import * as React from "react"
import { Search, X } from "lucide-react"
import { useTranslation } from "react-i18next"

import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"

/**
 * Search field: icon on the left, a clear button once something is typed.
 * Filters as you type; for server-side searches debounce `onChange` upstream.
 */
function SearchInput({
  value,
  onChange,
  placeholder,
  className,
  inputClassName,
  "aria-label": ariaLabel,
  ...props
}: Omit<React.ComponentProps<"input">, "value" | "onChange"> & {
  value: string
  onChange: (value: string) => void
  inputClassName?: string
}) {
  const { t } = useTranslation()
  const inputRef = React.useRef<HTMLInputElement>(null)

  return (
    <div className={cn("relative w-full", className)}>
      <Search
        className="pointer-events-none absolute top-1/2 left-3 size-[18px] -translate-y-1/2 text-ds-fg-muted rtl:right-3 rtl:left-auto"
        aria-hidden="true"
      />
      <Input
        ref={inputRef}
        type="search"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        aria-label={ariaLabel ?? placeholder}
        className={cn("pl-10 pr-9 rtl:pr-10 rtl:pl-9 [&::-webkit-search-cancel-button]:appearance-none", inputClassName)}
        {...props}
      />
      {value && (
        <button
          type="button"
          onClick={() => {
            onChange("")
            inputRef.current?.focus()
          }}
          className="absolute top-1/2 right-1.5 inline-flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-sm text-ds-fg-muted transition-colors hover:bg-ds-neutral-bg hover:text-ds-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus rtl:right-auto rtl:left-1.5"
          aria-label={t("Clear search")}
        >
          <X className="size-4" aria-hidden="true" />
        </button>
      )}
    </div>
  )
}

export { SearchInput }
