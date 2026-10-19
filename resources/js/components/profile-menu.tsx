import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link, router, usePage } from '@inertiajs/react';
import { Building2, CreditCard, History, LogOut, Settings, User } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { notifySignedOut } from '@/lib/ai-mode';
import { hasPermission } from '@/utils/authorization';

export function ProfileMenu() {
  const { t } = useTranslation();
  const { auth, isSaasMode } = usePage().props as any;
  const user = auth?.user;
  const permissions = auth?.permissions || [];

  // Account pages. The platform admin keeps Settings in the sidebar; for a
  // workspace, Billing and Settings belong to the owner (a manager's Settings
  // page had nothing for them to configure).
  const accountLinks: { label: string; href: string; icon: typeof User }[] = [];
  const isWorkspaceOwner = user?.workspace_role === 'owner';
  if (user?.type !== 'superadmin') {
    if (isSaasMode && ['plan_view_any', 'plan_view_my_requests', 'plan_view_my_orders'].some(p => hasPermission(permissions, p))) {
      accountLinks.push({ label: t('Billing'), href: route('plans.index'), icon: CreditCard });
    }
  }
  if (hasPermission(permissions, 'user_view_logs')) {
    accountLinks.push({ label: t('Login History'), href: route('users.all-logs'), icon: History });
  }
  if (user?.type !== 'superadmin' && isWorkspaceOwner && hasPermission(permissions, 'workspace_view_any')) {
    accountLinks.push({ label: t('Workspaces'), href: route('workspaces.index'), icon: Building2 });
  }
  if (user?.type !== 'superadmin' && isWorkspaceOwner && hasPermission(permissions, 'settings_view')) {
    accountLinks.push({ label: t('Settings'), href: route('settings'), icon: Settings });
  }

  const handleLogout = () => {
    notifySignedOut();
    router.post(route('logout'));
  };

  const initials = user?.name
    ? user.name
        .split(' ')
        .map((n: string) => n[0])
        .join('')
        .toUpperCase()
    : 'U';

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-9 w-9 shrink-0 rounded-full p-0" aria-label={t('Account menu')} title={user?.name}>
          <Avatar className="h-8 w-8 shrink-0">
            <AvatarImage src={user?.avatar} alt={user?.name} />
            <AvatarFallback>{initials}</AvatarFallback>
          </Avatar>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className="w-56" align="end" forceMount>
        <DropdownMenuLabel className="font-normal">
          <div className="flex flex-col space-y-1">
            <p className="text-sm font-medium leading-none">{user?.name}</p>
            <p className="text-xs leading-none text-muted-foreground">
              {user?.email}
            </p>
            {user?.current_workspace?.name && (
              <p className="flex items-center gap-1.5 pt-1 text-xs leading-none text-muted-foreground">
                {user?.workspace_role && (
                  <span className="rounded bg-primary/10 px-1.5 py-0.5 font-medium capitalize text-primary">{t(user.workspace_role)}</span>
                )}
                <span className="truncate text-foreground">{user.current_workspace.name}</span>
              </p>
            )}
          </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuGroup>
          <DropdownMenuItem asChild>
            <Link href={route('profile')}>
              <User className="mr-2 h-4 w-4" />
              <span>{t("Profile")}</span>
            </Link>
          </DropdownMenuItem>
          {accountLinks.map(link => (
            <DropdownMenuItem key={link.href} asChild>
              <Link href={link.href}>
                <link.icon className="mr-2 h-4 w-4" />
                <span>{link.label}</span>
              </Link>
            </DropdownMenuItem>
          ))}
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={handleLogout}>
          <LogOut className="mr-2 h-4 w-4" />
          <span>{t("Log out")}</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}