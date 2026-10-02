import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { ProfileMenu } from '@/components/profile-menu';
import { LanguageSwitcher } from '@/components/language-switcher';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import NavigationTimer from '@/components/timesheets/NavigationTimer';
import { NotificationDropdown } from '@/components/notification-dropdown';
import { usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Building2, Search } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { t } = useTranslation();
    const { position } = useLayout();
    const { auth } = usePage().props as any;
    const currentWorkspace = auth?.user?.current_workspace;

    return (
        <>
            <header className="border-sidebar-border/50 flex min-h-14 shrink-0 items-center gap-2 border-b px-4 py-2 transition-[width,height] ease-linear sm:px-6 lg:px-[50px] group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-12">
            <div className="flex w-full flex-wrap items-center justify-between gap-y-2">
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    {position === 'left' && <SidebarTrigger className="-ml-1 shrink-0" />}
                    {auth?.user?.type === 'company' && (
                        <div className="flex shrink-0 items-center gap-1 text-sm text-muted-foreground">
                            <button
                                type="button"
                                onClick={() => router.get(route('dashboard'))}
                                className="flex items-center gap-1 hover:text-foreground transition-colors"
                            >
                                <Building2 className="h-4 w-4" />
                                <span>{currentWorkspace?.name || 'No Workspace'}</span>
                            </button>
                            {breadcrumbs.length > 0 && <span className="mx-1">/</span>}
                        </div>
                    )}
                    <Breadcrumbs items={breadcrumbs.map(b => ({ label: b.title, href: b.href }))} />
                </div>
                <div className="flex flex-wrap shrink-0 items-center justify-end gap-2">
                    {(usePage().props as any).isImpersonating && (
                        <button
                            onClick={() => router.post(route('impersonate.leave'))}
                            className="bg-red-500 cursor-pointer text-white px-2 py-1 rounded text-xs hover:bg-red-600"
                        >
                            {t("Return Back")}
                        </button>
                    )}

                    {/* ⌘K Command Palette trigger */}
                    <button
                        onClick={() => window.dispatchEvent(new CustomEvent('open-command-palette'))}
                        className="hidden sm:flex items-center gap-2 h-9 px-3 rounded-md border shadow-sm bg-white dark:bg-gray-900 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-xs text-muted-foreground"
                        title={t('Search everything (⌘K)')}
                    >
                        <Search className="h-3.5 w-3.5" />
                        <span className="hidden md:inline">{t('Search...')}</span>
                        <kbd className="hidden md:inline-flex h-4 items-center rounded border bg-muted px-1 font-mono text-[9px]">⌘K</kbd>
                    </button>
                    {auth?.user?.type !== 'superadmin' && <NavigationTimer />}
                    <WorkspaceSwitcher />
                    <LanguageSwitcher />
                    <NotificationDropdown />
                    <ProfileMenu />
                    {position === 'right' && <SidebarTrigger className="-mr-1" />}
                </div>
            </div>
        </header>
        </>
    );
}
