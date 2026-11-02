import * as React from "react"

import { Label } from "@/components/ui/label"
import { cn } from "@/lib/utils"

/**
 * A labelled form control with an optional hint and an inline error.
 * Wires up the id, aria-invalid and aria-describedby on the single child
 * control, so the label, hint and error are announced with it.
 *
 *   <FormField id="email" label={t('Email address')} error={errors.email}>
 *     <Input type="email" value={email} onChange={...} />
 *   </FormField>
 */
function FormField({
  id,
  label,
  hint,
  error,
  required,
  className,
  children,
}: {
  id: string
  label: React.ReactNode
  hint?: React.ReactNode
  error?: React.ReactNode
  required?: boolean
  className?: string
  children: React.ReactElement<{
    id?: string
    required?: boolean
    "aria-invalid"?: boolean | "true" | "false"
    "aria-describedby"?: string
  }>
}) {
  const hintId = hint ? `${id}-hint` : undefined
  const errorId = error ? `${id}-error` : undefined
  const describedBy = [errorId, hintId, children.props["aria-describedby"]].filter(Boolean).join(" ") || undefined

  return (
    <div className={cn("flex flex-col gap-1.5", className)}>
      <Label htmlFor={id} required={required}>
        {label}
      </Label>
      {React.cloneElement(children, {
        id,
        required: children.props.required ?? required,
        "aria-invalid": error ? true : children.props["aria-invalid"],
        "aria-describedby": describedBy,
      })}
      {error ? (
        <p id={errorId} className="text-sm text-ds-danger-fg">
          {error}
        </p>
      ) : (
        hint && (
          <p id={hintId} className="text-sm text-ds-fg-muted">
            {hint}
          </p>
        )
      )}
    </div>
  )
}

export { FormField }
