import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { MoreHorizontal } from 'lucide-react';
import { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
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
  /** Custom header controls shown before the action buttons (e.g. a download menu). */
  toolbar?: ReactNode;
  children: ReactNode;
  noPadding?: boolean;
  breadcrumbs?: BreadcrumbItem[];
}

/**
 * A page's header buttons. Keeps the header to the page's main action: with
 * more than two actions, only the primary (variant "default") ones stay as
 * buttons and the rest move into a "more" menu.
 */
export function PageActions({ actions = [] }: { actions?: PageAction[] }) {
  const { t } = useTranslation();

  const primaryActions = actions.filter(action => action.variant === 'default');
  const visibleActions = actions.length <= 2
    ? actions
    : (primaryActions.length > 0 ? primaryActions : actions.slice(-1));
  const overflowActions = actions.filter(action => !visibleActions.includes(action));

  if (actions.length === 0) return null;

  return (
    <div className="flex flex-wrap items-center gap-2">
      {overflowActions.length > 0 && (
        <DropdownMenu>
          <Tooltip>
            <TooltipTrigger asChild>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="icon" aria-label={t('More actions')}>
                  <MoreHorizontal />
                </Button>
              </DropdownMenuTrigger>
            </TooltipTrigger>
            <TooltipContent>{t('More actions')}</TooltipContent>
          </Tooltip>
          <DropdownMenuContent align="end" className="min-w-44">
            {overflowActions.map((action, index) => (
              <DropdownMenuItem key={index} onClick={action.onClick} className="[&_svg]:mr-0 gap-2">
                {action.icon}
                {action.label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
      {visibleActions.map((action, index) => {
        const button = (
          <Button
            key={index}
            variant={action.variant || 'outline'}
            size={action.size || 'default'}
            className={action.className}
            onClick={action.onClick}
            aria-label={action.size === 'icon' ? action.label : undefined}
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
  );
}

export function PageTemplate({
  title,
  description,
  url,
  actions,
  toolbar,
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
      <Head title={`${title} - ${(usePage().props as any).globalSettings?.titleText || (usePage().props as any).name}`} />

      <div className="flex flex-1 flex-col gap-5 px-4 pt-5 pb-10 sm:px-6 lg:px-8">
        {/* Header: title and description on the left, the page's actions on the right */}
        <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
          <div className="min-w-0">
            <h1 className="truncate text-2xl font-bold tracking-tight text-ds-fg sm:text-ds-page-title">{title}</h1>
            {description && <p className="mt-1 text-sm text-ds-fg-secondary">{description}</p>}
          </div>
          {toolbar ? (
            <div className="flex flex-wrap items-center gap-2">
              {toolbar}
              <PageActions actions={actions} />
            </div>
          ) : (
            <PageActions actions={actions} />
          )}
        </div>

        {/* Content */}
        <div className={noPadding ? "" : "rounded-xl border border-ds-border bg-ds-surface px-5 py-4 shadow-ds-xs"}>
          {children}
        </div>
      </div>
      <FloatingChatGpt />
    </AppLayout>
  );
}
