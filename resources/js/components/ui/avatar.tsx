import * as React from "react"
import * as AvatarPrimitive from "@radix-ui/react-avatar"

import { avatarTone, initialsOf } from "@/lib/avatar-tone"
import { cn } from "@/lib/utils"

function Avatar({
  className,
  ...props
}: React.ComponentProps<typeof AvatarPrimitive.Root>) {
  return (
    <AvatarPrimitive.Root
      data-slot="avatar"
      className={cn(
        "relative flex size-8 shrink-0 overflow-hidden rounded-full",
        className
      )}
      {...props}
    />
  )
}

function AvatarImage({
  className,
  ...props
}: React.ComponentProps<typeof AvatarPrimitive.Image>) {
  return (
    <AvatarPrimitive.Image
      data-slot="avatar-image"
      className={cn("aspect-square size-full object-cover", className)}
      {...props}
    />
  )
}

function AvatarFallback({
  className,
  seed,
  children,
  ...props
}: React.ComponentProps<typeof AvatarPrimitive.Fallback> & {
  /** What picks the tint (usually the person's name). Defaults to the initials shown. */
  seed?: string
}) {
  const toneSeed = seed ?? (typeof children === "string" ? children : undefined)

  return (
    <AvatarPrimitive.Fallback
      data-slot="avatar-fallback"
      className={cn(
        "flex size-full items-center justify-center rounded-full font-semibold",
        toneSeed !== undefined ? avatarTone(toneSeed) : "bg-ds-neutral-bg text-ds-neutral-fg",
        className
      )}
      {...props}
    >
      {children}
    </AvatarPrimitive.Fallback>
  )
}

const USER_AVATAR_SIZE = {
  sm: "size-8 text-xs",
  md: "size-9 text-sm",
  lg: "size-11 text-base",
} as const

/**
 * A person's avatar: their photo, or tinted initials when there is none.
 * Sizes follow the design system: sm 32px, md 36px, lg 44px.
 */
function UserAvatar({
  name,
  src,
  size = "md",
  className,
}: {
  name: string | null | undefined
  src?: string | null
  size?: keyof typeof USER_AVATAR_SIZE
  className?: string
}) {
  return (
    <Avatar className={cn(USER_AVATAR_SIZE[size], className)}>
      {src && <AvatarImage src={src} alt="" />}
      <AvatarFallback seed={name ?? ""} aria-hidden="true">
        {initialsOf(name)}
      </AvatarFallback>
    </Avatar>
  )
}

export { Avatar, AvatarImage, AvatarFallback, UserAvatar }
