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

/**
 * Finance shell — routes /finance?tab=invoices|budgets|expenses.
 * Redirects immediately to the active module's own page.
 */
export default function Finance({ tab }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];

    const tabs = [
        { key: 'invoices',  label: t('Invoices'),  perm: 'invoice_view_any',  route: 'invoices.index' },
        { key: 'budgets',   label: t('Budgets'),   perm: 'budget_view_any',   route: 'budgets.index' },
        { key: 'expenses',  label: t('Expenses'),  perm: 'expense_view_any',  route: 'expenses.index' },
    ].filter(t => hasPermission(permissions, t.perm));

    const active = tab ?? tabs[0]?.key;

    // Redirect to the real page for the active tab
    useEffect(() => {
        const target = tabs.find(t => t.key === active) ?? tabs[0];
        if (target) router.visit(route(target.route), { replace: true });
    }, []);

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Finance') },
    ];

    return (
        <PageTemplate title={t('Finance')} breadcrumbs={breadcrumbs}>
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
