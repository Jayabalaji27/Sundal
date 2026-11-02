import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useSidebar } from '@/components/ui/sidebar';
import { Building2, Check, ChevronsUpDown, LogOut } from 'lucide-react';
import { usePage, router } from '@inertiajs/react';
import { hasPermission } from '@/utils/permissions';
import { LeaveWorkspaceModal } from '@/components/LeaveWorkspaceModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

/**
 * In the sidebar the menu is exactly as wide as the picker, so it stays inside
 * the sidebar (a fixed width spilled over the page). With the sidebar collapsed
 * to icons it opens beside the icon instead.
 */
function SidebarMenuContent({ children }: { children: React.ReactNode }) {
    const { state, isMobile } = useSidebar();
    const collapsed = state === 'collapsed' && !isMobile;

    return (
        <DropdownMenuContent
            className={collapsed ? 'w-64' : 'w-(--radix-dropdown-menu-trigger-width)'}
            side={collapsed ? 'right' : 'bottom'}
            align="start"
        >
            {children}
        </DropdownMenuContent>
    );
}

function HeaderMenuContent({ children }: { children: React.ReactNode }) {
    return <DropdownMenuContent className="w-64" align="end">{children}</DropdownMenuContent>;
}

/** In the sidebar (under the logo) it spans the width, like most apps' workspace pickers. */
export const WorkspaceSwitcher: React.FC<{ variant?: 'header' | 'sidebar' }> = ({ variant = 'header' }) => {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const currentWorkspaceId = auth?.user?.current_workspace_id;
    const ownedWorkspaces = auth?.user?.ownedWorkspaces || [];
    const memberWorkspaces = auth?.user?.workspaces || [];
    const [leaveWorkspace, setLeaveWorkspace] = useState<any>(null);

    // Combine owned and member workspaces
    const allWorkspaces = [
        ...ownedWorkspaces.map((ws: any) => ({ ...ws, is_owner: true })),
        ...memberWorkspaces.filter((ws: any) => !ownedWorkspaces.find((owned: any) => owned.id === ws.id))
            .map((ws: any) => ({ ...ws, is_owner: false }))
    ];
    
    const currentWorkspace = allWorkspaces.find(ws => ws.id === currentWorkspaceId);
    const MenuContent = variant === 'sidebar' ? SidebarMenuContent : HeaderMenuContent;

    if (!auth?.user || !hasPermission('workspace_switch') || allWorkspaces.length <= 1) {
        return null;
    }

    const switchTo = (workspace: { id: number }) => router.post(route('workspaces.switch', workspace.id), {}, {
        onSuccess: (page) => {
            const flash = (page.props as any).flash;
            if (flash?.error) {
                toast.error(flash.error);
                return;
            }
            const newGlobalSettings = (page.props as any).globalSettings;
            const newWorkspaceId = (page.props as any).auth?.user?.current_workspace_id;
            // Update window.page so use-appearance reads correct settings
            (window as any).page = page;
            window.dispatchEvent(new CustomEvent('workspaceSwitched', {
                detail: { globalSettings: newGlobalSettings, workspaceId: newWorkspaceId }
            }));
        }
    });

    return (
        <>
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {variant === 'sidebar' ? (
                    <Button
                        variant="outline"
                        className="h-9 w-full justify-start gap-2 px-3 font-medium group-data-[collapsible=icon]:w-9 group-data-[collapsible=icon]:shrink-0 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0"
                        title={currentWorkspace?.name}
                        aria-label={t('Switch workspace')}
                    >
                        <Building2 className="h-4 w-4 flex-shrink-0 text-ds-fg-muted" />
                        <span className="min-w-0 flex-1 truncate text-left text-sm text-ds-fg group-data-[collapsible=icon]:hidden">
                            {currentWorkspace?.name || t('Select Workspace')}
                        </span>
                        <ChevronsUpDown className="h-3.5 w-3.5 shrink-0 text-ds-fg-muted group-data-[collapsible=icon]:hidden" />
                    </Button>
                ) : (
                    <Button variant="ghost" className="flex items-center gap-2 max-w-40" aria-label={t('Switch workspace')}>
                        <Building2 className="h-4 w-4 flex-shrink-0" />
                        <span className="text-sm hidden md:inline-block truncate">
                            {currentWorkspace?.name || t('Select Workspace')}
                        </span>
                    </Button>
                )}
            </DropdownMenuTrigger>
            <MenuContent>
                {allWorkspaces.map((workspace) => (
                    // Selecting the item (click or Enter) switches; the leave button stops its own click.
                    <DropdownMenuItem
                        key={workspace.id}
                        className="flex items-center justify-between gap-2"
                        onSelect={() => switchTo(workspace)}
                        aria-current={currentWorkspaceId === workspace.id ? 'true' : undefined}
                    >
                        <div className="flex min-w-0 flex-1 items-center gap-2">
                            <div className="min-w-0 flex-1">
                                {/* Wraps (up to 2 lines) rather than cutting the name off in the narrow sidebar menu. */}
                                <div className="line-clamp-2 break-words font-medium" title={workspace.name}>{workspace.name}</div>
                                {!workspace.is_owner && (
                                    <div className="text-xs text-ds-fg-muted">{t('Shared')}</div>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-1">
                            {currentWorkspaceId === workspace.id && <Check className="h-4 w-4 flex-shrink-0 !text-ds-fg-accent" aria-label={t('Current workspace')} />}
                            {!workspace.is_owner && workspace.owner_id !== auth?.user?.id && allWorkspaces.length > 1 && hasPermission('workspace_leave') && (
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="size-8 p-0 text-ds-danger-fg hover:bg-ds-danger-bg hover:text-ds-danger-fg"
                                                aria-label={t('Leave {{name}}', { name: workspace.name })}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setLeaveWorkspace(workspace);
                                                }}
                                            >
                                                <LogOut className="!text-ds-danger-fg" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{t('Leave workspace')}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            )}
                        </div>
                    </DropdownMenuItem>
                ))}
            </MenuContent>
        </DropdownMenu>
        
        <LeaveWorkspaceModal
            isOpen={!!leaveWorkspace}
            onClose={() => setLeaveWorkspace(null)}
            onConfirm={() => {
                if (leaveWorkspace) {
                    router.post(route('workspaces.leave', leaveWorkspace.id), {}, {
                        onSuccess: () => {
                            setLeaveWorkspace(null);
                        }
                    });
                }
            }}
            workspaceName={leaveWorkspace?.name || ''}
        />
        </>
    );
};