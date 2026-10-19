import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { useSidebarSettings } from '@/contexts/SidebarContext';
import { useBrand } from '@/contexts/BrandContext';

import { type NavItem } from '@/types';
import { Link, usePage, router } from '@inertiajs/react';
import {
    LayoutGrid, Settings, FileText, CheckSquare, Calendar, CreditCard,
    Ticket, Gift, DollarSign, MessageSquare, Globe, FolderOpen,
    ClipboardList, Clock, Bot, Video, Building2, BarChart3, BookOpen,
    TrendingUp, Radio, Bug, ListTodo, Receipt, FileIcon, Zap, AlertTriangle,
    Bell, Mail, FolderKanban, Users, History, Sparkles, ClipboardCheck
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLogo from './app-logo';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';
import { useIsMobile } from '@/hooks/use-mobile';

declare const route: any;



export function AppSidebar() {
    const { t, i18n } = useTranslation();
    const { auth, isSaasMode, globalSettings, features } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const flags = features ?? {};
    
    // Check if current language is RTL
    const isRTL = ['ar', 'he'].includes(i18n.language);

    const isSuperAdmin = auth?.user?.type === 'superadmin';
    // On desktop the AI Assistant opens from the "AI mode" switch in the header.
    const isMobile = useIsMobile();

    // ─── SaaS Super Admin ────────────────────────────────────────────────────
    // Login History lives in the profile menu, as it does for company owners.
    const getSuperAdminNavItems = (): NavItem[] => {
        const items: NavItem[] = [];
        if (hasPermission(permissions, 'dashboard_view')) {
            items.push({ title: t('Dashboard'), href: route('dashboard'), icon: LayoutGrid });
        }
        if (hasPermission(permissions, 'company_view_any')) {
            items.push({ title: t('Companies'), href: route('companies.index'), icon: Building2 });
        }
        // Media Library is intentionally NOT offered to superadmin: the route sits behind
        // block.superadmin.workspace middleware (superadmin has no current_workspace_id and
        // is meant to reach workspace-scoped data only via Impersonate), so linking it here
        // always dead-ends in a 403.
        // Subscriptions: plan requests and plan orders are one queue (a company wants a plan).
        if (hasPermission(permissions, 'plan_manage_orders') || hasPermission(permissions, 'plan_manage_requests')) {
            items.push({
                title: t('Subscriptions'),
                href: hasPermission(permissions, 'plan_manage_orders') ? route('plan-orders.index') : route('plan-requests.index'),
                icon: Receipt,
                matches: [route('plan-orders.index'), route('plan-requests.index')],
            });
        }
        if (hasPermission(permissions, 'plan_view_any')) {
            items.push({ title: t('Plans'), href: route('plans.index'), icon: CreditCard });
        }
        if (hasPermission(permissions, 'coupon_view_any')) {
            items.push({ title: t('Coupons'), href: route('coupons.index'), icon: Ticket });
        }
        if (flags.currency_mgmt && hasPermission(permissions, 'currency_view_any')) {
            items.push({ title: t('Currency'), href: route('currencies.index'), icon: DollarSign });
        }
        if (flags.referral && hasPermission(permissions, 'referral_view_any')) {
            items.push({ title: t('Referral Program'), href: route('referral.index'), icon: Gift });
        }
        const websiteChildren = [];
        if (hasPermission(permissions, 'landing_page_manage')) websiteChildren.push({ title: t('Landing Page'), href: route('landing-page.settings') });
        if (hasPermission(permissions, 'custom_page_view_any')) websiteChildren.push({ title: t('Custom Pages'), href: route('landing-page.custom-pages.index') });
        if (hasPermission(permissions, 'contact_view_any')) websiteChildren.push({ title: t('Contact Inquiries'), href: route('contacts.index') });
        if (flags.newsletter && hasPermission(permissions, 'newsletter_view_any')) websiteChildren.push({ title: t('Newsletter'), href: route('newsletters.index') });
        if (websiteChildren.length > 0) {
            items.push({ title: t('Website'), icon: Globe, children: websiteChildren });
        }
        if (hasPermission(permissions, 'settings_view')) {
            items.push({ title: t('Settings'), href: route('settings'), icon: Settings });
        }
        return items;
    };

    // The one section heading in the workspace sidebar: optional tools, each behind a
    // feature flag (config/features.php). Everything above it is one short list.
    const TOOLS_GROUP = t('Tools');

    // ─── Workspace users (company, manager, member, client) ──────────────────
    // Each role sees the pages its work needs, in the order a day goes.
    // Billing, Login History and Settings live in the profile menu.
    const buildCommonNavItems = (): NavItem[] => {
        const main: NavItem[] = [];
        const tools: NavItem[] = [];
        // Chat, Meetings, Knowledge Base and AI need the Pro Add-on (CheckModuleAccess);
        // hide them instead of linking to a route that bounces back to the dashboard.
        const modulesLocked = !!auth?.modulesLocked;
        const workspaceRole = auth?.user?.workspace_role;
        const isOwner = workspaceRole === 'owner';
        const isManager = workspaceRole === 'manager';
        const isMember = workspaceRole === 'member';
        const isClient = workspaceRole === 'client';
        const can = (permission: string) => hasPermission(permissions, permission);
        const currentWorkspaceId = auth?.user?.current_workspace_id;

        if (can('dashboard_view')) {
            main.push({ title: t('Dashboard'), href: route('dashboard'), icon: LayoutGrid });
        }
        if (can('project_view_any')) {
            main.push({ title: t('Projects'), href: route('projects.index'), icon: FolderOpen });
        }
        if (can('task_view_any')) {
            // Members only see their own tasks there, so say so.
            main.push({ title: isMember ? t('My Tasks') : t('Tasks'), href: route('tasks.index'), icon: CheckSquare });
        }
        // One timesheet page: the week, submitted from the same screen. Owners log
        // time from the header timer; reviewing time is in Approvals.
        if (can('timesheet_view_any') && !isOwner) {
            main.push({ title: t('Timesheet'), href: route('timesheets.weekly-view'), icon: Clock, matches: [route('timesheets.index')] });
        }
        // Meetings sit with the day-to-day work (after Timesheet, as in Zoho Sprints), for
        // everyone who may see meetings. Until Zoom or Google Meet is connected
        // (Settings → Integrations) the page says so.
        if (flags.meetings && !modulesLocked && (can('zoom_meeting_view_any') || can('google_meeting_view_any'))) {
            main.push({ title: t('Meetings'), href: route('meetings.index'), icon: Video, forceFullReload: true, matches: [route('zoom-meetings.index'), route('google-meetings.index')] });
        }
        // Timesheets and expenses waiting for a decision, in one inbox.
        if (can('timesheet_approve') || can('expense_approval_view_any')) {
            const waiting = auth?.approvalsWaiting;
            main.push({
                title: t('Approvals'),
                href: route('approvals.index'),
                icon: ClipboardCheck,
                forceFullReload: true,
                badge: waiting ? { label: String(waiting) } : undefined,
                matches: [route('timesheet-approvals.index'), route('expense-approvals.index')],
            });
        }
        // Members claim expenses; owners and managers review them in Approvals.
        if (isMember && can('expense_view_any')) {
            main.push({ title: t('Expenses'), href: route('expenses.index'), icon: Receipt });
        }
        if (can('invoice_view_any') && !isMember) {
            main.push({ title: t('Invoices'), href: route('invoices.index'), icon: Receipt });
        }
        if (flags.budgets_page && can('budget_view_any') && !isClient) {
            main.push({ title: t('Budgets'), href: route('budgets.index'), icon: DollarSign });
        }
        // Contracts are between the owner and the client (e-signature).
        if (flags.contracts && can('contract_view_any') && (isOwner || isClient)) {
            try { main.push({ title: t('Contracts'), href: route('contracts.index'), icon: FileText }); } catch {}
        }
        // Reports: hours and amounts by project and person.
        if (can('report_timesheet')) {
            main.push({ title: t('Reports'), href: route('timesheet-reports.index'), icon: BarChart3 });
        }
        if (flags.project_reports && can('project_report_view_any')) {
            main.push({ title: t('Project Reports'), href: route('project-reports.index'), icon: BarChart3 });
        }
        if (can('team_view') && currentWorkspaceId && (isOwner || isManager)) {
            main.push({ title: t('Team'), href: route('team.index', currentWorkspaceId), icon: Users });
        }

        // ── Optional tools ──
        if (flags.bugs_menu && can('bug_view_any')) {
            tools.push({ title: t('Bugs'), href: route('tasks.index', { type: 'bug' }), icon: Bug, group: TOOLS_GROUP });
        }
        if (flags.calendar && can('task_calendar_view')) {
            try { tools.push({ title: t('Calendar'), href: route('task-calendar.index'), icon: Calendar, group: TOOLS_GROUP }); } catch {}
        }
        if (flags.chat && !modulesLocked && can('chat_view')) {
            tools.push({ title: t('Chat'), href: route('chat.index'), icon: MessageSquare, group: TOOLS_GROUP });
        }
        if (flags.knowledge_base && !modulesLocked && can('kb_view_any')) {
            tools.push({ title: t('Knowledge Base'), href: route('kb.index'), icon: BookOpen, group: TOOLS_GROUP });
        }
        if (flags.notes && can('note_view_any')) {
            tools.push({ title: t('Notes'), href: route('notes.index'), icon: BookOpen, group: TOOLS_GROUP });
        }
        // Forms are built by owners and managers; members only fill in the public link.
        if (flags.forms && can('form_view_any') && !isMember) {
            tools.push({ title: t('Forms'), href: route('forms.index'), icon: ClipboardList, group: TOOLS_GROUP });
        }
        if (flags.portfolios && can('project_view_any')) {
            try { tools.push({ title: t('Portfolios'), href: route('portfolios.index'), icon: FolderKanban, group: TOOLS_GROUP }); } catch {}
        }
        if (flags.ai && !modulesLocked && (can('agent_use') || can('agent_view_any'))) {
            tools.push({ title: t('AI'), href: route('ai.index'), icon: Bot, group: TOOLS_GROUP });
        }
        // AI Assistant: owners and managers only (aiAssistant is null for other roles).
        // Owners without the AI add-on still see it, and the route sends them to the
        // plans page; managers only see it when the owner's plan includes AI.
        // On desktop it opens from the "AI mode" switch in the header instead.
        const aiAssistant: string | null = auth?.aiAssistant ?? null;
        if (flags.ai_assistant && isMobile && aiAssistant && (aiAssistant !== 'plan' || isOwner)) {
            tools.push({ title: t('AI Assistant'), href: route('ai-assistant.index'), icon: Sparkles, group: TOOLS_GROUP });
        }

        return [...main, ...tools];
    };

    const getNavItems = (): NavItem[] => {
        if (isSaasMode && isSuperAdmin) return getSuperAdminNavItems();
        return buildCommonNavItems();
    };

    // Memoized so NavMain (which re-syncs its expanded-menu state whenever this
    // `items` array reference changes) doesn't see a brand-new array on every
    // unrelated re-render of AppSidebar — an unmemoized array here fed a
    // setState-in-useEffect loop that could trip React's update-depth limit.
    const mainNavItems = useMemo(
        () => getNavItems(),
        [isSaasMode, isSuperAdmin, permissions, flags, auth?.user?.current_workspace_id, auth?.user?.workspace_role, isMobile, t]
    );

    const { position, effectivePosition, isRtl } = useLayout();
    const { variant, collapsible, style } = useSidebarSettings();
    const { logoLight, logoDark, favicon, titleText, updateBrandSettings } = useBrand();
    const [sidebarStyle, setSidebarStyle] = useState({});

    useEffect(() => {

        // Apply styles based on sidebar style
        if (style === 'colored') {
            setSidebarStyle({ backgroundColor: 'var(--primary)', color: 'white' });
        } else if (style === 'gradient') {
            setSidebarStyle({
                background: 'linear-gradient(to bottom, var(--primary), color-mix(in srgb, var(--primary), transparent 20%))',
                color: 'white'
            });
        } else {
            setSidebarStyle({});
        }
    }, [style]);

    const filteredNavItems = mainNavItems;

    // Get the first available menu item's href for logo link
    const getFirstAvailableHref = () => {
        if (filteredNavItems.length === 0) return route('dashboard');

        const firstItem = filteredNavItems[0];
        if (firstItem.href) {
            return firstItem.href;
        } else if (firstItem.children && firstItem.children.length > 0) {
            return firstItem.children[0].href || route('dashboard');
        }
        return route('dashboard');
    };

    return (
        <Sidebar
            key={`sidebar-${effectivePosition}-${isRtl}`}
            side={effectivePosition}
            collapsible={collapsible}
            variant={variant}
            className={style !== 'plain' ? 'sidebar-custom-style' : ''}
        >
            <SidebarHeader className={style !== 'plain' ? 'sidebar-styled' : ''} style={sidebarStyle}>
                <div className="flex justify-center items-center p-2">
                    <Link href={getFirstAvailableHref()} prefetch className="flex items-center justify-center">
                        {/* Logo for expanded sidebar */}
                        <div className="h-8 group-data-[collapsible=icon]:hidden flex items-center">
                            {(() => {
                                const isDark = document.documentElement.classList.contains('dark');
                                const currentLogo = isDark ? logoLight : logoDark;
                                const displayUrl = currentLogo ? (
                                    currentLogo.startsWith('http') ? currentLogo :
                                        currentLogo.startsWith('/storage/') ? `${window.location.origin}${currentLogo}` :
                                            currentLogo.startsWith('/') ? `${window.location.origin}${currentLogo}` : currentLogo
                                ) : '';

                                return displayUrl ? (
                                    <img
                                        key={`${currentLogo}-${Date.now()}`}
                                        src={displayUrl}
                                        alt="Logo"
                                        className="h-8 w-auto max-w-[120px] transition-all duration-200"
                                        onError={() => updateBrandSettings({ [isDark ? 'logoLight' : 'logoDark']: '' })}
                                    />
                                ) : (
                                    <div className="h-8 flex items-center gap-1.5">
                                        <div className="w-7 h-7 rounded-lg bg-primary flex items-center justify-center shrink-0">
                                            <span className="text-primary-foreground font-bold text-sm leading-none">
                                                {(titleText || 'S')[0].toUpperCase()}
                                            </span>
                                        </div>
                                        <span className="font-bold text-base tracking-tight text-sidebar-foreground">
                                            {titleText || 'SUNDAL'}
                                        </span>
                                    </div>
                                );
                            })()}
                        </div>

                        {/* Icon for collapsed sidebar */}
                        <div className="h-8 w-8 hidden group-data-[collapsible=icon]:block">
                            {(() => {
                                const displayFavicon = favicon ? (
                                    favicon.startsWith('http') ? favicon :
                                        favicon.startsWith('/storage/') ? `${window.location.origin}${favicon}` :
                                            favicon.startsWith('/') ? `${window.location.origin}${favicon}` : favicon
                                ) : '';

                                return displayFavicon ? (
                                    <img
                                        key={`${favicon}-${Date.now()}`}
                                        src={displayFavicon}
                                        alt="Icon"
                                        className="h-8 w-8 transition-all duration-200"
                                        onError={() => updateBrandSettings({ favicon: '' })}
                                    />
                                ) : (
                                    <div className="h-8 w-8 bg-primary text-primary-foreground rounded-lg flex items-center justify-center font-bold text-sm shadow-sm">
                                        {(titleText || 'S')[0].toUpperCase()}
                                    </div>
                                );
                            })()}
                        </div>
                    </Link>
                </div>
                {/* Workspace picker under the logo (only for people in more than one workspace). */}
                {!(isSaasMode && isSuperAdmin) && (
                    <div className="px-2 pb-1">
                        <WorkspaceSwitcher variant="sidebar" />
                    </div>
                )}
            </SidebarHeader>

            <SidebarContent style={sidebarStyle} className={style !== 'plain' ? 'sidebar-styled' : ''}>
                <NavMain
                    items={filteredNavItems}
                    position={effectivePosition}
                    sidebarStyle={style}
                    collapsibleGroups={isSaasMode && isSuperAdmin ? [] : [TOOLS_GROUP]}
                />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" position={position} /> */}
                {/* Profile menu moved to header */}
            </SidebarFooter>
        </Sidebar>
    );
}