import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useState, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import axios from 'axios';
import { toast } from 'sonner';

interface NotificationItem {
    name: string;
    label: string;
    description?: string;
}

// Groups and one-line "who gets it" for the known emails. Templates not listed
// here (e.g. one added later) still show, under "Other".
const GROUPS: { title: string; items: Record<string, string> }[] = [
    {
        title: 'Team',
        items: {
            'Workspace Invitation': 'To people invited to join the workspace',
            'User Created': 'Sign-in details to a newly created user',
        },
    },
    {
        title: 'Projects & tasks',
        items: {
            'Project Assignment': 'To a person added to a project',
            'Task Assignment': 'To the person a task is assigned to',
            'Comment Mention': 'To a person @mentioned in a task comment',
        },
    },
    {
        title: 'Clients',
        items: {
            'Invoice Notification': 'Invoice emailed to the client',
            'New Contract': 'Contract emailed to the client',
            'Expense Notification': 'Expense details emailed to the client',
        },
    },
    {
        title: 'Meetings',
        items: {
            'Zoom Meeting Notification': 'Invitation to the Zoom meeting’s members',
            'Google Meeting Notification': 'Invitation to the Google Meet’s members',
        },
    },
    {
        title: 'To-dos',
        items: {
            'Todo Created': 'To the to-do’s members when it is created',
            'Todo Status Updated': 'When a to-do’s status changes',
            'Todo Comments Added': 'To the to-do’s members on a new comment',
        },
    },
];

export default function EmailNotificationSettings() {
    const { t } = useTranslation();
    const [notifications, setNotifications] = useState<Record<string, boolean>>({});
    const [availableNotifications, setAvailableNotifications] = useState<NotificationItem[]>([]);

    useEffect(() => {
        // Load available notifications
        axios.get(route('settings.email-notifications.available'))
            .then(response => {
                setAvailableNotifications(response.data);
            })
            .catch(error => {
                console.error('Failed to load available notifications:', error);
            });

        // Load current settings
        axios.get(route('settings.email-notifications.get'))
            .then(response => {
                setNotifications(response.data);
            })
            .catch(error => {
                console.error('Failed to load email notification settings:', error);
            });
    }, []);

    // The emails this account has, sorted into GROUPS; anything unknown goes to "Other".
    const groups = useMemo(() => {
        const available = new Map(availableNotifications.map(item => [item.name, item]));
        const used = new Set<string>();
        const result: { title: string; items: NotificationItem[] }[] = GROUPS.map(group => ({
            title: group.title,
            items: Object.entries(group.items)
                .filter(([name]) => available.has(name))
                .map(([name, description]) => {
                    used.add(name);
                    return { ...available.get(name)!, description };
                }),
        })).filter(group => group.items.length > 0);

        const other = availableNotifications.filter(item => !used.has(item.name));
        if (other.length) result.push({ title: 'Other', items: other });
        return result;
    }, [availableNotifications]);

    const total = availableNotifications.length;
    const onCount = availableNotifications.filter(item => notifications[item.name]).length;

    const handleToggle = (key: string, enabled: boolean) => {
        setNotifications(prev => ({
            ...prev,
            [key]: enabled
        }));
    };

    const setMany = (names: string[], enabled: boolean) => {
        setNotifications(prev => ({
            ...prev,
            ...Object.fromEntries(names.map(name => [name, enabled])),
        }));
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        toast.loading(t('Saving email notification settings...'));
        router.post(route('settings.email-notifications.update'), notifications, {
            preserveScroll: true,
            onSuccess: (page) => {
                toast.dismiss();
                const successMessage = page.props.flash?.success;
                const errorMessage = page.props.flash?.error;

                if (successMessage) {
                    toast.success(successMessage);
                } else if (errorMessage) {
                    toast.error(errorMessage);
                } else {
                    toast.success('Email notification settings updated successfully.');
                }
            },
            onError: () => {
                toast.error('Failed to update email notification settings.');
            }
        });
    };

    return (
        <SettingsSection
            title={t("Email Notification Settings")}
            description={t("Configure which email notifications are sent")}
            action={
                <Button onClick={handleSave} size="sm">
                    <Save className="h-4 w-4 mr-2" />
                    {t("Save Changes")}
                </Button>
            }
        >
            {total > 0 && (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-ds-fg-secondary">
                        {t('{{on}} of {{total}} emails on', { on: onCount, total })}
                    </p>
                    <div className="flex items-center gap-3">
                        <Label htmlFor="email-all" className="cursor-pointer text-sm font-medium text-ds-fg">
                            {t('All emails')}
                        </Label>
                        <Switch
                            id="email-all"
                            checked={onCount === total}
                            onCheckedChange={(checked) => setMany(availableNotifications.map(item => item.name), checked)}
                        />
                    </div>
                </div>
            )}

            {/* Two balanced columns on wide screens; a group never splits across them. */}
            <div className="gap-4 lg:columns-2">
                {groups.map(group => {
                    const names = group.items.map(item => item.name);
                    const groupOn = names.filter(name => notifications[name]).length;
                    return (
                    <div key={group.title} className="mb-4 break-inside-avoid overflow-hidden rounded-lg border border-ds-border-subtle">
                        <div className="flex items-center justify-between gap-3 border-b border-ds-border-subtle bg-ds-surface-subtle px-4 py-1.5">
                            <h4 className="text-xs font-semibold uppercase tracking-wide text-ds-fg-muted">
                                {t(group.title)}
                            </h4>
                            <div className="flex items-center gap-2">
                                <span className="text-xs tabular-nums text-ds-fg-muted">
                                    {groupOn}/{names.length}
                                </span>
                                <Switch
                                    className="origin-right scale-75"
                                    checked={groupOn === names.length}
                                    onCheckedChange={(checked) => setMany(names, checked)}
                                    aria-label={t('All {{group}} emails', { group: t(group.title) })}
                                />
                            </div>
                        </div>
                        <ul className="divide-y divide-ds-border-subtle">
                            {group.items.map(item => (
                                <li key={item.name} className="flex items-center justify-between gap-4 px-4 py-2.5">
                                    <div className="min-w-0">
                                        <Label htmlFor={`email-${item.name}`} className="cursor-pointer text-sm font-medium text-ds-fg">
                                            {t(item.label)}
                                        </Label>
                                        {item.description && (
                                            <p className="mt-0.5 text-xs text-ds-fg-muted">{t(item.description)}</p>
                                        )}
                                    </div>
                                    <Switch
                                        id={`email-${item.name}`}
                                        checked={notifications[item.name] || false}
                                        onCheckedChange={(checked) => handleToggle(item.name, checked)}
                                    />
                                </li>
                            ))}
                        </ul>
                    </div>
                    );
                })}
            </div>
        </SettingsSection>
    );
}
