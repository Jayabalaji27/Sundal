import * as React from "react"
import * as ScrollAreaPrimitive from "@radix-ui/react-scroll-area"
import { useTranslation } from 'react-i18next'
import { useLayout } from '@/contexts/LayoutContext'

import { cn } from "@/lib/utils"

const ScrollArea = React.forwardRef<
  React.ElementRef<typeof ScrollAreaPrimitive.Root>,
  React.ComponentPropsWithoutRef<typeof ScrollAreaPrimitive.Root>
>(({ className, children, ...props }, ref) => {
  const { i18n } = useTranslation()
  const { position } = useLayout()
  const isRTL = ['ar', 'he'].includes(i18n.language) || position === 'right'
  
  return (
    <ScrollAreaPrimitive.Root
      ref={ref}
      className={cn("relative overflow-hidden", className)}
      {...props}
    >
      {/*
        Radix's Viewport wraps its child in an internal div with an inline
        `display:table` style (to measure scrollable content) which sizes
        that wrapper to its content's natural width instead of the
        viewport's — so text inside can silently overflow past the visible
        boundary with no ellipsis, since nothing else in the app uses
        ScrollArea's horizontal-scroll support. `[&>div]:!block` overrides
        that inline style (needs `!` to beat inline-style specificity) so
        content respects the actual container width again.
      */}
      <ScrollAreaPrimitive.Viewport className={cn("h-full w-full rounded-[inherit] [&>div]:!block", isRTL && "[direction:rtl]")}>
        {children}
      </ScrollAreaPrimitive.Viewport>
      <ScrollBar />
      <ScrollAreaPrimitive.Corner />
    </ScrollAreaPrimitive.Root>
  )
})
ScrollArea.displayName = ScrollAreaPrimitive.Root.displayName

const ScrollBar = React.forwardRef<
  React.ElementRef<typeof ScrollAreaPrimitive.ScrollAreaScrollbar>,
  React.ComponentPropsWithoutRef<typeof ScrollAreaPrimitive.ScrollAreaScrollbar>
>(({ className, orientation = "vertical", ...props }, ref) => (
  <ScrollAreaPrimitive.ScrollAreaScrollbar
    ref={ref}
    orientation={orientation}
    className={cn(
      "flex touch-none select-none transition-colors",
      orientation === "vertical" &&
        "h-full w-2.5 border-l border-l-transparent p-[1px]",
      orientation === "horizontal" &&
        "h-2.5 border-t border-t-transparent p-[1px]",
      className
    )}
    {...props}
  >
    <ScrollAreaPrimitive.ScrollAreaThumb className="relative flex-1 rounded-full bg-border" />
  </ScrollAreaPrimitive.ScrollAreaScrollbar>
))
ScrollBar.displayName = ScrollAreaPrimitive.ScrollAreaScrollbar.displayName

export { ScrollArea, ScrollBar }