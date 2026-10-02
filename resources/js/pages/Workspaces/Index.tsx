import React, { useState, useEffect } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Users, Settings, Building2, Crown, UserCheck, ArrowRight, Sparkles, Search, Filter, LayoutGrid, List, Eye } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';


interface Workspace {
    id: number;
    name: string;
    slug: string;
    description?: string;
    members_count?: number;
    created_at?: string;
    updated_at?: string;
}

interface Props {
    ownedWorkspaces: Workspace[];
    memberWorkspaces: Workspace[];
    currentWorkspace?: Workspace;
}

export default function Index({ ownedWorkspaces, memberWorkspaces, currentWorkspace }: Props) {
    const { t } = useTranslation();
    const { errors: pageErrors, auth, isSaasMode } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const [searchTerm, setSearchTerm] = useState('');
    const [activeView, setActiveView] = useState('grid');
    const [showCreateModal, setShowCreateModal] = useState(false);
    
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: ''
    });
    

    
    const switchWorkspace = (workspace: Workspace) => {
        router.post(route('workspaces.switch', workspace.id));
    };
    
    const filteredOwnedWorkspaces = ownedWorkspaces.filter(workspace => 
        workspace.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        workspace.description?.toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    const filteredMemberWorkspaces = memberWorkspaces.filter(workspace => 
        workspace.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        workspace.description?.toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    const handleCreateWorkspace = (e: React.FormEvent) => {
        e.preventDefault();
        
        post(route('workspaces.store'), {
            onSuccess: () => {
                setShowCreateModal(false);
                reset();
                toast.success(t('Workspace created successfully!'));
            },
            onError: (errors) => {
                if (errors.error) {
                    toast.error(errors.error);
                }
            }
        });
    };
    
    const pageActions = [];
    
    if (hasPermission(permissions, 'workspace_create')) {
        pageActions.push({
            label: t('Create Workspace'),
            icon: <Plus className="h-4 w-4 mr-2" />,
            variant: 'default' as const,
            onClick: () => setShowCreateModal(true)
        });
    }
    
    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Workspaces') }
    ];

    return (
        <PageTemplate 
            title={t('Workspaces Management')} 
            url="/workspaces"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <Head title={t('Workspaces')} />
            
            {/* Search and filters section */}
            <div className="bg-white rounded-lg shadow mb-4">
                <div className="p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <div className="relative w-full sm:w-64">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder={t('Search workspaces...')}
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-full pl-9"
                                />
                            </div>
                        </div>
                        
                        <div className="flex items-center gap-2">
                            <div className="border rounded-md p-0.5 mr-2">
                                <Button 
                                    size="sm" 
                                    variant={activeView === 'list' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => setActiveView('list')}
                                >
                                    <List className="h-4 w-4" />
                                </Button>
                                <Button 
                                    size="sm" 
                                    variant={activeView === 'grid' ? "default" : "ghost"}
                                    className="h-7 px-2"
                                    onClick={() => setActiveView('grid')}
                                >
                                    <LayoutGrid className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Owned Workspaces - Only show in SaaS mode */}
            {isSaasMode && (
                <div className="mb-8">
                    <div className="flex items-center gap-3 mb-4">
                        <Crown className="w-5 h-5 text-amber-500" />
                        <h2 className="text-lg font-semibold text-gray-900">{t('Your Workspaces')}</h2>
                        <Badge variant="secondary" className="bg-amber-100 text-amber-800 border-amber-200">
                            {filteredOwnedWorkspaces.length}
                        </Badge>
                    </div>
                
                {filteredOwnedWorkspaces.length === 0 ? (
                    <Card className="border-dashed border-2 border-gray-300">
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Building2 className="w-12 h-12 text-gray-400 mb-4" />
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                {searchTerm ? t('No workspaces found') : t('No workspaces yet')}
                            </h3>
                            <p className="text-gray-500 text-center mb-6 max-w-sm">
                                {searchTerm ? t('Try adjusting your search terms.') : t('Create your first workspace to start organizing your projects.')}
                            </p>
                            {!searchTerm && hasPermission(permissions, 'workspace_create') && (
                                <Button onClick={() => setShowCreateModal(true)}>
                                    <Plus className="w-4 h-4 mr-2" />
                                    {t('Create Workspace')}
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className={activeView === 'grid' ? "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" : "space-y-2"}>
                        {filteredOwnedWorkspaces.map((workspace) => (
                            activeView === 'grid' ? (
                                <TooltipProvider key={workspace.id}>
                                    <Card className="group hover:shadow-md transition-all duration-200">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between mb-2">
                                                <div className="flex items-center gap-3 flex-1">
                                                    <Avatar className="w-10 h-10">
                                                        <AvatarFallback className="bg-gradient-to-br from-blue-500 to-indigo-600 text-white font-semibold text-sm">
                                                            {workspace.name.substring(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex-1 min-w-0">
                                                        <CardTitle 
                                                            className="text-base font-semibold text-gray-900 truncate cursor-pointer hover:text-blue-600 transition-colors"
                                                            onClick={() => router.visit(route('workspaces.show', workspace.id))}
                                                        >
                                                            {workspace.name}
                                                        </CardTitle>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 mb-2">
                                                <Badge className="bg-amber-100 text-amber-800 border-amber-200 text-xs">
                                                    <Crown className="w-3 h-3 mr-1" />
                                                    Owner
                                                </Badge>
                                                {workspace.id === currentWorkspace?.id && (
                                                    <Badge variant="outline" className="text-green-600 border-green-200 bg-green-50 text-xs">
                                                        <Sparkles className="w-3 h-3 mr-1" />
                                                        Active
                                                    </Badge>
                                                )}
                                            </div>
                                            {workspace.description && (
                                                <CardDescription className="mt-2 text-sm text-gray-600 line-clamp-2">
                                                    {workspace.description}
                                                </CardDescription>
                                            )}
                                        </CardHeader>
                                        <CardContent className="pt-0">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center text-sm text-gray-500">
                                                    <Users className="w-4 h-4 mr-1.5" />
                                                    <span className="font-medium">{workspace.members_count || 0}</span>
                                                    <span className="ml-1">members</span>
                                                </div>
                                                <div className="flex gap-1">
                                                    {hasPermission(permissions, 'workspace_view') && (
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => router.visit(route('workspaces.show', workspace.id))}>
                                                                    <Eye className="w-4 h-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>View Details</TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {workspace.id !== currentWorkspace?.id && hasPermission(permissions, 'workspace_switch') && (
                                                        <Button size="sm" onClick={() => switchWorkspace(workspace)}>
                                                            Switch
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TooltipProvider>
                            ) : (
                                <Card key={workspace.id} className="hover:shadow-md transition-shadow">
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="w-10 h-10">
                                                    <AvatarFallback className="bg-gradient-to-br from-blue-500 to-indigo-600 text-white font-semibold">
                                                        {workspace.name.substring(0, 2).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <h3 
                                                            className="font-semibold text-gray-900 truncate cursor-pointer hover:text-blue-600 transition-colors"
                                                            onClick={() => router.visit(route('workspaces.show', workspace.id))}
                                                        >
                                                            {workspace.name}
                                                        </h3>
                                                        <Badge className="bg-amber-100 text-amber-800 border-amber-200">
                                                            <Crown className="w-3 h-3 mr-1" />
                                                            Owner
                                                        </Badge>
                                                        {workspace.id === currentWorkspace?.id && (
                                                            <Badge variant="outline" className="text-green-600 border-green-600">
                                                                <Sparkles className="w-3 h-3 mr-1" />
                                                                Active
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    {workspace.description && (
                                                        <p className="text-sm text-gray-600 truncate">{workspace.description}</p>
                                                    )}
                                                    <div className="flex items-center text-sm text-gray-500 mt-1">
                                                        <Users className="w-4 h-4 mr-1" />
                                                        {workspace.members_count || 0} members
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex gap-1">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => router.visit(route('workspaces.show', workspace.id))}>
                                                            <Eye className="w-4 h-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>View Details</TooltipContent>
                                                </Tooltip>
                                                {workspace.id !== currentWorkspace?.id && (
                                                    <Button size="sm" onClick={() => switchWorkspace(workspace)}>
                                                        Switch
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )
                        ))}
                    </div>
                )}
                </div>
            )}

            {/* Shared Workspaces */}
            {filteredMemberWorkspaces.length > 0 && (
                <div>
                    <div className="flex items-center gap-3 mb-4">
                        <UserCheck className="w-5 h-5 text-blue-500" />
                        <h2 className="text-lg font-semibold text-gray-900">
                            {isSaasMode ? t('Shared Workspaces') : t('Workspaces')}
                        </h2>
                        <Badge variant="secondary" className="bg-blue-100 text-blue-800 border-blue-200">
                            {filteredMemberWorkspaces.length}
                        </Badge>
                    </div>
                    <div className={activeView === 'grid' ? "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" : "space-y-2"}>
                        {filteredMemberWorkspaces.map((workspace) => (
                            activeView === 'grid' ? (
                                <TooltipProvider key={workspace.id}>
                                    <Card className="group hover:shadow-md transition-all duration-200">
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between mb-2">
                                                <div className="flex items-center gap-3 flex-1">
                                                    <Avatar className="w-10 h-10">
                                                        <AvatarFallback className="bg-gradient-to-br from-green-500 to-teal-600 text-white font-semibold text-sm">
                                                            {workspace.name.substring(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex-1 min-w-0">
                                                        <CardTitle 
                                                            className="text-base font-semibold text-gray-900 truncate cursor-pointer hover:text-blue-600 transition-colors"
                                                            onClick={() => router.visit(route('workspaces.show', workspace.id))}
                                                        >
                                                            {workspace.name}
                                                        </CardTitle>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 mb-2">
                                                <Badge variant="outline" className="border-blue-200 text-blue-700 bg-blue-50 text-xs">
                                                    <UserCheck className="w-3 h-3 mr-1" />
                                                    Member
                                                </Badge>
                                                {workspace.id === currentWorkspace?.id && (
                                                    <Badge variant="outline" className="text-green-600 border-green-200 bg-green-50 text-xs">
                                                        <Sparkles className="w-3 h-3 mr-1" />
                                                        Active
                                                    </Badge>
                                                )}
                                            </div>
                                            {workspace.description && (
                                                <CardDescription className="mt-2 text-sm text-gray-600 line-clamp-2">
                                                    {workspace.description}
                                                </CardDescription>
                                            )}
                                        </CardHeader>
                                        <CardContent className="pt-0">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center text-sm text-gray-500">
                                                    <Users className="w-4 h-4 mr-1.5" />
                                                    <span className="font-medium">{workspace.members_count || 0}</span>
                                                    <span className="ml-1">members</span>
                                                </div>
                                                <div className="flex gap-1">
                                                    {hasPermission(permissions, 'workspace_view') && (
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => router.visit(route('workspaces.show', workspace.id))}>
                                                                    <Eye className="w-4 h-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>View Details</TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {workspace.id !== currentWorkspace?.id && (
                                                        <Button size="sm" onClick={() => switchWorkspace(workspace)}>
                                                            Switch
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </TooltipProvider>
                            ) : (
                                <Card key={workspace.id} className="hover:shadow-md transition-shadow">
                                    <CardContent className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="w-10 h-10">
                                                    <AvatarFallback className="bg-gradient-to-br from-green-500 to-teal-600 text-white font-semibold">
                                                        {workspace.name.substring(0, 2).toUpperCase()}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <h3 
                                                            className="font-semibold text-gray-900 truncate cursor-pointer hover:text-blue-600 transition-colors"
                                                            onClick={() => router.visit(route('workspaces.show', workspace.id))}
                                                        >
                                                            {workspace.name}
                                                        </h3>
                                                        <Badge variant="outline" className="border-blue-200 text-blue-700 bg-blue-50">
                                                            <UserCheck className="w-3 h-3 mr-1" />
                                                            Member
                                                        </Badge>
                                                        {workspace.id === currentWorkspace?.id && (
                                                            <Badge variant="outline" className="text-green-600 border-green-600">
                                                                <Sparkles className="w-3 h-3 mr-1" />
                                                                Active
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    {workspace.description && (
                                                        <p className="text-sm text-gray-600 truncate">{workspace.description}</p>
                                                    )}
                                                    <div className="flex items-center text-sm text-gray-500 mt-1">
                                                        <Users className="w-4 h-4 mr-1" />
                                                        {workspace.members_count || 0} members
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex gap-1">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => router.visit(route('workspaces.show', workspace.id))}>
                                                            <Eye className="w-4 h-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>View Details</TooltipContent>
                                                </Tooltip>
                                                {workspace.id !== currentWorkspace?.id && (
                                                    <Button size="sm" onClick={() => switchWorkspace(workspace)}>
                                                        Switch
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )
                        ))}
                    </div>
                </div>
            )}
            
            {/* Create Workspace Modal */}
            <Dialog open={showCreateModal} onOpenChange={setShowCreateModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Building2 className="w-5 h-5 text-blue-600" />
                            {t('Create New Workspace')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('Set up a new workspace to organize your projects and collaborate with your team.')}
                        </DialogDescription>
                    </DialogHeader>
                    
                    <form onSubmit={handleCreateWorkspace} className="space-y-4">
                        <div>
                            <Label htmlFor="name">{t('Workspace Name')} *</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={t('e.g., My Company, Project Alpha')}
                                required
                            />
                            {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                        </div>
                        
                        <div>
                            <Label htmlFor="description">{t('Description')}</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder={t('Describe the purpose of this workspace...')}
                                rows={3}
                            />
                            {errors.description && <p className="text-red-500 text-sm mt-1">{errors.description}</p>}
                        </div>
                        

                        
                        <div className="flex gap-2 pt-4">
                            <Button type="submit" disabled={processing} className="flex-1">
                                <Plus className="w-4 h-4 mr-2" />
                                {processing ? t('Creating...') : t('Create Workspace')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setShowCreateModal(false);
                                    reset();
                                }}
                            >
                                {t('Cancel')}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </PageTemplate>
    );
}