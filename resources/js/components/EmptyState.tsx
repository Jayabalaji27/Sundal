import { type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';

interface EmptyStateProps {
    icon: string;          // emoji or icon
    title: string;
    description: string;
    action?: {
        label: string;
        onClick: () => void;
        icon?: ReactNode;
    };
    secondaryAction?: {
        label: string;
        onClick: () => void;
    };
    compact?: boolean;
}

export function EmptyState({ icon, title, description, action, secondaryAction, compact = false }: EmptyStateProps) {
    const { t } = useTranslation();
    return (
        <div className={`flex flex-col items-center justify-center text-center ${compact ? 'py-10 px-4' : 'py-20 px-6'}`}>
            <div className={`${compact ? 'text-4xl mb-3' : 'text-6xl mb-5'} select-none`}>{icon}</div>
            <h3 className={`font-semibold text-foreground ${compact ? 'text-base mb-1' : 'text-xl mb-2'}`}>{t(title)}</h3>
            <p className={`text-muted-foreground max-w-sm ${compact ? 'text-xs' : 'text-sm'}`}>{t(description)}</p>
            {(action || secondaryAction) && (
                <div className="flex items-center gap-3 mt-6">
                    {secondaryAction && (
                        <Button variant="outline" size={compact ? 'sm' : 'default'} onClick={secondaryAction.onClick}>
                            {t(secondaryAction.label)}
                        </Button>
                    )}
                    {action && (
                        <Button size={compact ? 'sm' : 'default'} onClick={action.onClick} className="gap-2">
                            {action.icon}
                            {t(action.label)}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}

// Pre-configured empty states for common entities
export const EMPTY_STATES = {
    projects: {
        icon: '🗂️',
        title: 'No projects yet',
        description: 'Create your first project to start tracking tasks, timesheets, and team progress.',
    },
    tasks: {
        icon: '✅',
        title: 'No tasks here',
        description: 'Break your project into tasks. Add your first one to get started.',
    },
    bugs: {
        icon: '🐛',
        title: 'No bugs reported',
        description: "Great news — no open bugs. When issues are found, they'll appear here.",
    },
    timesheets: {
        icon: '⏱️',
        title: 'No time logged',
        description: 'Start tracking time against tasks and projects to generate reports.',
    },
    invoices: {
        icon: '🧾',
        title: 'No invoices yet',
        description: 'Create your first invoice and send it directly to your client.',
    },
    members: {
        icon: '👥',
        title: 'No team members',
        description: 'Invite your team to collaborate on projects together.',
    },
    milestones: {
        icon: '🚩',
        title: 'No milestones set',
        description: 'Add milestones to track key checkpoints in your project timeline.',
    },
    notes: {
        icon: '📝',
        title: 'No notes yet',
        description: 'Add notes to keep important context linked to this project.',
    },
    messages: {
        icon: '💬',
        title: 'No messages yet',
        description: 'Start the conversation with your team.',
    },
    notifications: {
        icon: '🔔',
        title: "You're all caught up",
        description: 'No new notifications. Check back later.',
    },
    search: {
        icon: '🔍',
        title: 'No results found',
        description: 'Try different keywords or adjust your filters.',
    },
    standup: {
        icon: '☕',
        title: 'No standups for this date',
        description: 'Activity from yesterday and assigned tasks for today will appear here.',
    },
    conflicts: {
        icon: '🎉',
        title: 'No resource conflicts',
        description: 'All team members are within healthy workload limits.',
    },
} as const;
