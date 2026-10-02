import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { ReactNode } from 'react';
import { FloatingChatGpt } from '@/components/FloatingChatGpt';

export interface PageAction {
  label: string;
  icon?: ReactNode;
  variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  size?: 'default' | 'sm' | 'lg' | 'icon';
  tooltip?: string;
  className?: string;
  onClick?: () => void;
}

export interface PageTemplateProps {
  title: string;
  description?: string;
  url?: string;
  actions?: PageAction[];
  children: ReactNode;
  noPadding?: boolean;
  breadcrumbs?: BreadcrumbItem[];
}

export function PageTemplate({ 
  title,
  description, 
  url, 
  actions, 
  children, 
  noPadding = false,
  breadcrumbs
}: PageTemplateProps) {
  // Default breadcrumbs if none provided
  const pageBreadcrumbs: BreadcrumbItem[] = breadcrumbs || [
    {
      title,
      href: url,
    },
  ];

  return (
    <AppLayout breadcrumbs={pageBreadcrumbs}>
      <Head title={`${title} - ${(usePage().props as any).globalSettings?.titleText || 'Taskly'}`} />
      
      <div className="flex flex-1 flex-col gap-4 p-4 px-4 pb-[50px] sm:px-6 lg:px-[50px]">
        {/* Header with action buttons */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-xl font-semibold truncate">{title}</h1>
          {actions && actions.length > 0 && (
            <div className="flex flex-wrap items-center gap-2">
              {actions.map((action, index) => {
                const button = (
                  <Button 
                    key={index}
                    variant={action.variant || 'outline'} 
                    size={action.size || 'sm'}
                    className={action.className}
                    onClick={action.onClick}
                  >
                    {action.icon}
                    {action.size !== 'icon' && action.label}
                  </Button>
                );
                
                if (action.tooltip) {
                  return (
                    <Tooltip key={index}>
                      <TooltipTrigger asChild>
                        {button}
                      </TooltipTrigger>
                      <TooltipContent>
                        {action.tooltip}
                      </TooltipContent>
                    </Tooltip>
                  );
                }
                
                return button;
              })}
            </div>
          )}
        </div>
        
        {/* Content */}
        <div className={noPadding ? "" : "rounded-xl border p-6"}>
          {children}
        </div>
      </div>
      <FloatingChatGpt />
    </AppLayout>
  );
}