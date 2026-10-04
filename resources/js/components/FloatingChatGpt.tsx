import { useState, useEffect } from 'react';
import { Brain } from 'lucide-react';
import { ChatGptModal } from '@/components/chatgpt';
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/react';
import { useModalStack } from '@/contexts/ModalStackContext';

export function FloatingChatGpt() {
  const { auth, isSaasMode, globalSettings } = usePage().props as any;
  const [isOpen, setIsOpen] = useState(false);
  const [generatedContent, setGeneratedContent] = useState('');
  // Stays above dialogs on purpose (usable from inside a form), so while one is
  // open move it to the top-right, clear of dialog footer buttons.
  const { modalStack } = useModalStack();
  const dialogOpen = modalStack.length > 0 && !isOpen;
  
  // Check if user can access ChatGPT
  const userRole = auth?.roles?.[0] || auth?.user?.type;
  const isSuperAdmin = userRole === 'superadmin' || auth?.user?.type === 'superadmin';
  const isCompany = auth?.user?.type === 'company';
  
  let canUseChatGPT = false;
  
  if (isSaasMode) {
    // SaaS mode: Check plan-based access
    if (isSuperAdmin) {
      canUseChatGPT = true;
    } else if (isCompany) {
      // For company users, check their own plan
      const hasActivePlan = auth?.user?.plan_is_active === 1 && auth?.user?.plan;
      canUseChatGPT = hasActivePlan && auth?.user?.plan?.enable_chatgpt === 'on';
    } else {
      // For other users, check the plan of the company user who created them
      const creator = auth?.user?.creator;
      const hasActivePlan = creator?.plan_is_active === 1 && creator?.plan;
      canUseChatGPT = hasActivePlan && creator?.plan?.enable_chatgpt === 'on';
    }
  } else {
    // Non-SaaS mode: Check if ChatGPT is configured
    const hasChatGptKey = globalSettings?.chatgptKey && globalSettings.chatgptKey.length > 0;
    canUseChatGPT = hasChatGptKey;
  }
  
  // Don't render if user doesn't have access
  if (!canUseChatGPT) {
    return null;
  }
  
  useEffect(() => {
  }, [isOpen]);

  const handleGenerate = (content: string) => {
    setGeneratedContent(content);
    // You can add additional logic here if needed
  };

  const handleModalOpen = () => {
    setIsOpen(true);
  };

  const handleModalClose = () => {
    setIsOpen(false);
  };

  return (
    <>
      <div 
        className={`fixed right-6 z-[9999] ${dialogOpen ? 'top-20' : 'bottom-6'}`}
        onClickCapture={(e) => {
          e.preventDefault();
          e.stopPropagation();
          e.nativeEvent.stopImmediatePropagation();
          handleModalOpen();
        }}
        onMouseDownCapture={(e) => {
          e.preventDefault();
          e.stopPropagation();
        }}
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
        }}
      >
        <Button
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            handleModalOpen();
          }}
          className="h-14 w-14 rounded-full shadow-lg hover:shadow-xl transition-shadow"
          size="lg"
        >
          <Brain className="h-6 w-6" />
        </Button>
      </div>

      <ChatGptModal
        isOpen={isOpen}
        onClose={handleModalClose}
        onGenerate={handleGenerate}
        title="AI Assistant"
        placeholder="What would you like me to help you generate?"
      />
    </>
  );
}