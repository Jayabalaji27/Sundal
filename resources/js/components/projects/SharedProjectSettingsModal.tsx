import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Copy, Eye, EyeOff, Settings } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface SharedProjectSettingsModalProps {
    isOpen: boolean;
    onClose: () => void;
    project: any;
}

export default function SharedProjectSettingsModal({ isOpen, onClose, project }: SharedProjectSettingsModalProps) {
    const { t } = useTranslation();

    const [settings, setSettings] = useState({
        overview: false,
        member: false,
        milestone: false,
        notes: false,
        budget: false,
        expenses: false,
        task: false,
        recent_bugs: false,
        timesheet: false,
        files: false,
        activity: false
    });

    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [shareUrl, setShareUrl] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (project?.shared_settings) {
            setSettings({ ...settings, ...project.shared_settings });
        } else {
            // Auto-enable settings based on project data
            const autoSettings = { ...settings };

            autoSettings.overview = true; // Always enable overview
            if (project?.members?.length > 0 || project?.clients?.length > 0) autoSettings.member = true;
            if (project?.milestones?.length > 0) autoSettings.milestone = true;
            if (project?.notes?.data?.length > 0) autoSettings.notes = true;
            if (project?.budget) autoSettings.budget = true;
            if (project?.expenses?.length > 0) autoSettings.expenses = true;
            if (project?.attachments?.data?.length > 0) autoSettings.files = true;
            if (project?.activities?.data?.length > 0) autoSettings.activity = true;


            setSettings(autoSettings);
        }
    }, [project]);

    const handleSettingChange = (key: string, value: boolean) => {
        setSettings(prev => ({ ...prev, [key]: value }));
    };

    const handleSave = () => {
        setIsLoading(true);

        router.put(route('projects.update-shared-settings', project.id), {
            shared_settings: settings,
            password: password || null
        }, {
            onSuccess: () => {
                toast.success('Shared settings updated successfully');
                onClose();
            },
            onError: (errors) => {
                toast.error(`Failed: ${Object.values(errors).join(', ')}`);
            },
            onFinish: () => setIsLoading(false)
        });
    };

    const generateShareLink = () => {
        fetch(route('projects.generate-share-link', project.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
            .then(response => response.json())
            .then(data => {
                setShareUrl(data.share_url || '');
            })
    };

    useEffect(() => {
        if (isOpen) {
            generateShareLink();
        }
    }, [isOpen]);

    const copyShareLink = () => {
        if (shareUrl) {
            navigator.clipboard.writeText(shareUrl);
            toast.success('Link Copy on Clipboard');
        }
    };

    const settingsConfig = [
        { key: 'overview', label: 'Overview', description: 'Project overview and summary information' },
        { key: 'member', label: 'Team Members', description: 'Team members, clients and their roles' },
        { key: 'milestone', label: 'Milestone', description: 'Project milestones and deadlines' },
        { key: 'notes', label: 'Notes', description: 'Project notes and documentation' },
        { key: 'budget', label: 'Budget', description: 'Project budget and financial information' },
        { key: 'expenses', label: 'Expenses', description: 'Project expenses and costs' },
        { key: 'task', label: 'Task', description: 'Project tasks and assignments' },
        { key: 'recent_bugs', label: 'Recent Bugs', description: 'Recent bug reports and issues' },
        { key: 'timesheet', label: 'Timesheet', description: 'Timesheet entries and approvals' },
        { key: 'files', label: 'Files', description: 'Project attachments and documents' },
        { key: 'activity', label: 'Activity', description: 'Project activity log and updates' },

    ];

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Settings className="h-5 w-5" />
                        {t('Shared Project')}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Settings List */}
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4">
                            <div className="flex items-center justify-between text-sm font-medium text-gray-700 border-b pb-2">
                                <span>{t('Name')}</span>
                                <span>{t('ON/OFF')}</span>
                            </div>

                            {settingsConfig.map((config) => (
                                <div key={config.key} className="flex items-center justify-between py-2">
                                    <div className="flex-1">
                                        <Label className="text-sm font-medium">{config.label}</Label>
                                        <p className="text-xs text-gray-500 mt-1">{config.description}</p>
                                    </div>
                                    <Switch
                                        checked={settings[config.key as keyof typeof settings]}
                                        onCheckedChange={(checked) => handleSettingChange(config.key, checked)}
                                        className="ml-4"
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Password Field */}
                    <div className="space-y-2 border-t pt-4">
                        <Label htmlFor="password">{t('Password (Optional)')}</Label>
                        <div className="relative">
                            <Input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="******"
                                className="pr-10"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                                onClick={() => setShowPassword(!showPassword)}
                            >
                                {showPassword ? (
                                    <EyeOff className="h-4 w-4" />
                                ) : (
                                    <Eye className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </div>

                    {/* Share Link Section */}
                    <div className="space-y-3 border-t pt-4">
                        <div className="flex items-center justify-between">
                            <Label className="text-sm font-medium">{t('Share Link')}</Label>
                            {shareUrl && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={copyShareLink}
                                    className="flex items-center gap-2"
                                    title="Copy Project Link"
                                >
                                    <Copy className="h-4 w-4" />
                                </Button>
                            )}
                        </div>

                    </div>

                    {/* Action Buttons */}
                    <div className="flex justify-end gap-3 border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="button"
                            onClick={handleSave}
                        >
                            {t('Save')}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
