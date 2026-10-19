import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { ProfileMenu } from '@/components/profile-menu';
import NavigationTimer from '@/components/timesheets/NavigationTimer';
import { AiModeToggle } from '@/components/ai-mode-toggle';
import { NotificationDropdown } from '@/components/notification-dropdown';
import { GlobalCreateMenu } from '@/components/global-create-menu';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Search } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { t } = useTranslation();
    const { position } = useLayout();
    const { auth, features } = usePage().props as any;
    const isSuperAdmin = auth?.user?.type === 'superadmin';

    // The page title already names a top-level page, and the sidebar shows where
    // it sits, so a trail is only worth showing on nested pages (Projects > LMS).
    // A leading Dashboard crumb is dropped: the sidebar always links there.
    const dashboardUrl = route('dashboard');
    const trail = breadcrumbs.filter((crumb, index) => !(index === 0 && (crumb.href === dashboardUrl || crumb.title === t('Dashboard'))));
    const showTrail = trail.length > 1;

    return (
        <header className="border-sidebar-border/50 flex min-h-14 shrink-0 items-center gap-2 border-b px-4 py-2 transition-[width,height] ease-linear sm:px-6 lg:px-[50px] group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-12">
            <div className="flex w-full items-center justify-between gap-2">
                <div className="flex min-w-0 flex-1 items-center gap-2">
                    {position === 'left' && <SidebarTrigger className="-ml-1 shrink-0" />}
                    {showTrail && <Breadcrumbs items={trail.map(b => ({ label: b.title, href: b.href }))} />}
                </div>
                {/* Standard order: search, create, then icon actions and the account. */}
                <div className="flex shrink-0 items-center justify-end gap-2">
                    {/* Command palette trigger (the keyboard shortcut still works) */}
                    <button
                        onClick={() => window.dispatchEvent(new CustomEvent('open-command-palette'))}
                        className="flex h-9 w-9 items-center justify-center gap-2 rounded-md border bg-background text-sm text-muted-foreground transition-colors hover:bg-muted md:w-64 md:justify-start md:px-3"
                        title={t('Search everything')}
                        aria-label={t('Search everything')}
                    >
                        <Search className="h-4 w-4 shrink-0" />
                        <span className="hidden md:inline">{t('Search...')}</span>
                    </button>
                    {/* Leaving impersonation is offered by the banner above the header. */}
                    <GlobalCreateMenu />
                    {/* Owners and managers with AI access (the button checks that itself). */}
                    {features?.ai_assistant && <AiModeToggle />}
                    {/* Shown only while a timer is running (e.g. one started from the AI Assistant). */}
                    {!isSuperAdmin && <NavigationTimer />}
                    {/* Language is picked on the Profile page, for every role; managing
                        languages is under Settings. */}
                    <NotificationDropdown />
                    <ProfileMenu />
                    {position === 'right' && <SidebarTrigger className="-mr-1" />}
                </div>
            </div>
        </header>
    );
}
