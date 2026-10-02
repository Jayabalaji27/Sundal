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

/** Docs shell — merges Knowledge Base and Notes under one nav entry, redirects to the active tab. */
export default function Docs({ tab }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];

    const tabs = [
        { key: 'kb',    label: t('Knowledge Base'), perm: 'kb_view_any',   route: 'kb.index' },
        { key: 'notes', label: t('Notes'),          perm: 'note_view_any', route: 'notes.index' },
    ].filter(t => hasPermission(permissions, t.perm));

    const active = tab ?? tabs[0]?.key;

    useEffect(() => {
        const target = tabs.find(t => t.key === active) ?? tabs[0];
        if (target) router.visit(route(target.route), { replace: true });
    }, []);

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Docs') },
    ];

    return (
        <PageTemplate title={t('Docs')} breadcrumbs={breadcrumbs}>
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
