import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';
import { usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ShieldAlert } from 'lucide-react';
import FlashMessages from '@/components/FlashMessages';
import { CustomToast } from '@/components/custom-toast';

declare const route: any;

export default function AppSidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { isImpersonating } = usePage().props as any;
    const { t } = useTranslation();

    return (
        <AppShell variant="sidebar">
            <FlashMessages />
            <CustomToast />
            <AppSidebar />
            <AppContent id="main-content" variant="sidebar">
                {isImpersonating && (
                    <div className="flex items-center justify-between bg-amber-500 text-white text-sm px-4 py-2 sticky top-0 z-50">
                        <div className="flex items-center gap-2">
                            <ShieldAlert className="h-4 w-4" />
                            <span>{t('You are currently impersonating this company.')}</span>
                        </div>
                        <button
                            onClick={() => router.post(route('impersonate.leave'))}
                            className="underline font-semibold hover:opacity-80"
                        >
                            {t('Back to Admin')}
                        </button>
                    </div>
                )}
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
