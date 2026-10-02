import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem, useSidebar } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger } from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';

// Store expanded menu state in localStorage
const STORAGE_KEY = 'nav_expanded_items';

export function NavMain({ items = [], position, sidebarStyle = 'plain' }: { items: NavItem[]; position: 'left' | 'right'; sidebarStyle?: string }) {
    const { t } = useTranslation();
    const page = usePage();
    const { state } = useSidebar();
    const [expandedItems, setExpandedItems] = useState<Record<string, boolean>>({});

    // Auto-expand the group containing the active route when the URL changes.
    // This used to rebuild expandedItems from an empty object every navigation,
    // which force-collapsed every other group the user had open (and briefly
    // dropped/rebuilt the whole menu tree) on every single sidebar click, reading
    // as the entire sidebar "reloading" rather than a normal SPA navigation. It
    // now merges the newly-active keys into the existing state instead of
    // replacing it, so groups the user opened manually stay open across
    // navigation within the same module.
    useEffect(() => {
        const activeExpandedItems: Record<string, boolean> = {};

        // Process menus that should be expanded
        const processMenuItems = (menuItems: NavItem[], parentKey?: string) => {
            menuItems.forEach(item => {
                // If this is the active item or contains the active item
                const isItemActive = isActive(item.href);
                const hasActiveChild = item.children && isChildActive(item.children);

                // If this item or its children are active, expand it
                if (parentKey && (isItemActive || hasActiveChild)) {
                    activeExpandedItems[parentKey] = true;
                }

                // If this item has children and is active, has active children, or defaultOpen is true, expand it
                if (item.children && (isItemActive || hasActiveChild || item.defaultOpen === true)) {
                    activeExpandedItems[item.title] = true;

                    // Recursively check children
                    processMenuItems(item.children, item.title);
                }

                // Check nested children with their own keys
                if (item.children) {
                    checkNestedChildren(item.children, 1, activeExpandedItems);
                }
            });
        };

        processMenuItems(items);

        // Merge into (rather than replace) the existing expanded state, and save
        // the merged result to localStorage.
        setExpandedItems(prev => {
            const merged = { ...prev, ...activeExpandedItems };
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(merged));
            } catch (e) {
                console.error('Error saving navigation state:', e);
            }
            return merged;
        });
    }, [page.url, items]); // Re-run when URL changes or items change

    // Helper function to check nested children for active items
    const checkNestedChildren = (
        children: NavItem[],
        level: number,
        newExpandedItems: Record<string, boolean>
    ) => {
        children.forEach(child => {
            const childKey = `${level}-${child.title}`;
            const isChildItemActive = isActive(child.href);
            const hasActiveChild = child.children && isChildActive(child.children);

            if (child.children && (isChildItemActive || hasActiveChild)) {
                newExpandedItems[childKey] = true;
                checkNestedChildren(child.children, level + 1, newExpandedItems);
            }
        });
    };

    const toggleExpand = (title: string) => {
        const newExpandedItems = {
            ...expandedItems,
            [title]: !expandedItems[title]
        };

        setExpandedItems(newExpandedItems);

        // Save to localStorage
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(newExpandedItems));
        } catch (e) {
            console.error('Error saving navigation state:', e);
        }
    };

    const isActive = (href?: string) => {
        if (!href) return false;

        // Extract pathname from href if it's a full URL
        const hrefPath = href.startsWith('http') ? new URL(href).pathname : href;

        // Get current path without query parameters or hash
        const currentPath = page.url.split('?')[0].split('#')[0];

        // Normalize paths by removing trailing slashes
        const normalizedHref = hrefPath.replace(/\/$/, '');
        const normalizedCurrent = currentPath.replace(/\/$/, '');

        // Check exact match first
        if (normalizedCurrent === normalizedHref) {
            return true;
        }

        // Check if current path is a sub-path of href
        // This handles cases like /coupons/123 when href is /coupons
        if (normalizedCurrent.startsWith(normalizedHref + '/')) {
            return true;
        }

        return false;
    };

    const isChildActive = (children?: NavItem[]): boolean => {
        if (!children) return false;
        return children.some(child => isActive(child.href) || isChildActive(child.children));
    };

    const isColored = sidebarStyle === 'colored' || sidebarStyle === 'gradient';
    const activeClass = isColored ? 'data-[active=true]:bg-white/20 data-[active=true]:text-white' : '';

    const renderSubMenu = (children: NavItem[], level: number = 1): React.ReactNode => {
        return (
            <SidebarMenuSub>
                {children.map(child => (
                    <div key={child.title}>
                        {child.children ? (
                            // Nested submenu item with children
                            <>
                                <SidebarMenuSubItem>
                                    <SidebarMenuSubButton
                                        isActive={isChildActive(child.children)}
                                        onClick={() => toggleExpand(`${level}-${child.title}`)}
                                        className={activeClass}>
                                        <div className={`flex items-center gap-2 ${position === 'right' ? 'justify-end text-right' : 'justify-start text-left'}`}>
                                            <span>{child.title}</span>
                                            {state !== "collapsed" && (
                                                expandedItems[`${level}-${child.title}`] ?
                                                    <ChevronDown className="h-3 w-3 ml-auto" /> :
                                                    <ChevronRight className="h-3 w-3 ml-auto" />
                                            )}
                                        </div>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>

                                {/* Render nested children */}
                                {expandedItems[`${level}-${child.title}`] && renderSubMenu(child.children, level + 1)}
                            </>
                        ) : (
                            // Regular submenu item
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton asChild isActive={isActive(child.href)} className={activeClass}>
                                    <Link
                                        href={child.href || '#'}
                                        prefetch
                                        target={child.target}
                                        className={`flex items-center gap-2 ${position === 'right' ? 'justify-end text-right' : 'justify-start text-left'}`}
                                    >
                                        <span>{child.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        )}
                    </div>
                ))}
            </SidebarMenuSub>
        );
    };

    return (
        <>
        {(() => {
            const groups: { label: string; items: NavItem[] }[] = [];
            let currentGroup: { label: string; items: NavItem[] } | null = null;

            items.forEach(item => {
                if (item.group && item.group !== currentGroup?.label) {
                    if (currentGroup) groups.push(currentGroup);
                    currentGroup = { label: item.group, items: [item] };
                } else {
                    if (!currentGroup) currentGroup = { label: '', items: [] };
                    currentGroup.items.push(item);
                }
            });
            if (currentGroup) groups.push(currentGroup);

            return groups.map((group, groupIndex) => (
                <SidebarGroup key={groupIndex} className="px-1.5 py-0">
                    {group.label && (
            <SidebarGroupLabel className={`flex w-full text-xs ${position === 'right' ? 'justify-end' : 'justify-start'}`}>
                           <span className="text-[13px] font-bold capitalize tracking-wide text-gray-500 dark:text-gray-400 leading-none">
                                    {group.label}
                                </span>
                        </SidebarGroupLabel>
                    )}
                    <SidebarMenu>
                        {group.items.map((item) => (
                    <div key={item.title}>
                        {item.children ? (
                            // Parent item with children
                            <>
                                <SidebarMenuItem>
                                    {state === "collapsed" ? (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <SidebarMenuButton
                                                    isActive={isChildActive(item.children)}
                                                    tooltip={{ children: item.title }}
                                                    className={activeClass}
                                                >
                                                    {item.icon && <item.icon className="h-4 w-4" />}
                                                </SidebarMenuButton>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent side="right" align="start" className="w-48">
                                                {item.children.map(child => (
                                                    <DropdownMenuItem key={child.title} asChild>
                                                        <Link href={child.href || '#'} prefetch>
                                                            {child.title}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    ) : (
                                        <SidebarMenuButton
                                            isActive={isChildActive(item.children)}
                                            tooltip={{ children: item.title }}
                                            onClick={() => toggleExpand(item.title)}
                                            className={activeClass}
                                        >
                                            <div className={`flex items-center gap-2 w-full ${position === 'right' ? 'justify-end text-right' : 'justify-start text-left'}`}>
                                                {position === 'right' ? (
                                                    <>
                                                        {expandedItems[item.title] ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                                                        {item.icon && <item.icon className="h-4 w-4" />}
                                                        <span>{item.title}</span>
                                                    </>
                                                ) : (
                                                    <>
                                                        {item.icon && <item.icon className="h-4 w-4" />}
                                                        <div className="flex items-center gap-1">
                                                            <span>{item.title}</span>
                                                            {item.badge && (
                                                                <span className="px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-primary text-white">
                                                                    {item.badge.label}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {expandedItems[item.title] ? <ChevronDown className="h-3 w-3 ml-auto" /> : <ChevronRight className="h-3 w-3 ml-auto" />}
                                                    </>
                                                )}
                                            </div>
                                        </SidebarMenuButton>
                                    )}
                                </SidebarMenuItem>

                                {/* Child items */}
                                {state !== "collapsed" && expandedItems[item.title] && renderSubMenu(item.children)}
                            </>
                        ) : (
                            // Regular item without children
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild isActive={isActive(item.href)} tooltip={{ children: item.title }} className={activeClass}>
                                    {item.target === '_blank' || item.forceFullReload ? (
                                        <a
                                            href={item.href || '#'}
                                            {...(item.target === '_blank' ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                                            className={`flex items-center gap-2 ${position === 'right' ? 'justify-end text-right' : 'justify-start text-left'}`}
                                        >
                                            {position === 'right' ? (
                                                <>
                                                    {item.icon && <item.icon className="h-4 w-4" />}
                                                    {state !== "collapsed" && <span>{item.title}</span>}
                                                </>
                                            ) : (
                                                <>
                                                    {item.icon && <item.icon className="h-4 w-4" />}
                                                    {state !== "collapsed" && <span>{item.title}</span>}
                                                </>
                                            )}
                                        </a>
                                    ) : (
                                        <Link
                                            href={item.href || '#'}
                                            prefetch
                                            className={`flex items-center gap-2 ${position === 'right' ? 'justify-end text-right' : 'justify-start text-left'}`}
                                        >
                                            {position === 'right' ? (
                                                <>
                                                    {item.icon && <item.icon className="h-4 w-4" />}
                                                    {state !== "collapsed" && <span>{item.title}</span>}
                                                </>
                                            ) : (
                                                <>
                                                    {item.icon && <item.icon className="h-4 w-4" />}
                                                    {state !== "collapsed" && <span>{item.title}</span>}
                                                </>
                                            )}
                                        </Link>
                                    )}
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        )}
                    </div>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ));
        })()}
        </>
    );
}
