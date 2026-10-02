import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';

declare const route: any;

interface Props {
    tab?: string;
}

/** Meetings shell — redirects to Zoom or Google Meetings. */
export default function Meetings({ tab }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];

    const tabs = [
        { key: 'zoom',   label: t('Zoom'),        perm: 'zoom_meeting_view_any',   route: 'zoom-meetings.index' },
        { key: 'google', label: t('Google Meet'), perm: 'google_meeting_view_any', route: 'google-meetings.index' },
    ].filter(t => hasPermission(permissions, t.perm));

    const active = tab ?? tabs[0]?.key;

    useEffect(() => {
        const target = tabs.find(t => t.key === active) ?? tabs[0];
        if (target) router.visit(route(target.route), { replace: true });
    }, []);

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Meetings') },
    ];

    return (
        <PageTemplate title={t('Meetings')} breadcrumbs={breadcrumbs}>
            <Tabs value={active}>
                <TabsList>
                    {tabs.map(t => (
                        <TabsTrigger
                            key={t.key}
                            value={t.key}
                            onClick={() => router.visit(route(t.route))}
                        >
                            {t.label}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </Tabs>
        </PageTemplate>
    );
}
