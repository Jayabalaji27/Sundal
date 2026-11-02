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
import { AiVoiceAssistant } from '@/components/ai-voice-assistant';

declare const route: any;

export default function AppSidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { isImpersonating } = usePage().props as any;
    const { t } = useTranslation();

    return (
        <AppShell variant="sidebar">
            <FlashMessages />
            <AppSidebar />
            <AppContent id="main-content" variant="sidebar">
                {isImpersonating && (
                    <div role="status" className="sticky top-0 z-50 flex items-center justify-between gap-3 border-b border-ds-warning/30 bg-ds-warning-bg px-4 py-2 text-sm text-ds-warning-fg sm:px-6 lg:px-8">
                        <div className="flex items-center gap-2">
                            <ShieldAlert className="h-4 w-4 shrink-0" aria-hidden="true" />
                            <span>{t('You are currently impersonating this company.')}</span>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post(route('impersonate.leave'))}
                            className="cursor-pointer rounded-sm font-semibold underline underline-offset-2 hover:no-underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus"
                        >
                            {t('Back to Admin')}
                        </button>
                    </div>
                )}
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            <AiVoiceAssistant />
        </AppShell>
    );
}
