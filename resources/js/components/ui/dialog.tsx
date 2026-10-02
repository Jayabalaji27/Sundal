import * as React from "react"
import * as DialogPrimitive from "@radix-ui/react-dialog"
import { X } from "lucide-react"

import { cn } from "@/lib/utils"
import { useModalStack } from "@/contexts/ModalStackContext"

const Dialog = ({ onOpenChange, modal = true, open, ...props }: React.ComponentPropsWithoutRef<typeof DialogPrimitive.Root>) => {
  const [isChatGptOpen, setIsChatGptOpen] = React.useState(false);

  React.useEffect(() => {
    // If dialog is not open, no need to monitor
    const isDialogOpen = open !== undefined ? open : (props as any).defaultOpen;
    if (!isDialogOpen) return;

    const checkChatGpt = () => {
      const chatGptModal = document.querySelector('[data-chatgpt-modal="true"]');
      setIsChatGptOpen(!!chatGptModal);
    };

    // Initial check
    checkChatGpt();

    // Set up observer to watch for ChatGPT modal appearing/disappearing
    const observer = new MutationObserver(checkChatGpt);
    observer.observe(document.body, { 
      childList: true, 
      subtree: true,
      attributeFilter: ['data-chatgpt-modal']
    });

    return () => observer.disconnect();
  }, [open, (props as any).defaultOpen]);

  const handleOpenChange = (open: boolean) => {
    // Only allow manual close, not auto-close from focus loss
    if (!open && onOpenChange) {
      // Add a small delay to prevent immediate close when another modal opens
      setTimeout(() => {
        if (onOpenChange) onOpenChange(open);
      }, 100);
    } else if (open && onOpenChange) {
      onOpenChange(open);
    }
  };
  
  return (
    <DialogPrimitive.Root 
      {...props} 
      open={open}
      modal={isChatGptOpen ? false : modal} 
      onOpenChange={handleOpenChange} 
    />
  );
};
Dialog.displayName = "Dialog"

const DialogTrigger = DialogPrimitive.Trigger

const DialogPortal = DialogPrimitive.Portal

const DialogClose = DialogPrimitive.Close

const DialogOverlay = React.forwardRef<
  React.ElementRef<typeof DialogPrimitive.Overlay>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay> & { modalId?: string }
>(({ className, modalId, ...props }, ref) => {
  const { getZIndex, modalStack } = useModalStack();
  const zIndex = modalId ? getZIndex(modalId) : 50;
  const modalIndex = modalStack.indexOf(modalId || '');
  const isFirstModal = modalIndex <= 0;
  
  return (
    <DialogPrimitive.Overlay
      ref={ref}
      className={cn(
        "fixed inset-0 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0",
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
  );
})
DialogOverlay.displayName = DialogPrimitive.Overlay.displayName

const DialogContent = React.forwardRef<
  React.ElementRef<typeof DialogPrimitive.Content>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content> & { modalId?: string }
>(({ className, children, modalId, onPointerDownOutside, ...props }, ref) => {
  const { registerModal, unregisterModal, getZIndex, modalStack } = useModalStack();
  const [currentModalId] = React.useState(() => modalId || `modal-${Date.now()}-${Math.random()}`);
  
  React.useEffect(() => {
    registerModal(currentModalId);
    return () => unregisterModal(currentModalId);
  }, [currentModalId, registerModal, unregisterModal]);
  
  const zIndex = getZIndex(currentModalId);
  
  return (
    <DialogPortal>
      <DialogOverlay modalId={currentModalId} />
      <DialogPrimitive.Content
        ref={ref}
        className={cn(
          "fixed left-[50%] top-[50%] grid w-full max-w-lg translate-x-[-50%] translate-y-[-50%] gap-4 border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] sm:rounded-lg pointer-events-auto",
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
          if (onPointerDownOutside) onPointerDownOutside(e);
        }}
        onFocusOutside={(e) => e.preventDefault()}
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
          // If ChatGPT modal is open, block ESC on this dialog - let ChatGPT handle it
          const chatGptModal = document.querySelector('[data-chatgpt-modal="true"]');
          if (chatGptModal) {
            e.preventDefault();
          }
        }}
        {...props}
      >
        {children}
        <DialogPrimitive.Close className="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none data-[state=open]:bg-accent data-[state=open]:text-muted-foreground cursor-pointer">
          <X className="h-4 w-4" />
          <span className="sr-only">Close</span>
        </DialogPrimitive.Close>
      </DialogPrimitive.Content>
    </DialogPortal>
  );
})
DialogContent.displayName = DialogPrimitive.Content.displayName

const DialogHeader = ({
  className,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) => (
  <div
    className={cn(
      "flex flex-col space-y-1.5 text-center sm:text-left",
      className
    )}
    {...props}
  />
)
DialogHeader.displayName = "DialogHeader"

const DialogFooter = ({
  className,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) => (
  <div
    className={cn(
      "flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:space-x-2 sm:gap-0",
      className
    )}
    {...props}
  />
)
DialogFooter.displayName = "DialogFooter"

const DialogTitle = React.forwardRef<
  React.ElementRef<typeof DialogPrimitive.Title>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({ className, ...props }, ref) => (
  <DialogPrimitive.Title
    ref={ref}
    className={cn(
      "text-lg font-semibold leading-none tracking-tight",
      className
    )}
    {...props}
  />
))
DialogTitle.displayName = DialogPrimitive.Title.displayName

const DialogDescription = React.forwardRef<
  React.ElementRef<typeof DialogPrimitive.Description>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Description>
>(({ className, ...props }, ref) => (
  <DialogPrimitive.Description
    ref={ref}
    className={cn("text-sm text-muted-foreground", className)}
    {...props}
  />
))
DialogDescription.displayName = DialogPrimitive.Description.displayName

export {
  Dialog,
  DialogPortal,
  DialogOverlay,
  DialogClose,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogFooter,
  DialogTitle,
  DialogDescription,
}