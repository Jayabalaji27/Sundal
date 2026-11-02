import * as React from "react"
import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import { Loader2 } from "lucide-react"

import { cn } from "@/lib/utils"

/*
 * Button hierarchy (design system):
 *   default      PrimaryButton   - the one main action in a section
 *   outline      SecondaryButton - Manage, Edit, Cancel
 *   ghost        TertiaryButton  - View details, Resend, More
 *   danger       DangerButton    - everyday destructive actions (outlined red)
 *   destructive                  - solid red, only for the final confirmation step
 *   secondary                    - quiet grey fill
 *   link                         - inline text action
 *
 * The icon-to-label space comes from gap-2; [&>svg]:mr-0 cancels the mr-2
 * many older call sites put on a leading icon, which doubled it.
 */
const buttonVariants = cva(
  "relative inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-semibold select-none outline-none transition-[background-color,border-color,color,box-shadow,transform] duration-150 ease-out active:translate-y-px disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none aria-busy:pointer-events-none focus-visible:ring-2 focus-visible:ring-ds-border-focus focus-visible:ring-offset-2 focus-visible:ring-offset-ds-surface aria-invalid:border-ds-danger-input [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 [&>svg]:mr-0",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-ds-xs hover:bg-brand-700 active:bg-brand-800",
        destructive:
          "bg-ds-danger text-white shadow-ds-xs hover:bg-ds-danger-hover focus-visible:ring-ds-danger",
        danger:
          "border border-ds-danger-border bg-ds-surface text-ds-danger-fg shadow-ds-xs hover:bg-ds-danger-bg focus-visible:ring-ds-danger",
        outline:
          "border border-ds-border-strong bg-ds-surface text-ds-fg-control shadow-ds-xs hover:border-ds-border-control-hover hover:bg-ds-button-hover hover:text-ds-fg",
        secondary:
          "bg-ds-neutral-bg text-ds-fg-control hover:bg-ds-border-subtle hover:text-ds-fg",
        ghost:
          "text-ds-fg-secondary hover:bg-ds-neutral-bg hover:text-ds-fg",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-9 px-3.5 has-[>svg]:px-3",
        sm: "h-8 gap-1.5 px-3 has-[>svg]:px-2.5",
        lg: "h-10 px-5 has-[>svg]:px-4",
        icon: "size-9 [&_svg:not([class*='size-'])]:size-[18px]",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

type ButtonProps = React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
    /** Shows a spinner, keeps the button's width and blocks repeat clicks. */
    loading?: boolean
    /** Label shown while loading, e.g. "Sending..." (defaults to the spinner only). */
    loadingText?: React.ReactNode
  }

function Button({
  className,
  variant,
  size,
  asChild = false,
  loading = false,
  loadingText,
  disabled,
  children,
  ...props
}: ButtonProps) {
  const Comp = asChild ? Slot : "button"
  const showLoading = loading && !asChild

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      disabled={asChild ? disabled : disabled || loading}
      aria-busy={loading || undefined}
      {...props}
    >
      {showLoading ? (
        <>
          {/* The real label stays in place (invisible) so the width doesn't jump. */}
          <span className="invisible inline-flex items-center gap-2">{children}</span>
          <span className="absolute inset-0 inline-flex items-center justify-center gap-2">
            <Loader2 className="animate-spin" aria-hidden="true" />
            {loadingText}
          </span>
        </>
      ) : (
        children
      )}
    </Comp>
  )
}

type FamilyButtonProps = Omit<ButtonProps, "variant">

/** The single most important action in a section. */
function PrimaryButton(props: FamilyButtonProps) {
  return <Button variant="default" {...props} />
}

/** Manage, Edit, Cancel: white with a subtle border. */
function SecondaryButton(props: FamilyButtonProps) {
  return <Button variant="outline" {...props} />
}

/** Low-priority actions such as View details or Resend. */
function TertiaryButton(props: FamilyButtonProps) {
  return <Button variant="ghost" {...props} />
}

/**
 * A destructive action. Outlined red by default; pass `confirm` for the solid
 * red button on the final confirmation step (e.g. inside the confirm dialog).
 */
function DangerButton({ confirm = false, ...props }: FamilyButtonProps & { confirm?: boolean }) {
  return <Button variant={confirm ? "destructive" : "danger"} {...props} />
}

/** Icon-only button with a 36px hit area. `aria-label` is required. */
function IconButton({
  variant = "ghost",
  size = "icon",
  ...props
}: ButtonProps & { "aria-label": string }) {
  return <Button variant={variant} size={size} {...props} />
}

export {
  Button,
  buttonVariants,
  PrimaryButton,
  SecondaryButton,
  TertiaryButton,
  DangerButton,
  IconButton,
}
export type { ButtonProps }
