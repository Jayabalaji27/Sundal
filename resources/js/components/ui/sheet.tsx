import * as React from "react"
import * as SheetPrimitive from "@radix-ui/react-dialog"
import { XIcon } from "lucide-react"

import { cn } from "@/lib/utils"
import { useModalStack } from "@/contexts/ModalStackContext"

function Sheet({ modal = true, open, ...props }: React.ComponentProps<typeof SheetPrimitive.Root>) {
  const [isChatGptOpen, setIsChatGptOpen] = React.useState(false);

  React.useEffect(() => {
    const isSheetOpen = open !== undefined ? open : (props as any).defaultOpen;
    if (!isSheetOpen && open === undefined) return;

    const checkChatGpt = () => {
      const chatGptModal = document.querySelector('[data-chatgpt-modal="true"]');
      setIsChatGptOpen(!!chatGptModal);
    };

    checkChatGpt();

    const observer = new MutationObserver(checkChatGpt);
    observer.observe(document.body, { 
      childList: true, 
      subtree: true,
      attributeFilter: ['data-chatgpt-modal']
    });

    return () => observer.disconnect();
  }, [open, (props as any).defaultOpen]);

  return <SheetPrimitive.Root data-slot="sheet" modal={isChatGptOpen ? false : modal} open={open} {...props} />
}

function SheetTrigger({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Trigger>) {
  return <SheetPrimitive.Trigger data-slot="sheet-trigger" {...props} />
}

function SheetClose({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Close>) {
  return <SheetPrimitive.Close data-slot="sheet-close" {...props} />
}

function SheetPortal({
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Portal>) {
  return <SheetPrimitive.Portal data-slot="sheet-portal" {...props} />
}

function SheetOverlay({
  className,
  modalId,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Overlay> & { modalId?: string }) {
  const { getZIndex, modalStack } = useModalStack();
  const zIndex = modalId ? getZIndex(modalId) : 50;
  const modalIndex = modalStack.indexOf(modalId || "");
  const isFirstModal = modalIndex <= 0;

  return (
    <SheetPrimitive.Overlay
      data-slot="sheet-overlay"
      className={cn(
        "data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0",
        isFirstModal ? "bg-black/30" : "bg-black/25",
        className
      )}
      style={{ zIndex }}
      onPointerDown={(e) => {
        // Allow clicks on elements with higher z-index (like FloatingChatGpt)
        const target = e.target as HTMLElement;
        const targetZIndex = parseInt(window.getComputedStyle(target).zIndex) || 0;
        if (targetZIndex > zIndex) {
          return;
        }
        // Allow ChatGPT button clicks
        if (target.closest('[data-chatgpt-button]')) {
          e.stopPropagation();
          return;
        }
      }}
      {...props}
    />
  )
}

function SheetContent({
  className,
  children,
  side = "right",
  modalId,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Content> & {
  side?: "top" | "right" | "bottom" | "left"
  modalId?: string
}) {
  const { registerModal, unregisterModal, getZIndex, modalStack } = useModalStack();
  const [currentModalId] = React.useState(() => modalId || `sheet-${Date.now()}-${Math.random()}`);

  React.useEffect(() => {
    registerModal(currentModalId);
    return () => unregisterModal(currentModalId);
  }, [currentModalId, registerModal, unregisterModal]);

  const zIndex = getZIndex(currentModalId);

  return (
    <SheetPortal>
      <SheetOverlay modalId={currentModalId} />
      <SheetPrimitive.Content
        data-slot="sheet-content"
        className={cn(
          "bg-background data-[state=open]:animate-in data-[state=closed]:animate-out fixed z-50 flex flex-col gap-4 shadow-lg transition ease-in-out data-[state=closed]:duration-300 data-[state=open]:duration-500",
          side === "right" &&
            "data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right inset-y-0 right-0 h-full w-3/4 border-l sm:max-w-sm",
          side === "left" &&
            "data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left inset-y-0 left-0 h-full w-3/4 border-r sm:max-w-sm",
          side === "top" &&
            "data-[state=closed]:slide-out-to-top data-[state=open]:slide-in-from-top inset-x-0 top-0 h-auto border-b",
          side === "bottom" &&
            "data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom inset-x-0 bottom-0 h-auto border-t",
          className
        )}
        style={{ zIndex: zIndex + 1 }}
        onPointerDownOutside={(e) => {
          const target = e.target as Element;
          if (target.closest('[data-chatgpt-button]') || target.closest('[data-chatgpt-modal]')) {
            e.preventDefault();
            return;
          }
          // Prevent closing when clicking outside
          e.preventDefault();
        }}
        onInteractOutside={(e) => {
          const target = e.target as Element;
          if (target.closest('[data-chatgpt-button]') || target.closest('[data-chatgpt-modal]')) {
            e.preventDefault();
            return;
          }
          // Prevent closing when interacting outside
          e.preventDefault();
        }}
        onEscapeKeyDown={(e) => {
          // Only close if this is the topmost modal
          if (modalStack[modalStack.length - 1] !== currentModalId) {
            e.preventDefault();
          }
        }}
        {...props}
      >
        {children}
        <SheetPrimitive.Close className="ring-offset-background focus:ring-ring data-[state=open]:bg-secondary absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none cursor-pointer">
          <XIcon className="size-4" />
          <span className="sr-only">Close</span>
        </SheetPrimitive.Close>
      </SheetPrimitive.Content>
    </SheetPortal>
  )
}

function SheetHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-header"
      className={cn("flex flex-col gap-1.5 p-4", className)}
      {...props}
    />
  )
}

function SheetFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="sheet-footer"
      className={cn("mt-auto flex flex-col gap-2 p-4", className)}
      {...props}
    />
  )
}

function SheetTitle({
  className,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Title>) {
  return (
    <SheetPrimitive.Title
      data-slot="sheet-title"
      className={cn("text-foreground font-semibold", className)}
      {...props}
    />
  )
}

function SheetDescription({
  className,
  ...props
}: React.ComponentProps<typeof SheetPrimitive.Description>) {
  return (
    <SheetPrimitive.Description
      data-slot="sheet-description"
      className={cn("text-muted-foreground text-sm", className)}
      {...props}
    />
  )
}

export {
  Sheet,
  SheetTrigger,
  SheetClose,
  SheetContent,
  SheetHeader,
  SheetFooter,
  SheetTitle,
  SheetDescription,
}
