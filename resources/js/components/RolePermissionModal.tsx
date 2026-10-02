import React, { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Loader2, Shield } from 'lucide-react';
import { router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface Permission {
    name: string;
    label: string;
    action: string;
    has_permission: boolean;
}

interface PermissionModule {
    module: string;
    permissions: Permission[];
}

interface Role {
    id: number;
    name: string;
}

interface RolePermissionModalProps {
    isOpen: boolean;
    onClose: () => void;
    projectId: number;
    roleId: number | null;
}

export default function RolePermissionModal({ isOpen, onClose, projectId, roleId }: RolePermissionModalProps) {
    const { t } = useTranslation();
    const [permissionsByModule, setPermissionsByModule] = useState<Record<string, PermissionModule>>({});
    const [role, setRole] = useState<Role | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [changes, setChanges] = useState<Record<string, 'grant' | 'revoke'>>({});

    useEffect(() => {
        if (isOpen && roleId) {
            fetchRolePermissions();
        }
    }, [isOpen, roleId, projectId]);

    const fetchRolePermissions = async () => {
        if (!roleId) return;

        setLoading(true);
        try {
            const response = await fetch(route('projects.role-permissions', [projectId, roleId]));
            const data = await response.json();
            setPermissionsByModule(data.permissionsByModule);
            setRole(data.role);
        } catch (error) {
            console.error('RolePermissionModal - Failed to fetch permissions:', error);
        } finally {
            setLoading(false);
        }
    };

    const handlePermissionToggle = (permissionName: string, currentHasPermission: boolean) => {
        const newChanges = { ...changes };

        // If there's already a change for this permission, remove it (revert to original state)
        if (changes[permissionName]) {
            delete newChanges[permissionName];
        } else {
            // If no change exists, toggle from current state
            if (currentHasPermission) {
                newChanges[permissionName] = 'revoke';
            } else {
                newChanges[permissionName] = 'grant';
            }
        }

        setChanges(newChanges);
    };

    const getPermissionStatus = (permission: Permission) => {
        const change = changes[permission.name];
        if (change === 'grant') return true;
        if (change === 'revoke') return false;
        return permission.has_permission;
    };

    const handleSave = async () => {
        if (!roleId) return;

        setSaving(true);

        // Get all currently selected permissions (original + changes)
        const selectedPermissions: string[] = [];

        Object.values(permissionsByModule).forEach(moduleData => {
            moduleData.permissions.forEach(permission => {
                const isSelected = getPermissionStatus(permission);
                if (isSelected) {
                    selectedPermissions.push(permission.name);
                }
            });
        });

        router.post(route('projects.update-role-permissions', { project: projectId, role: roleId }), {
            permissions: selectedPermissions
        }, {
            onSuccess: (page) => {
                setSaving(false);
                if (page.props.flash.success) {
                    toast.success(page.props.flash.success);
                } else if (page.props.flash.error) {
                    toast.error(page.props.flash.error);
                }
                setChanges({});
                onClose();
            },
            onError: (errors) => {
                setSaving(false);
                if (typeof errors === 'string') {
                    toast.error(errors);
                } else {
                    toast.error(`Failed to update role permissions: ${Object.values(errors).join(', ')}`);
                }
            }
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-6xl max-h-[85vh] overflow-hidden">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle>
                        <div>{t('Update Permissions')}</div>
                        {role && <div className="text-base font-normal text-gray-500">{role.name} {t('Role')}</div>}
                    </DialogTitle>
                </DialogHeader>

                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <div className="text-center">
                            <Loader2 className="h-8 w-8 animate-spin text-blue-600 mx-auto mb-3" />
                            <p className="text-sm font-medium text-gray-600">{t('Loading Permissions...')}</p>
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col h-full">
                        <div className="flex-1 overflow-y-auto px-1 max-h-[60vh]">
                            <div className="space-y-6 pr-2">
                                {Object.entries(permissionsByModule).map(([moduleKey, moduleData]) => {
                                    const modulePermissions = moduleData.permissions;
                                    const selectedCount = modulePermissions.filter(p => getPermissionStatus(p)).length;
                                    const totalCount = modulePermissions.length;

                                    return (
                                        <div key={moduleKey} className="bg-white border border-gray-200 rounded-lg">
                                            {/* Module Header */}
                                            <div className="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50">
                                                <div className="flex items-center space-x-3">
                                                    <Checkbox
                                                        checked={selectedCount === totalCount && selectedCount > 0}
                                                        onCheckedChange={(checked) => {
                                                            const newChanges = { ...changes };
                                                            modulePermissions.forEach(permission => {
                                                                if (checked) {
                                                                    // Select all - grant permission if not already has it
                                                                    if (!permission.has_permission) {
                                                                        newChanges[permission.name] = 'grant';
                                                                    } else {
                                                                        delete newChanges[permission.name];
                                                                    }
                                                                } else {
                                                                    // Deselect all - revoke permission if has it
                                                                    if (permission.has_permission) {
                                                                        newChanges[permission.name] = 'revoke';
                                                                    } else {
                                                                        delete newChanges[permission.name];
                                                                    }
                                                                }
                                                            });
                                                            setChanges(newChanges);
                                                        }}
                                                        className="text-blue-600"
                                                    />
                                                    <span className="font-semibold text-gray-900 capitalize">
                                                        {moduleData.module}
                                                    </span>
                                                </div>
                                                <span className="text-sm text-gray-500">
                                                    {selectedCount} of {totalCount} selected
                                                </span>
                                            </div>

                                            {/* Permissions Grid */}
                                            <div className="p-4">
                                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                    {modulePermissions.map((permission) => {
                                                        const isEnabled = getPermissionStatus(permission);
                                                        const hasChange = changes[permission.name];

                                                        return (
                                                            <div key={permission.name} className="flex items-center space-x-3">
                                                                <Checkbox
                                                                    id={permission.name}
                                                                    checked={isEnabled}
                                                                    onCheckedChange={() => handlePermissionToggle(permission.name, permission.has_permission)}
                                                                    className={`text-blue-600 ${hasChange ? 'border-blue-500' : ''}`}
                                                                />
                                                                <label
                                                                    htmlFor={permission.name}
                                                                    className="text-sm font-medium text-gray-700 cursor-pointer flex-1"
                                                                >
                                                                    {permission.label || permission.name.replace(/_/g, ' ')}
                                                                </label>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-6 border-t border-gray-200 bg-gray-50 px-6 py-4 -mx-6 -mb-6">
                            <Button
                                variant="outline"
                                onClick={onClose}
                                className="px-6 py-2 font-medium"
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleSave}
                                disabled={saving}
                            >
                                {saving && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
                                {saving ? 'Updating...' : 'Update'}
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}