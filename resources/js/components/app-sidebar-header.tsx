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

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

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
        // rounded-t-[inherit] follows the page panel's rounded top corners.
        <header className="flex h-13 shrink-0 items-center gap-2 rounded-t-[inherit] border-b border-ds-border-subtle bg-ds-surface px-4 sm:px-6 lg:px-8">
            <div className="flex w-full items-center justify-between gap-2">
                <div className="flex min-w-0 flex-1 items-center gap-2">
                    {position === 'left' && <SidebarTrigger className="-ml-2 size-9 shrink-0 text-ds-fg-muted" />}
                    {showTrail && <Breadcrumbs items={trail.map(b => ({ label: b.title, href: b.href }))} />}
                </div>
                {/* Standard order: search, create, then icon actions and the account. */}
                <div className="flex shrink-0 items-center justify-end gap-1.5">
                    {/* Command palette trigger (the keyboard shortcut still works).
                        A soft filled field rather than a boxed input, with the shortcut shown. */}
                    <button
                        type="button"
                        onClick={() => window.dispatchEvent(new CustomEvent('open-command-palette'))}
                        className="mr-1 flex h-9 w-9 cursor-pointer items-center justify-center gap-2 rounded-full bg-ds-surface-subtle text-sm text-ds-fg-placeholder ring-1 ring-transparent transition-all duration-150 hover:bg-ds-surface-hover hover:ring-ds-border-subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus md:w-64 md:justify-start md:pl-3 md:pr-1.5"
                        title={t('Search everything')}
                        aria-label={t('Search everything')}
                        aria-keyshortcuts="Control+K Meta+K"
                    >
                        <Search className="h-4 w-4 shrink-0 text-ds-fg-muted" aria-hidden="true" />
                        <span className="hidden flex-1 text-left md:inline">{t('Search...')}</span>
                        <kbd className="hidden h-6 items-center rounded-full border border-ds-border-subtle bg-ds-surface px-2 font-sans text-[11px] font-medium text-ds-fg-muted md:inline-flex">
                            {isMac ? '⌘K' : 'Ctrl K'}
                        </kbd>
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
                    {/* Separates the workspace actions from the account. */}
                    <span className="mx-1 hidden h-6 w-px bg-ds-border-subtle sm:block" aria-hidden="true" />
                    <ProfileMenu />
                    {position === 'right' && <SidebarTrigger className="-mr-2 size-9 text-ds-fg-muted" />}
                </div>
            </div>
        </header>
    );
}
