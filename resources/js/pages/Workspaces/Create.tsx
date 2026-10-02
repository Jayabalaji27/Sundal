import React, { useEffect, useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Building2, Plus, AlertTriangle, Info } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { toast } from '@/components/custom-toast';

interface LimitStatus {
    can_create: boolean;
    message?: string;
    error_type?: string;
    current_workspaces: number;
    workspace_limit?: number;
    plan_name: string;
    is_unlimited: boolean;
    remaining_workspaces?: number;
}

export default function Create() {
    const { t } = useTranslation();
    const { errors: pageErrors } = usePage().props as any;
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: ''
    });
    
    const [limitStatus, setLimitStatus] = useState<LimitStatus | null>(null);
    const [checkingLimits, setCheckingLimits] = useState(true);
    
    useEffect(() => {
        if (pageErrors?.error) {
            toast.error(pageErrors.error, {
                duration: 6000,
                action: pageErrors.error.includes('Upgrade') ? {
                    label: 'View Plans',
                    onClick: () => router.visit('/plans')
                } : undefined
            });
        }
    }, [pageErrors]);
    
    useEffect(() => {
        // Check workspace creation limits on component mount
        checkWorkspaceLimits();
    }, []);
    
    const checkWorkspaceLimits = async () => {
        try {
            const response = await fetch(route('workspaces.check-limits'), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            
            if (response.ok) {
                const status = await response.json();
                setLimitStatus(status);
            }
        } catch (error) {
            console.error('Failed to check workspace limits:', error);
        } finally {
            setCheckingLimits(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Check limits before submission
        if (limitStatus && !limitStatus.can_create) {
            toast.error(limitStatus.message || t('Cannot create workspace due to plan limits'), {
                duration: 6000,
                action: {
                    label: t('Upgrade Plan'),
                    onClick: () => router.visit('/plans')
                }
            });
            return;
        }
        
        post(route('workspaces.store'), {
            onSuccess: () => {
                toast.success(t('Workspace created successfully!'));
            },
            onError: (errors) => {
                if (errors.error) {
                    toast.error(errors.error, {
                        duration: 6000,
                        action: errors.error.includes('Upgrade') ? {
                            label: t('View Plans'),
                            onClick: () => router.visit(route('plans.index'))
                        } : undefined
                    });
                }
            }
        });
    };
    
    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Workspaces'), href: route('workspaces.index') },
        { title: t('Create') }
    ];

    return (
        <PageTemplate 
            title={t('Create New Workspace')}
            subtitle={t('Set up a new workspace to organize your projects and collaborate with your team')}
            url="/workspaces/create"
            breadcrumbs={breadcrumbs}
        >
            <Head title={t('Create Workspace')} />
            
            <div className="max-w-2xl mx-auto">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="w-6 h-6 text-blue-600" />
                            {t('Workspace Details')}
                        </CardTitle>
                        <CardDescription>
                            {t('Provide basic information about your new workspace. You can always update these details later.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Plan Limit Status */}
                        {!checkingLimits && limitStatus && (
                            <div className="mb-6">
                                {!limitStatus.can_create ? (
                                    <Alert variant="destructive">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertDescription>
                                            {limitStatus.message}
                                            {(limitStatus.error_type === 'workspace_limit_reached' || limitStatus.error_type === 'plan_expired') && (
                                                <Button 
                                                    variant="link" 
                                                    className="p-0 h-auto ml-2 text-red-600 underline"
                                                    onClick={() => router.visit('/plans')}
                                                >
                                                    {t('Upgrade your plan')}
                                                </Button>
                                            )}
                                        </AlertDescription>
                                    </Alert>
                                ) : (
                                    <Alert>
                                        <Info className="h-4 w-4" />
                                        <AlertDescription>
                                            {limitStatus.is_unlimited ? (
                                                `You have unlimited workspaces on your ${limitStatus.plan_name} plan. Currently using ${limitStatus.current_workspaces} workspace${limitStatus.current_workspaces !== 1 ? 's' : ''}.`
                                            ) : (
                                                `You can create ${limitStatus.remaining_workspaces} more workspace${limitStatus.remaining_workspaces !== 1 ? 's' : ''} on your ${limitStatus.plan_name} plan (${limitStatus.current_workspaces}/${limitStatus.workspace_limit} used).`
                                            )}
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        )}
                        
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div>
                                <Label htmlFor="name" className="text-sm font-medium">
                                    {t('Workspace Name')} <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder={t('e.g., My Company, Project Alpha, Team Workspace')}
                                    className="mt-1"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-red-500 text-sm mt-2">{errors.name}</p>
                                )}
                                <p className="text-xs text-gray-500 mt-1">
                                    {t('Choose a descriptive name that your team will recognize.')}
                                </p>
                            </div>
                            
                            <div>
                                <Label htmlFor="description" className="text-sm font-medium">
                                    {t('Description')}
                                </Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder={t('Describe the purpose of this workspace, what projects it will contain, or any other relevant information...')}
                                    rows={4}
                                    className="mt-1"
                                />
                                {errors.description && (
                                    <p className="text-red-500 text-sm mt-2">{errors.description}</p>
                                )}
                                <p className="text-xs text-gray-500 mt-1">
                                    {t('Optional: Help your team understand what this workspace is for.')}
                                </p>
                            </div>
                            
                            <div className="flex gap-3 pt-4 border-t">
                                <Button 
                                    type="submit" 
                                    disabled={processing || checkingLimits || (limitStatus && !limitStatus.can_create)} 
                                    className="flex-1"
                                >
                                    <Plus className="w-4 h-4 mr-2" />
                                    {processing ? t('Creating Workspace...') : 
                                     checkingLimits ? t('Checking Limits...') : 
                                     t('Create Workspace')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                    className="px-6"
                                >
                                    {t('Cancel')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </PageTemplate>
    );
}