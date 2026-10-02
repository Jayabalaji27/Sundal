import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Loader2, Shield, ShieldCheck, ShieldX } from 'lucide-react';
import { router } from '@inertiajs/react';

interface Permission {
    name: string;
    label: string;
    has_via_role: boolean;
    has_directly: boolean;
    status: 'none' | 'via_role' | 'granted';
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface UserPermissionModalProps {
    isOpen: boolean;
    onClose: () => void;
    projectId: number;
    userId: number | null;
}

export default function UserPermissionModal({ isOpen, onClose, projectId, userId }: UserPermissionModalProps) {
    const [permissions, setPermissions] = useState<Permission[]>([]);
    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [changes, setChanges] = useState<Record<string, 'grant' | 'revoke'>>({});

    useEffect(() => {
        if (isOpen && userId) {
            fetchUserPermissions();
        }
    }, [isOpen, userId, projectId]);

    const fetchUserPermissions = async () => {
        if (!userId) return;
        
        setLoading(true);
        try {
            const response = await fetch(`/projects/${projectId}/users/${userId}/permissions`);
            const data = await response.json();
            setPermissions(data.permissions);
            setUser(data.user);
        } catch (error) {
            console.error('Failed to fetch permissions:', error);
        } finally {
            setLoading(false);
        }
    };

    const handlePermissionToggle = (permissionName: string, currentStatus: string) => {
        const newChanges = { ...changes };
        
        if (currentStatus === 'granted') {
            newChanges[permissionName] = 'revoke';
        } else {
            newChanges[permissionName] = 'grant';
        }
        
        setChanges(newChanges);
    };

    const getPermissionStatus = (permission: Permission) => {
        const change = changes[permission.name];
        if (change === 'grant') return 'granted';
        if (change === 'revoke') return 'none';
        return permission.status;
    };

    const handleSave = async () => {
        if (!userId || Object.keys(changes).length === 0) return;

        setSaving(true);
        try {
            const permissionUpdates = Object.entries(changes).map(([name, action]) => ({
                name,
                action
            }));

            await router.post(`/projects/${projectId}/users/${userId}/permissions`, {
                permissions: permissionUpdates
            }, {
                preserveState: true,
                onSuccess: () => {
                    setChanges({});
                    onClose();
                }
            });
        } catch (error) {
            console.error('Failed to update permissions:', error);
        } finally {
            setSaving(false);
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'granted':
                return <ShieldCheck className="h-4 w-4 text-green-600" />;
            case 'via_role':
                return <Shield className="h-4 w-4 text-blue-600" />;
            default:
                return <ShieldX className="h-4 w-4 text-gray-400" />;
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'granted':
                return <Badge variant="default" className="bg-green-100 text-green-800">Granted</Badge>;
            case 'via_role':
                return <Badge variant="secondary" className="bg-blue-100 text-blue-800">Via Role</Badge>;
            default:
                return <Badge variant="outline" className="text-gray-600">None</Badge>;
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5" />
                        Manage User Permissions
                        {user && <span className="text-sm font-normal text-gray-600">- {user.name}</span>}
                    </DialogTitle>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin" />
                        <span className="ml-2">Loading permissions...</span>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                            <p><strong>Permission States:</strong></p>
                            <ul className="mt-1 space-y-1">
                                <li className="flex items-center gap-2">
                                    <ShieldX className="h-3 w-3 text-gray-400" />
                                    <span>None - User doesn't have this permission</span>
                                </li>
                                <li className="flex items-center gap-2">
                                    <Shield className="h-3 w-3 text-blue-600" />
                                    <span>Via Role - Permission granted through user's role</span>
                                </li>
                                <li className="flex items-center gap-2">
                                    <ShieldCheck className="h-3 w-3 text-green-600" />
                                    <span>Granted - Direct permission granted to user</span>
                                </li>
                            </ul>
                        </div>

                        <div className="space-y-2">
                            {permissions.map((permission) => {
                                const currentStatus = getPermissionStatus(permission);
                                const hasChange = changes[permission.name];
                                
                                return (
                                    <div
                                        key={permission.name}
                                        className={`flex items-center justify-between p-3 border rounded-lg ${
                                            hasChange ? 'border-blue-300 bg-blue-50' : 'border-gray-200'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3">
                                            {getStatusIcon(currentStatus)}
                                            <div>
                                                <div className="font-medium">{permission.label}</div>
                                                <div className="text-sm text-gray-500">{permission.name}</div>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center gap-2">
                                            {getStatusBadge(currentStatus)}
                                            
                                            {permission.status !== 'via_role' && (
                                                <Button
                                                    variant={currentStatus === 'granted' ? 'destructive' : 'default'}
                                                    size="sm"
                                                    onClick={() => handlePermissionToggle(permission.name, currentStatus)}
                                                >
                                                    {currentStatus === 'granted' ? 'Revoke' : 'Grant'}
                                                </Button>
                                            )}
                                            
                                            {permission.status === 'via_role' && (
                                                <span className="text-xs text-gray-500 px-2">
                                                    Inherited from role
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="flex justify-end gap-2 pt-4 border-t">
                            <Button variant="outline" onClick={onClose}>
                                Cancel
                            </Button>
                            <Button 
                                onClick={handleSave}
                                disabled={saving || Object.keys(changes).length === 0}
                            >
                                {saving && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
                                Save Changes ({Object.keys(changes).length})
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}