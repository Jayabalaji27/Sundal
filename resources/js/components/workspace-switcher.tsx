import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Building2, Check, LogOut } from 'lucide-react';
import { usePage, router } from '@inertiajs/react';
import { hasPermission } from '@/utils/permissions';
import { LeaveWorkspaceModal } from '@/components/LeaveWorkspaceModal';
import { toast } from '@/components/custom-toast';

export const WorkspaceSwitcher: React.FC = () => {
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

    if (!auth?.user || !hasPermission('workspace_switch') || allWorkspaces.length <= 1) {
        return null;
    }

    return (
        <>
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="flex items-center gap-2 h-8 max-w-40">
                    <Building2 className="h-4 w-4 flex-shrink-0" />
                    <span className="text-sm hidden md:inline-block truncate">
                        {currentWorkspace?.name || 'Select Workspace'}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-64" align="end">
                {allWorkspaces.map((workspace) => (
                    <DropdownMenuItem
                        key={workspace.id}
                        className="flex items-center justify-between gap-2 p-3"
                        onSelect={(e) => e.preventDefault()}
                    >
                        <div 
                            className="flex items-center gap-2 min-w-0 flex-1 cursor-pointer"
                            onClick={() => router.post(route('workspaces.switch', workspace.id), {}, {
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
                            })}
                        >
                            <Building2 className="h-4 w-4 flex-shrink-0" />
                            <div className="min-w-0 flex-1">
                                <div className="truncate font-medium">{workspace.name}</div>
                                {!workspace.is_owner && (
                                    <div className="text-xs text-muted-foreground">Shared</div>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-1">
                            {currentWorkspaceId === workspace.id && <Check className="h-4 w-4 flex-shrink-0" />}
                            {!workspace.is_owner && workspace.owner_id !== auth?.user?.id && allWorkspaces.length > 1 && hasPermission('workspace_leave') && (
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                size="sm"
                                                   variant="ghost"
                                                className="h-6 w-6 p-0 text-red-500 hover:text-red-700 hover:bg-red-50"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setLeaveWorkspace(workspace);
                                                }}
                                            >
                                                <LogOut className="h-3 w-3" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Leave workspace</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            )}
                        </div>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
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