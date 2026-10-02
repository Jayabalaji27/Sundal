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
    Bell, Mail, FolderKanban, Users
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLogo from './app-logo';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';

declare const route: any;



export function AppSidebar() {
    const { t, i18n } = useTranslation();
    const { auth, isSaasMode, globalSettings, features } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const flags = features ?? {};
    
    // Check if current language is RTL
    const isRTL = ['ar', 'he'].includes(i18n.language);

    const isSuperAdmin = auth?.user?.type === 'superadmin';

    // ─── SaaS Super Admin ────────────────────────────────────────────────────
    const getSuperAdminNavItems = (): NavItem[] => {
        const items: NavItem[] = [];
        if (hasPermission(permissions, 'dashboard_view')) {
            items.push({ title: t('Dashboard'), href: route('dashboard'), icon: LayoutGrid, group: t('Overview') });
        }
        if (hasPermission(permissions, 'company_view_any')) {
            items.push({ title: t('Companies'), href: route('companies.index'), icon: Building2, group: t('Management') });
        }
        // Media Library is intentionally NOT offered to superadmin: the route sits behind
        // block.superadmin.workspace middleware (superadmin has no current_workspace_id and
        // is meant to reach workspace-scoped data only via Impersonate), so linking it here
        // always dead-ends in a 403.
        if (hasPermission(permissions, 'plan_view_any') || hasPermission(permissions, 'plan_manage_requests') || hasPermission(permissions, 'plan_manage_orders')) {
            const planChildren = [];
            if (hasPermission(permissions, 'plan_view_any')) planChildren.push({ title: t('Plan'), href: route('plans.index') });
            if (hasPermission(permissions, 'plan_manage_requests')) planChildren.push({ title: t('Plan Requests'), href: route('plan-requests.index') });
            if (hasPermission(permissions, 'plan_manage_orders')) planChildren.push({ title: t('Plan Orders'), href: route('plan-orders.index') });
            items.push({ title: t('Plans'), icon: CreditCard, group: t('Management'), children: planChildren });
        }
        if (hasPermission(permissions, 'coupon_view_any')) {
            items.push({ title: t('Coupons'), href: route('coupons.index'), icon: Ticket, group: t('Management') });
        }
        if (flags.currency_mgmt && hasPermission(permissions, 'currency_view_any')) {
            items.push({ title: t('Currency'), href: route('currencies.index'), icon: DollarSign, group: t('Management') });
        }
        if (flags.referral && hasPermission(permissions, 'referral_view_any')) {
            items.push({ title: t('Referral Program'), href: route('referral.index'), icon: Gift, group: t('Management') });
        }
        const landingChildren = [];
        if (hasPermission(permissions, 'landing_page_manage')) landingChildren.push({ title: t('Landing Page'), href: route('landing-page.settings') });
        if (hasPermission(permissions, 'custom_page_view_any')) landingChildren.push({ title: t('Custom Pages'), href: route('landing-page.custom-pages.index') });
        if (hasPermission(permissions, 'contact_view_any')) landingChildren.push({ title: t('Contact Inquiries'), href: route('contacts.index') });
        if (hasPermission(permissions, 'newsletter_view_any')) landingChildren.push({ title: t('Newsletter'), href: route('newsletters.index') });
        if (landingChildren.length > 0) {
            items.push({ title: t('Landing Page'), icon: Globe, group: t('Management'), children: landingChildren });
        }
        if (hasPermission(permissions, 'settings_view')) {
            items.push({ title: t('Settings'), href: route('settings'), icon: Settings, group: t('System Control') });
        }
        return items;
    };

    // ─── Common items — Phase 2: 13 top-level items ──────────────────────────
    const buildCommonNavItems = (): NavItem[] => {
        const items: NavItem[] = [];

        // 1. Dashboard
        if (hasPermission(permissions, 'dashboard_view')) {
            items.push({ title: t('Dashboard'), href: route('dashboard'), icon: LayoutGrid, group: t('Overview') });
        }

        // 2. Workspaces
        if (hasPermission(permissions, 'workspace_view_any')) {
            items.push({ title: t('Workspaces'), href: route('workspaces.index'), icon: Building2, group: t('Overview') });
        }

        // Team (member management) — needs a current workspace to link into
        const currentWorkspaceId = auth?.user?.current_workspace_id;
        if (hasPermission(permissions, 'team_view') && currentWorkspaceId) {
            items.push({ title: t('Team'), href: route('team.index', currentWorkspaceId), icon: Users, group: t('Overview') });
        }

        // 3. Projects (with Tasks, Bugs, Sprints as children) — expanded by default
        // for members, so they can jump straight to Tasks/Bugs without an extra click.
        if (hasPermission(permissions, 'project_view_any') || hasPermission(permissions, 'task_view_any')) {
            const projectChildren: { title: string; href: string }[] = [];
            if (hasPermission(permissions, 'project_view_any')) {
                projectChildren.push({ title: t('All Projects'), href: route('projects.index') });
            }
            if (hasPermission(permissions, 'task_view_any')) {
                projectChildren.push({ title: t('Tasks'), href: route('tasks.index') });
            }
            if (hasPermission(permissions, 'bug_view_any')) {
                projectChildren.push({ title: t('Bugs'), href: route('bugs.index') });
            }
            if (flags.portfolios && hasPermission(permissions, 'project_view_any')) {
                try { projectChildren.push({ title: t('Portfolios'), href: route('portfolios.index') }); } catch {}
            }
            items.push({
                title: t('Projects'), icon: FolderOpen, group: t('Work'), children: projectChildren,
                defaultOpen: auth?.user?.workspace_role === 'member',
            });
        }

        // 4. Calendar
        if (hasPermission(permissions, 'task_calendar_view')) {
            try { items.push({ title: t('Calendar'), href: route('task-calendar.index'), icon: Calendar, group: t('Work') }); } catch {}
        }

        // 5. Timesheets
        if (hasPermission(permissions, 'timesheet_view_any')) {
            const timesheetChildren = [
                { title: t('My Timesheets'), href: route('timesheets.index') },
                { title: t('Weekly'), href: route('timesheets.weekly-view') },
            ];
            if (hasPermission(permissions, 'timesheet_approve')) {
                timesheetChildren.push({ title: t('Approvals'), href: route('timesheet-approvals.index') });
            }
            items.push({ title: t('Timesheets'), icon: Clock, group: t('Work'), children: timesheetChildren });
        }

        // 6. Contracts — kept in the Work group, right after Timesheets, so the
        // Work group stays contiguous (Communication is inserted right after it).
        if (hasPermission(permissions, 'contract_view_any')) {
            try { items.push({ title: t('Contracts'), href: route('contracts.index'), icon: FileText, group: t('Work') }); } catch {}
        }

        // 7. Communication (Chat + Meetings) — placed directly after the Work group.
        if (hasPermission(permissions, 'chat_view')) {
            items.push({ title: t('Chat'), href: route('chat.index'), icon: MessageSquare, group: t('Communication') });
        }
        if (hasPermission(permissions, 'zoom_meeting_view_any') || hasPermission(permissions, 'google_meeting_view_any')) {
            const meetingsChildren: { title: string; href: string }[] = [];
            if (hasPermission(permissions, 'zoom_meeting_view_any')) {
                meetingsChildren.push({ title: t('Zoom Meetings'), href: route('zoom-meetings.index') });
            }
            if (hasPermission(permissions, 'google_meeting_view_any')) {
                meetingsChildren.push({ title: t('Google Meetings'), href: route('google-meetings.index') });
            }
            items.push({ title: t('Meetings'), icon: Video, group: t('Communication'), children: meetingsChildren });
        }

        // 8. Finance (unified shell → Invoices, Budgets, Expenses, Expense Approvals)
        if (hasPermission(permissions, 'invoice_view_any') || hasPermission(permissions, 'budget_view_any') || hasPermission(permissions, 'expense_view_any')) {
            const financeChildren: { title: string; href: string }[] = [];
            if (hasPermission(permissions, 'invoice_view_any')) {
                financeChildren.push({ title: t('Invoices'), href: route('invoices.index') });
            }
            if (hasPermission(permissions, 'budget_view_any')) {
                financeChildren.push({ title: t('Budgets'), href: route('budgets.index') });
            }
            if (hasPermission(permissions, 'expense_view_any')) {
                financeChildren.push({ title: t('Expenses'), href: route('expenses.index') });
            }
            if (hasPermission(permissions, 'expense_approval_view_any')) {
                financeChildren.push({ title: t('Expense Approvals'), href: route('expense-approvals.index') });
            }
            if (hasPermission(permissions, 'tax_view_any')) {
                financeChildren.push({ title: t('Taxes'), href: route('taxes.index') });
            }
            items.push({ title: t('Finance'), icon: FileText, group: t('Finance'), children: financeChildren });
        }

        // 9. Docs (Knowledge Base + Notes merged)
        if (hasPermission(permissions, 'kb_view_any') || hasPermission(permissions, 'note_view_any')) {
            const docsChildren: { title: string; href: string }[] = [];
            if (hasPermission(permissions, 'kb_view_any')) {
                docsChildren.push({ title: t('Knowledge Base'), href: route('kb.index') });
            }
            if (hasPermission(permissions, 'note_view_any')) {
                docsChildren.push({ title: t('Notes'), href: route('notes.index') });
            }
            items.push({ title: t('Docs'), icon: BookOpen, group: t('Content'), children: docsChildren });
        }

        // 10. Forms
        if (hasPermission(permissions, 'form_view_any')) {
            items.push({ title: t('Forms'), href: route('forms.index'), icon: ClipboardList, group: t('Content') });
        }

        // 11. AI (Risk Radar + Resource Conflicts + custom agents)
        if (hasPermission(permissions, 'agent_use') || hasPermission(permissions, 'agent_view_any')) {
            items.push({ title: t('AI'), href: route('ai.index'), icon: Bot, group: t('Intelligence') });
        }

        // 12. Reports
        if (hasPermission(permissions, 'project_report_view_any') || hasPermission(permissions, 'report_timesheet')) {
            const reportsChildren: { title: string; href: string }[] = [];
            if (hasPermission(permissions, 'project_report_view_any')) {
                reportsChildren.push({ title: t('Project Reports'), href: route('project-reports.index') });
            }
            if (hasPermission(permissions, 'report_timesheet')) {
                reportsChildren.push({ title: t('Timesheet Reports'), href: route('timesheet-reports.index') });
            }
            items.push({ title: t('Reports'), icon: BarChart3, group: t('Intelligence'), children: reportsChildren });
        }

        return items;
    };

    // ─── SaaS Company User ───────────────────────────────────────────────────
    const getSaasNavItems = (): NavItem[] => {
        const items = buildCommonNavItems();
        // Plans (billing) — single entry
        if (hasPermission(permissions, 'plan_view_any') || hasPermission(permissions, 'plan_view_my_requests') || hasPermission(permissions, 'plan_view_my_orders')) {
            items.push({ title: t('Billing'), href: route('plans.index'), icon: CreditCard, group: t('Account') });
        }
        if (hasPermission(permissions, 'settings_view')) {
            items.push({ title: t('Settings'), href: route('settings'), icon: Settings, group: t('Account') });
        }
        return items;
    };

    // ─── Non-SaaS ────────────────────────────────────────────────────────────
    const getNonSaasNavItems = (): NavItem[] => {
        const items = buildCommonNavItems();
        if (hasPermission(permissions, 'settings_view')) {
            items.push({ title: t('Settings'), href: route('settings'), icon: Settings, group: t('Account') });
        }
        return items;
    };

    const getNavItems = (): NavItem[] => {
        if (isSaasMode && isSuperAdmin) return getSuperAdminNavItems();
        if (isSaasMode) return getSaasNavItems();
        return getNonSaasNavItems();
    };

    // Memoized so NavMain (which re-syncs its expanded-menu state whenever this
    // `items` array reference changes) doesn't see a brand-new array on every
    // unrelated re-render of AppSidebar — an unmemoized array here fed a
    // setState-in-useEffect loop that could trip React's update-depth limit.
    const mainNavItems = useMemo(
        () => getNavItems(),
        [isSaasMode, isSuperAdmin, permissions, flags, auth?.user?.current_workspace_id, auth?.user?.workspace_role, t]
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


            </SidebarHeader>

            <SidebarContent style={sidebarStyle} className={style !== 'plain' ? 'sidebar-styled' : ''}>
                <NavMain items={filteredNavItems} position={effectivePosition} sidebarStyle={style} />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" position={position} /> */}
                {/* Profile menu moved to header */}
            </SidebarFooter>
        </Sidebar>
    );
}