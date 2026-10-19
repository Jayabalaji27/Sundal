import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

/**
 * Plan orders (payments) and plan requests (asks for a plan) are one queue for the
 * platform admin: "a company wants a plan". Both pages share these tabs.
 */
export function SubscriptionTabs({ active }: { active: 'orders' | 'requests' }) {
    const { t } = useTranslation();
    const tabs = [
        { key: 'orders', label: t('Orders'), href: route('plan-orders.index') },
        { key: 'requests', label: t('Requests'), href: route('plan-requests.index') },
    ];

    return (
        <div className="mb-4 flex gap-1 border-b">
            {tabs.map(tab => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    className={cn(
                        '-mb-px border-b-2 px-3 pb-2 text-sm font-medium transition-colors',
                        tab.key === active ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}
