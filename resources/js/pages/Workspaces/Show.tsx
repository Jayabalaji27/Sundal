import React, { useState, useEffect } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Users, Mail, Plus, Send, Trash2, Settings, Save, UserPlus, Crown, Shield, User, Clock, Building2, ExternalLink, AlertTriangle, Info, LogOut, Eye } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { LeaveWorkspaceModal } from '@/components/LeaveWorkspaceModal';
import { toast } from '@/components/custom-toast';
import { hasPermission } from '@/utils/authorization';
import { useTranslation } from 'react-i18next';


interface User {
    id: number;
    name: string;
    email: string;
}

interface Member {
    id: number;
    role: string;
    status: string;
    user: User;
    joined_at: string;
}

interface Invitation {
    id: number;
    email: string;
    role: string;
    expires_at: string;
}

interface Workspace {
    id: number;
    name: string;
    description?: string;
    members: Member[];
    pending_invitations: Invitation[];
}

interface Props {
    workspace: Workspace;
    availableRoles: string[];
    errors?: {
        error?: string;
        upgrade_link?: string;
    };
    isSaasMode?: boolean;
}

export default function Show({ workspace, availableRoles, errors, isSaasMode = true }: Props) {
    const { t } = useTranslation();
    const [showInviteForm, setShowInviteForm] = useState(false);
    const [showSettings, setShowSettings] = useState(false);
    const [deleteInvitation, setDeleteInvitation] = useState<Invitation | null>(null);
    const [deleteMember, setDeleteMember] = useState<Member | null>(null);
    const [showDeleteWorkspace, setShowDeleteWorkspace] = useState(false);
    const [showLeaveWorkspace, setShowLeaveWorkspace] = useState(false);
    const [userWorkspaceCount, setUserWorkspaceCount] = useState<number>(0);
    
    const { props } = usePage();
    const { flash, auth } = props as any;
    const permissions = auth?.permissions || [];
    
    const isOwner = workspace.owner_id == auth?.user?.id;
    
    // Fetch user workspace count for leave validation
    useEffect(() => {
        if (auth?.user && !isOwner) {
            fetch(route('workspaces.user-workspace-count'))
                .then(response => response.json())
                .then(data => setUserWorkspaceCount(data.total_count))
                .catch(error => console.error('Error fetching workspace count:', error));
        }
    }, [auth?.user, isOwner]);
    
    useEffect(() => {
        // Handle flash messages from backend
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
        
        // Handle errors prop
        if (errors?.error) {
            if (isSaasMode && errors.upgrade_link) {
                toast.error(errors.error, {
                    action: {
                        label: t('Upgrade Plan'),
                        onClick: () => window.open(errors.upgrade_link, '_blank')
                    },
                    duration: 6000
                });
            } else {
                toast.error(errors.error);
            }
        }
    }, [errors, isSaasMode, flash]);
    
    const isMember = !isOwner && workspace.members.some(member => member.user.id === auth?.user?.id);
    const canLeave = isMember && userWorkspaceCount > 1 && hasPermission(permissions, 'workspace_leave');
    
    const handleUserLogsHistory = () => {
        router.visit(route('users.all-logs'));
    };
    
    const pageActions = [];
    
    if ((isOwner || isMember) && hasPermission(permissions, 'user_view_logs')) {
        pageActions.push({
            label: t('User Logs History'),
            icon: <Eye className="h-4 w-4 mr-2" />,
            variant: 'outline' as const,
            onClick: () => handleUserLogsHistory()
        });
    }

    if (isOwner && hasPermission(permissions, 'workspace_update')) {
        pageActions.push({
            label: t('Settings'),
            icon: <Settings className="h-4 w-4 mr-2" />,
            variant: 'outline' as const,
            onClick: () => setShowSettings(!showSettings)
        });
    }
    
    if (isOwner && hasPermission(permissions, 'workspace_invite_members')) {
        pageActions.push({
            label: t('Invite Member'),
            icon: <UserPlus className="h-4 w-4 mr-2" />,
            variant: 'default' as const,
            onClick: () => setShowInviteForm(!showInviteForm),
            disabled: availableRoles.length === 0
        });
    }
    
    if (canLeave) {
        pageActions.push({
            label: t('Leave Workspace'),
            icon: <LogOut className="h-4 w-4 mr-2" />,
            variant: 'destructive' as const,
            onClick: () => setShowLeaveWorkspace(true)
        });
    }
    
    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Workspaces'), href: route('workspaces.index') },
        { title: workspace.name }
    ];
    
    const { data, setData, post, processing, errors: formErrors, reset } = useForm({
        email: '',
        role: availableRoles[0] || 'member'
    });

    const { data: settingsData, setData: setSettingsData, put: putSettings, processing: settingsProcessing, errors: settingsErrors } = useForm({
        name: workspace.name,
        description: workspace.description || '',
        task_delete_alert: workspace.task_delete_alert || false
    });

    const handleInvite = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('workspace.invitations.store', workspace.id), {
            preserveState: false,
            onSuccess: () => {
                reset();
                setShowInviteForm(false);
            }
        });
    };

    const handleSettingsUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        
        putSettings(route('workspaces.update', workspace.id), {
            onSuccess: () => {
                setShowSettings(false);
            }
        });
    };

    const handleDeleteWorkspace = () => {
        router.delete(route('workspaces.destroy', workspace.id));
        setShowDeleteWorkspace(false);
    };
    
    const handleLeaveWorkspace = () => {
        router.post(route('workspaces.leave', workspace.id), {}, {
            onSuccess: () => {
                setShowLeaveWorkspace(false);
            },
            onError: (errors) => {
                console.error('Leave workspace error:', errors);
                if (errors.message) {
                    toast.error(errors.message);
                }
            }
        });
    };

    const getRoleIcon = (role: string) => {
        switch (role) {
            case 'owner': return <Crown className="w-4 h-4" />;
            case 'admin': return <Shield className="w-4 h-4" />;
            default: return <User className="w-4 h-4" />;
        }
    };
    
    const getRoleColor = (role: string) => {
        switch (role) {
            case 'owner': return 'bg-amber-100 text-amber-800 border-amber-200';
            case 'admin': return 'bg-red-100 text-red-800 border-red-200';
            case 'member': return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'client': return 'bg-green-100 text-green-800 border-green-200';
            default: return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    return (
        <PageTemplate 
            title={workspace.name}
            subtitle={workspace.description}
            url={`/workspaces/${workspace.id}`}
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <Head title={`${workspace.name} - ${t('Workspace')}`} />
            
            {/* Workspace Header */}
            <div className="bg-white rounded-lg shadow mb-4">
                <div className="p-4">
                    <div className="flex items-center gap-3">
                        <Avatar className="w-10 h-10">
                            <AvatarFallback className="bg-gradient-to-br from-blue-500 to-indigo-600 text-white font-bold text-sm">
                                {workspace.name.substring(0, 2).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex-1">
                            <h2 className="text-xl font-bold text-gray-900">{workspace.name}</h2>
                            {workspace.description && (
                                <p className="text-gray-600 text-sm">{workspace.description}</p>
                            )}
                            <div className="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                <div className="flex items-center gap-1">
                                    <Users className="w-3 h-3" />
                                    {workspace.members.length} {t('members')}
                                </div>
                                {workspace.pending_invitations.length > 0 && (
                                    <div className="flex items-center gap-1">
                                        <Mail className="w-3 h-3" />
                                        {workspace.pending_invitations.length} {t('pending invitations')}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Settings Form */}
            {showSettings && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings className="w-5 h-5" />
                            {t('Workspace Settings')}
                        </CardTitle>
                        <CardDescription>
                            {t('Update your workspace information and preferences.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSettingsUpdate} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <Label htmlFor="name">{t('Workspace Name')}</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={settingsData.name}
                                        onChange={(e) => setSettingsData('name', e.target.value)}
                                        required
                                    />
                                    {settingsErrors.name && (
                                        <p className="text-red-500 text-sm mt-1">{settingsErrors.name}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="description">{t('Description')}</Label>
                                    <Input
                                        id="description"
                                        type="text"
                                        value={settingsData.description}
                                        onChange={(e) => setSettingsData('description', e.target.value)}
                                        placeholder={t('Workspace description (optional)')}
                                    />
                                    {settingsErrors.description && (
                                        <p className="text-red-500 text-sm mt-1">{settingsErrors.description}</p>
                                    )}
                                </div>
                            </div>

                            <Separator />
                            <div className="flex justify-between">
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={settingsProcessing}>
                                        <Save className="w-4 h-4 mr-2" />
                                        {settingsProcessing ? t('Saving...') : t('Save Changes')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setShowSettings(false)}
                                    >
                                        {t('Cancel')}
                                    </Button>
                                </div>
                                {isOwner && hasPermission(permissions, 'workspace_delete') && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={() => setShowDeleteWorkspace(true)}
                                    >
                                        <Trash2 className="w-4 h-4 mr-2" />
                                        {t('Delete Workspace')}
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {/* Invite Form */}
            {showInviteForm && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserPlus className="w-5 h-5" />
                            {t('Invite New Member')}
                        </CardTitle>
                        <CardDescription>
                            {t('Send an invitation to add a new member to your workspace.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Role Limit Warning */}
                        {availableRoles.length === 0 && (
                            <Alert variant="destructive" className="mb-4">
                                <AlertTriangle className="h-4 w-4" />
                                <AlertDescription>
                                    {t('You have reached the member limit for your current plan.')} 
                                    <Button 
                                        variant="link" 
                                        className="p-0 h-auto ml-1 text-red-600 underline"
                                        onClick={() => router.visit(route('plans.index'))}
                                    >
                                        {t('Upgrade your plan')}
                                    </Button>
                                    {' '}{t('to invite more members.')}
                                </AlertDescription>
                            </Alert>
                        )}
                        
                        <form onSubmit={handleInvite} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="email">{t('Email Address')}</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder={t('Enter email address')}
                                        required
                                        disabled={availableRoles.length === 0}
                                    />
                                    {formErrors.email && (
                                        <p className="text-red-500 text-sm mt-1">{formErrors.email}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="role">{t('Role')}</Label>
                                    <Select 
                                        value={data.role} 
                                        onValueChange={(value) => setData('role', value)}
                                        disabled={availableRoles.length === 0}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={availableRoles.length === 0 ? t('No roles available') : t('Select role')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableRoles.includes('manager') && (
                                                <SelectItem value="manager">{t('Manager - Full access')}</SelectItem>
                                            )}
                                            {availableRoles.includes('member') && (
                                                <SelectItem value="member">{t('Member - Standard access')}</SelectItem>
                                            )}
                                            {availableRoles.includes('client') && (
                                                <SelectItem value="client">{t('Client - Limited access')}</SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    {availableRoles.length === 0 && (
                                        <p className="text-red-500 text-sm mt-1">{t('No roles available due to plan limits')}</p>
                                    )}
                                </div>
                            </div>
                            
                            {/* Plan Limit Info */}
                            {availableRoles.length > 0 && isSaasMode && (
                                <Alert>
                                    <Info className="h-4 w-4" />
                                    <AlertDescription>
                                        {t('Available roles based on your current plan limits')}: {availableRoles.join(', ')}
                                    </AlertDescription>
                                </Alert>
                            )}
                            
                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing || availableRoles.length === 0}>
                                    <Send className="w-4 h-4 mr-2" />
                                    {processing ? t('Sending...') : t('Send Invitation')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowInviteForm(false)}
                                >
                                    {t('Cancel')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {/* Members */}
            <div className="mb-8">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="w-5 h-5" />
                            {t('Members')} ({workspace.members.length})
                        </CardTitle>
                        <CardDescription>
                            {t('Manage workspace members and their roles.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {workspace.members.map((member) => (
                                <div key={member.id} className="flex flex-wrap items-center justify-between gap-3 p-4 border rounded-lg hover:bg-gray-50 transition-colors">
                                    <div className="flex items-center gap-3 min-w-0">
                                        <Avatar className="w-10 h-10 shrink-0">
                                            {member.user.avatar && (
                                                <AvatarImage src={member.user.avatar.startsWith('http') ? member.user.avatar : `/storage/${member.user.avatar}`} />
                                            )}
                                            <AvatarFallback className="bg-gradient-to-br from-blue-500 to-indigo-600 text-white font-semibold">
                                                {member.user.name.charAt(0).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0">
                                            <p className="font-medium text-gray-900 truncate">{member.user.name}</p>
                                            <p className="text-sm text-gray-500 truncate">{member.user.email}</p>
                                            <div className="flex items-center gap-1 mt-1 text-xs text-gray-400">
                                                <Clock className="w-3 h-3" />
                                                {t('Joined')} {new Date(member.joined_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Badge className={getRoleColor(member.role)}>
                                                        {getRoleIcon(member.role)}
                                                        <span className="ml-1 capitalize">{member.role}</span>
                                                    </Badge>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{t('Role')}: {member.role}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <Badge variant={member.status === 'active' ? 'default' : 'secondary'}>
                                            {member.status}
                                        </Badge>
                                        {member.role !== 'owner' && isOwner && hasPermission(permissions, 'workspace_manage_members') && (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-red-500 hover:text-red-700 hover:bg-red-50"
                                                            onClick={() => setDeleteMember(member)}
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>{t('Remove member')}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Pending Invitations */}
            {workspace.pending_invitations.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Mail className="w-5 h-5" />
                            {t('Pending Invitations')} ({workspace.pending_invitations.length})
                        </CardTitle>
                        <CardDescription>
                            {t('Manage pending workspace invitations.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {workspace.pending_invitations.map((invitation) => (
                                <div key={invitation.id} className="flex flex-wrap items-center justify-between gap-3 p-4 border rounded-lg hover:bg-gray-50 transition-colors">
                                    <div className="flex items-center gap-3 min-w-0">
                                        <Avatar className="w-10 h-10 shrink-0">
                                            <AvatarFallback className="bg-gradient-to-br from-gray-400 to-gray-600 text-white font-semibold">
                                                {invitation.email.charAt(0).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0">
                                            <p className="font-medium text-gray-900 truncate">{invitation.email}</p>
                                            <div className="flex items-center gap-1 mt-1 text-xs text-gray-400">
                                                <Clock className="w-3 h-3" />
                                                {t('Expires')}: {new Date(invitation.expires_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <Badge className={getRoleColor(invitation.role)}>
                                            {getRoleIcon(invitation.role)}
                                            <span className="ml-1 capitalize">{invitation.role}</span>
                                        </Badge>
                                        {isOwner && hasPermission(permissions, 'workspace_invite_members') && (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-blue-500 hover:text-blue-700 hover:bg-blue-50"
                                                            onClick={() => router.post(route('invitations.resend', invitation.id))}
                                                        >
                                                            <Send className="w-4 h-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>{t('Resend invitation')}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        )}
                                        {isOwner && hasPermission(permissions, 'workspace_manage_members') && (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-red-500 hover:text-red-700 hover:bg-red-50"
                                                            onClick={() => setDeleteInvitation(invitation)}
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>{t('Delete invitation')}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
            
            {/* Delete Invitation Modal */}
            <EnhancedDeleteModal
                isOpen={!!deleteInvitation}
                onClose={() => setDeleteInvitation(null)}
                onConfirm={() => {
                    if (deleteInvitation) {
                        router.delete(route('invitations.destroy', deleteInvitation.id));
                        setDeleteInvitation(null);
                    }
                }}
                itemName={deleteInvitation?.email || ''}
                entityName={t('Invitation')}
                warningMessage={t('This invitation will be permanently removed and cannot be recovered.')}
            />
            
            {/* Delete Member Modal */}
            <EnhancedDeleteModal
                isOpen={!!deleteMember}
                onClose={() => setDeleteMember(null)}
                onConfirm={() => {
                    if (deleteMember) {
                        router.delete(route('workspace.remove-member', [workspace.id, deleteMember.user.id]));
                        setDeleteMember(null);
                    }
                }}
                itemName={deleteMember?.user.name || ''}
                entityName={t('Member')}
                warningMessage={t('This member will be removed from the workspace and lose access to all projects and data.')}
            />
            
            {/* Delete Workspace Modal */}
            <CrudDeleteModal
                isOpen={showDeleteWorkspace}
                onClose={() => setShowDeleteWorkspace(false)}
                onConfirm={handleDeleteWorkspace}
                itemName={workspace.name}
                entityName={t('workspace')}
            />
            
            {/* Leave Workspace Modal */}
            <LeaveWorkspaceModal
                isOpen={showLeaveWorkspace}
                onClose={() => setShowLeaveWorkspace(false)}
                onConfirm={handleLeaveWorkspace}
                workspaceName={workspace.name}
            />
        </PageTemplate>
    );
}