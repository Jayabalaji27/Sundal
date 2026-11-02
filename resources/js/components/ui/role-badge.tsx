import * as React from "react"
import { Briefcase, Crown, User, Users } from "lucide-react"

import { cn } from "@/lib/utils"

/*
 * Workspace role tag. Roles are information, not status, so the tints stay
 * quieter than actions and status pills; each role also has its own icon.
 *
 *   <RoleBadge role="manager">{t('Manager')}</RoleBadge>
 */
const ROLE_STYLE: Record<string, { className: string; Icon: React.ElementType }> = {
  owner: { className: "bg-ds-warning-bg text-ds-warning-fg", Icon: Crown },
  manager: { className: "bg-brand-soft text-brand-soft-fg", Icon: Users },
  member: { className: "bg-ds-info-bg text-ds-info-fg", Icon: User },
  client: { className: "bg-ds-neutral-bg text-ds-neutral-fg", Icon: Briefcase },
}

function RoleBadge({
  role,
  className,
  children,
  ...props
}: React.ComponentProps<"span"> & { role: string }) {
  const { className: tone, Icon } = ROLE_STYLE[role] ?? { className: "bg-ds-neutral-bg text-ds-neutral-fg", Icon: User }

  return (
    <span
      data-slot="role-badge"
      className={cn(
        "inline-flex h-6 w-fit shrink-0 items-center gap-1.5 whitespace-nowrap rounded-sm px-2 text-xs font-medium",
        tone,
        className
      )}
      {...props}
    >
      <Icon className="size-3.5 shrink-0" aria-hidden="true" />
      {children}
    </span>
  )
}

export { RoleBadge }
