import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Loader2, Shield, User, Users } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface Permission {
    name: string;
    label: string;
    description: string;
}

interface UpdatePermissionsModalProps {
    isOpen: boolean;
    onClose: () => void;
    project: any;
    user: any;
    userType: 'member' | 'client';
}

export default function UpdatePermissionsModal({
    isOpen,
    onClose,
    project,
    user,
    userType
    
}: UpdatePermissionsModalProps) {
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [availablePermissions, setAvailablePermissions] = useState<Permission[]>([]);
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);
    const { t } = useTranslation();

    useEffect(() => {
        if (isOpen && user) {
            loadUserPermissions();
        }
    }, [isOpen, user]);

    const loadUserPermissions = async () => {
        setLoading(true);
        try {
            // Since the route doesn't exist, let's create mock data for now
            // This should be replaced with actual API call once backend is implemented
            const mockPermissions = [
                { name: 'project_view_milestones', label: t('View Milestones'), description: t('Can view project milestones') },
                { name: 'project_manage_milestones', label: t('Manage Milestones'), description: t('Can create, edit, and delete milestones') },
                { name: 'task_view_any', label: t('View Tasks'), description: t('Can view all tasks in the project') },
                { name: 'task_create', label: t('Create Tasks'), description: t('Can create new tasks') },
                { name: 'task_update', label: t('Edit Tasks'), description: t('Can edit existing tasks') },
                { name: 'task_delete', label: t('Delete Tasks'), description: t('Can delete tasks') },
                { name: 'bug_view_any', label: t('View Bugs'), description: t('Can view bug reports') },
                { name: 'bug_create', label: t('Create Bugs'), description: t('Can create bug reports') },
                { name: 'expense_view_any', label: t('View Expenses'), description: t('Can view project expenses') },
                { name: 'expense_create', label: t('Create Expenses'), description: t('Can create expense entries') },
                { name: 'timesheet_view_any', label: t('View Timesheets'), description: t('Can view timesheet entries') },
                { name: 'project_manage_attachments', label: t('Manage Files'), description: t('Can upload and manage project files') }
            ];
            
            // Mock current user permissions (empty for now)
            const currentPermissions = [];
            
            setAvailablePermissions(mockPermissions);
            setSelectedPermissions(currentPermissions);
        } catch (error) {
            console.error('❌ Error loading permissions:', error);
            toast.error(t('Failed to load user permissions'));
        } finally {
            setLoading(false);
        }
    };

    const handlePermissionToggle = (permissionName: string) => {
        setSelectedPermissions(prev => 
            prev.includes(permissionName)
                ? prev.filter(p => p !== permissionName)
                : [...prev, permissionName]
        );
    };

    const handleSave = () => {
        setSaving(true);
        
        // Check if demo mode
        const isDemoMode = window.location.hostname === 'localhost' || window.location.hostname.includes('demo');
        
        if (isDemoMode) {
            // Demo mode - simulate saving
            setTimeout(() => {
                toast.success(t('Permissions updated successfully (Demo Mode)'));
                setSaving(false);
                onClose();
            }, 1000);
        } else {
            // Production mode - actual API call
            router.put(route('projects.update-user-permissions', project.id), {
                user_id: user.id,
                user_type: userType,
                permissions: selectedPermissions
            }, {
                onSuccess: (page) => {
                    setSaving(false);
                    if (page.props.flash.success) {
                        toast.success(page.props.flash.success);
                    } else if (page.props.flash.error) {
                        toast.error(page.props.flash.error);
                    }
                    onClose();
                },
                onError: (errors) => {
                    setSaving(false);
                    if (typeof errors === 'string') {
                        toast.error(errors);
                    } else {
                        toast.error(`${t('Failed to update permissions')}: ${Object.values(errors).join(', ')}`);
                    }
                }
            });
        }
    };

    const formatText = (text: string) => {
        return text.replace(/_/g, ' ').split(' ').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-3">
                        <Shield className="h-5 w-5 text-blue-500" />
                        {t('Update Permissions')}
                    </DialogTitle>
                </DialogHeader>

                {user && (
                    <div className="mb-6">
                        <div className="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                            <Avatar>
                                <AvatarImage src={user.avatar} />
                                <AvatarFallback>{user.name?.charAt(0)}</AvatarFallback>
                            </Avatar>
                            <div className="flex-1">
                                <h3 className="font-medium">{user.name}</h3>
                                <p className="text-sm text-gray-500">{user.email}</p>
                            </div>
                            <Badge variant="outline" className="flex items-center gap-1">
                                {userType === 'member' ? <Users className="h-3 w-3" /> : <User className="h-3 w-3" />}
                                {formatText(userType)}
                            </Badge>
                        </div>
                    </div>
                )}

                {loading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin" />
                        <span className="ml-2">{t('Loading permissions...')}</span>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="border-b pb-2">
                            <h4 className="font-medium text-gray-900">{t('Project Access Permissions')}</h4>
                            <p className="text-sm text-gray-500">
                                {t('Select which parts of the project this user can access')}
                            </p>
                        </div>

                        <div className="space-y-3">
                            {availablePermissions.map((permission) => (
                                <div key={permission.name} className="flex items-start space-x-3 p-3 border rounded-lg hover:bg-gray-50">
                                    <Checkbox
                                        id={permission.name}
                                        checked={selectedPermissions.includes(permission.name)}
                                        onCheckedChange={() => handlePermissionToggle(permission.name)}
                                        className="mt-1"
                                    />
                                    <div className="flex-1">
                                        <label 
                                            htmlFor={permission.name}
                                            className="text-sm font-medium cursor-pointer"
                                        >
                                            {permission.label}
                                        </label>
                                        <p className="text-xs text-gray-500 mt-1">
                                            {permission.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {availablePermissions.length === 0 && !loading && (
                            <div className="text-center py-8 text-gray-500">
                                <Shield className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>{t('No permissions available to configure')}</p>
                            </div>
                        )}
                    </div>
                )}

                <div className="flex justify-end gap-3 pt-4 border-t">
                    <Button variant="outline" onClick={onClose} disabled={saving}>
                        {t('Cancel')}
                    </Button>
                    <Button 
                        onClick={handleSave} 
                        disabled={saving || loading}
                        className="flex items-center gap-2"
                    >
                        {saving && <Loader2 className="h-4 w-4 animate-spin" />}
                        {t('Save Permissions')}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}