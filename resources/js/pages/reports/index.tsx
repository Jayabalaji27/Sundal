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

/** Reports shell — redirects to the active report module's own page. */
export default function Reports({ tab }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];

    const tabs = [
        { key: 'project',    label: t('Project'),    perm: 'project_report_view_any',  route: 'project-reports.index' },
        { key: 'timesheet',  label: t('Timesheet'),  perm: 'report_timesheet',          route: 'timesheet-reports.index' },
        { key: 'budget',     label: t('Budget'),     perm: 'budget_view_any',           route: 'budgets.dashboard' },
    ].filter(t => hasPermission(permissions, t.perm));

    const active = tab ?? tabs[0]?.key;

    useEffect(() => {
        const target = tabs.find(t => t.key === active) ?? tabs[0];
        if (target) router.visit(route(target.route), { replace: true });
    }, []);

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Reports') },
    ];

    return (
        <PageTemplate title={t('Reports')} breadcrumbs={breadcrumbs}>
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
