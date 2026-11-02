import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Clock, Info, Mail, MoreVertical, SearchX, Send, Sparkles, UserPlus, UserRound, Users, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageTemplate } from '@/components/page-template';
import { EmptyState } from '@/components/EmptyState';
import { UserAvatar } from '@/components/ui/avatar';
import { Button, IconButton } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RoleBadge } from '@/components/ui/role-badge';
import { SearchInput } from '@/components/ui/search-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { StatusBadge } from '@/components/ui/status-badge';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { toast } from '@/components/custom-toast';

declare const route: any;

interface Member {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    is_deactivated: boolean;
    joined_at: string | null;
}

interface PendingInvitation {
    id: number;
    email: string;
    role: string;
    expires_at: string;
    is_expired?: boolean;
    invited_by: { name: string } | null;
    created_at: string;
}

interface Props {
    workspace: { id: number; name: string };
    members: Member[];
    pendingInvitations: PendingInvitation[];
    isOwner: boolean;
}

const ROLE_LABEL: Record<string, string> = {
    owner: 'Owner', manager: 'Manager', member: 'Member', client: 'Client',
};

const ASSIGNABLE_ROLES = ['manager', 'member', 'client'] as const;

const EMAIL_FORMAT = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** A white card with a section title, a short explanation and optional controls. */
function Section({ id, title, description, toolbar, children }: {
    id: string;
    title: ReactNode;
    description: ReactNode;
    toolbar?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section aria-labelledby={id} className="rounded-xl border border-ds-border bg-ds-surface shadow-ds-xs">
            <div className="flex flex-wrap items-start justify-between gap-4 px-4 pt-4 pb-3 sm:px-5">
                <div className="min-w-0">
                    <h2 id={id} className="text-ds-section-title font-semibold text-ds-fg">{title}</h2>
                    <p className="mt-1 text-sm text-ds-fg-secondary">{description}</p>
                </div>
                {toolbar}
            </div>
            <div className="px-4 pb-4 sm:px-5">{children}</div>
        </section>
    );
}

export default function TeamIndex({ workspace, members, pendingInvitations, isOwner }: Props) {
    const { t, i18n } = useTranslation();
    const { props } = usePage();
    const { auth, flash } = props as any;
    const permissions = auth?.permissions || [];
    const canInvite = permissions.includes('team_invite');
    // Manager can only invite Member; Owner can invite any of the three.
    const invitableRoles: string[] = isOwner ? [...ASSIGNABLE_ROLES] : ['member'];

    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteEmailError, setInviteEmailError] = useState<string | null>(null);
    const [inviteRole, setInviteRole] = useState(invitableRoles[0]);
    const [submitting, setSubmitting] = useState(false);
    const emailInputRef = useRef<HTMLInputElement>(null);

    const [query, setQuery] = useState('');
    const [roleFilter, setRoleFilter] = useState('all');

    // The row action in flight (e.g. "role:12"), so its button shows progress
    // and the row can't be submitted twice.
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [memberToDeactivate, setMemberToDeactivate] = useState<Member | null>(null);

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Team') },
    ];

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const roleLabel = (role: string) => t(ROLE_LABEL[role] ?? role);

    const dateFormat = useMemo(
        () => new Intl.DateTimeFormat(i18n.language || 'en', { month: 'short', day: 'numeric', year: 'numeric' }),
        [i18n.language],
    );
    const formatDate = (value: string | null) => {
        if (!value) return null;
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : dateFormat.format(date);
    };
    const daysLeft = (value: string) => Math.ceil((new Date(value).getTime() - Date.now()) / 86_400_000);

    const sendInvite = () => {
        setInviteEmailError(null);
        if (!inviteEmail.trim()) {
            setInviteEmailError(t('Enter an email address.'));
            emailInputRef.current?.focus();
            return;
        }
        if (!EMAIL_FORMAT.test(inviteEmail.trim())) {
            setInviteEmailError(t('Enter a valid email address (e.g. name@example.com).'));
            emailInputRef.current?.focus();
            return;
        }
        setSubmitting(true);
        router.post(route('workspace.invitations.store', workspace.id), {
            email: inviteEmail, role: inviteRole,
        }, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            // A blocked invite (plan limit, already a member) is also a successful
            // visit, just with an error flash - keep the email for a retry and
            // only clear it once it shows up as a pending invitation.
            onSuccess: (page) => {
                const invited = (page.props.pendingInvitations as PendingInvitation[] | undefined)
                    ?.some(inv => inv.email.toLowerCase() === inviteEmail.trim().toLowerCase());
                if (invited) setInviteEmail('');
            },
            onError: (formErrors) => {
                if (formErrors.email) {
                    setInviteEmailError(formErrors.email);
                    emailInputRef.current?.focus();
                }
                toast.error(Object.values(formErrors)[0] as string || t('Failed to send invitation.'));
            },
        });
    };

    const runRowAction = (key: string, visit: (options: { preserveScroll: boolean; onFinish: () => void }) => void, after?: () => void) => {
        setBusyKey(key);
        visit({
            preserveScroll: true,
            onFinish: () => {
                setBusyKey(null);
                after?.();
            },
        });
    };

    const changeRole = (member: Member, role: string) => {
        if (role === member.role) return;
        runRowAction(`role:${member.user_id}`, options =>
            router.patch(route('team.update-role', [workspace.id, member.user_id]), { role }, options));
    };

    const confirmDeactivate = () => {
        if (!memberToDeactivate) return;
        const member = memberToDeactivate;
        runRowAction(`deactivate:${member.user_id}`, options =>
            router.post(route('team.deactivate', [workspace.id, member.user_id]), {}, options),
            () => setMemberToDeactivate(null));
    };

    const reactivate = (member: Member) => {
        runRowAction(`reactivate:${member.user_id}`, options =>
            router.post(route('team.reactivate', [workspace.id, member.user_id]), {}, options));
    };

    const cancelInvitation = (invitation: PendingInvitation) => {
        runRowAction(`cancel:${invitation.id}`, options =>
            router.delete(route('invitations.destroy', invitation.id), options));
    };

    const resendInvitation = (invitation: PendingInvitation) => {
        runRowAction(`resend:${invitation.id}`, options =>
            router.post(route('invitations.resend', invitation.id), {}, options));
    };

    const normalizedQuery = query.trim().toLowerCase();
    const visibleMembers = members.filter(member =>
        (roleFilter === 'all' || member.role === roleFilter) &&
        (!normalizedQuery ||
            member.name.toLowerCase().includes(normalizedQuery) ||
            member.email.toLowerCase().includes(normalizedQuery) ||
            roleLabel(member.role).toLowerCase().includes(normalizedQuery)),
    );
    const isFiltering = normalizedQuery !== '' || roleFilter !== 'all';

    const isInactive = (member: Member) => member.is_deactivated || member.status !== 'active';
    const canManage = (member: Member) => isOwner && member.role !== 'owner';
    const canManageInvitation = (invitation: PendingInvitation) => canInvite && invitableRoles.includes(invitation.role);

    // ── Pieces shared by the desktop table and the mobile list ──

    const memberStatus = (member: Member) => isInactive(member)
        ? <StatusBadge tone="neutral">{t('Inactive')}</StatusBadge>
        : <StatusBadge tone="success">{t('Active')}</StatusBadge>;

    const memberActions = (member: Member) => {
        if (!canManage(member)) {
            return <span className="text-ds-fg-placeholder" aria-label={t('No actions')}>—</span>;
        }
        const roleBusy = busyKey === `role:${member.user_id}`;
        return (
            <div className="flex items-center justify-end gap-1">
                {isInactive(member) ? (
                    <Button variant="outline" onClick={() => reactivate(member)}
                        loading={busyKey === `reactivate:${member.user_id}`} disabled={busyKey !== null}>
                        {t('Reactivate')}
                    </Button>
                ) : (
                    <Button variant="outline" onClick={() => setMemberToDeactivate(member)} disabled={busyKey !== null}>
                        {t('Deactivate')}
                    </Button>
                )}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <IconButton aria-label={t('More actions for {{name}}', { name: member.name })}
                            loading={roleBusy} disabled={busyKey !== null}>
                            <MoreVertical />
                        </IconButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuLabel>{t('Change role')}</DropdownMenuLabel>
                        <DropdownMenuRadioGroup value={member.role} onValueChange={(role) => changeRole(member, role)}>
                            {ASSIGNABLE_ROLES.map(role => (
                                <DropdownMenuRadioItem key={role} value={role}>{roleLabel(role)}</DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        );
    };

    const invitationStatus = (invitation: PendingInvitation) => invitation.is_expired
        ? <StatusBadge tone="danger" icon={<XCircle aria-hidden="true" />}>{t('Expired')}</StatusBadge>
        : <StatusBadge tone="warning" icon={<Clock aria-hidden="true" />}>{t('Pending')}</StatusBadge>;

    const invitationExpiry = (invitation: PendingInvitation) => {
        const date = formatDate(invitation.expires_at);
        if (!date) return '—';
        if (invitation.is_expired) return date;
        const days = daysLeft(invitation.expires_at);
        const remaining = days <= 0 ? t('today') : days === 1 ? t('1 day left') : t('{{count}} days left', { count: days });
        return (
            <>
                {date} <span className="text-ds-fg-muted">({remaining})</span>
            </>
        );
    };

    const invitationActions = (invitation: PendingInvitation) => {
        // Managers can only cancel the Member invitations they're allowed to send.
        if (!canManageInvitation(invitation)) {
            return <span className="text-ds-fg-placeholder" aria-label={t('No actions')}>—</span>;
        }
        return (
            <div className="flex items-center justify-end gap-1">
                <Button variant="outline" onClick={() => cancelInvitation(invitation)}
                    loading={busyKey === `cancel:${invitation.id}`} disabled={busyKey !== null}
                    aria-label={t('Cancel invitation to {{email}}', { email: invitation.email })}>
                    {t('Cancel')}
                </Button>
                {!invitation.is_expired && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <IconButton aria-label={t('More actions for {{name}}', { name: invitation.email })}
                                loading={busyKey === `resend:${invitation.id}`} disabled={busyKey !== null}>
                                <MoreVertical />
                            </IconButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuItem onSelect={() => resendInvitation(invitation)}>
                                <Send />
                                {t('Resend invitation')}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        );
    };

    const totalMembers = (
        <div className="flex items-center gap-3 rounded-xl bg-brand-soft px-4 py-2.5">
            <Users className="size-5 text-brand-soft-fg" aria-hidden="true" />
            <div>
                <div className="text-lg font-semibold leading-6 text-ds-fg tabular-nums">{members.length}</div>
                <div className="text-xs text-ds-fg-secondary">{t('Total members')}</div>
            </div>
        </div>
    );

    return (
        <PageTemplate
            title={t('Team')}
            description={t('Manage your team members, roles and permissions.')}
            breadcrumbs={breadcrumbs}
            toolbar={totalMembers}
            noPadding
        >
            <div className="space-y-5">
                {canInvite && (
                    <section
                        aria-labelledby="invite-title"
                        className="relative overflow-hidden rounded-2xl border border-brand-soft-border bg-linear-120 from-brand-soft via-ds-surface to-brand-soft px-4 py-4 sm:px-5"
                    >
                        {/* Decoration only; kept clear of the form. */}
                        <div aria-hidden="true" className="pointer-events-none absolute top-5 right-8 hidden items-start gap-2 text-brand-300 lg:flex">
                            <Sparkles className="mt-4 size-4" />
                            <Send className="size-9 -rotate-12 text-brand-400" />
                            <Sparkles className="size-3" />
                        </div>

                        <div className="flex items-start gap-4">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white" aria-hidden="true">
                                <UserPlus className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <h2 id="invite-title" className="text-ds-section-title font-semibold text-ds-fg">{t('Invite someone to your team')}</h2>
                                <p className="mt-1 text-sm text-ds-fg-secondary">
                                    {t('Add a new member by entering their email and role.')}
                                    {!isOwner && <> {t('Managers can only invite new members as Member.')}</>}
                                </p>
                            </div>
                        </div>

                        <form
                            noValidate
                            onSubmit={(e) => { e.preventDefault(); sendInvite(); }}
                            className="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,14rem)_auto] sm:items-start"
                        >
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="invite-email" className="sr-only">{t('Email address')}</Label>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute top-1/2 left-3 size-[18px] -translate-y-1/2 text-ds-fg-muted rtl:right-3 rtl:left-auto" aria-hidden="true" />
                                    <Input
                                        ref={emailInputRef}
                                        id="invite-email"
                                        type="email"
                                        autoComplete="email"
                                        value={inviteEmail}
                                        onChange={e => { setInviteEmail(e.target.value); setInviteEmailError(null); }}
                                        placeholder="name@example.com"
                                        aria-invalid={inviteEmailError ? true : undefined}
                                        aria-describedby={inviteEmailError ? 'invite-email-error' : undefined}
                                        className="pl-10 rtl:pr-10 rtl:pl-3"
                                    />
                                </div>
                                {inviteEmailError && (
                                    <p id="invite-email-error" className="text-sm text-ds-danger-fg">{inviteEmailError}</p>
                                )}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="invite-role" className="sr-only">{t('Role')}</Label>
                                <Select value={inviteRole} onValueChange={setInviteRole} disabled={invitableRoles.length === 1}>
                                    <SelectTrigger id="invite-role">
                                        <UserRound className="size-[18px] shrink-0 text-ds-fg-muted" aria-hidden="true" />
                                        <SelectValue className="text-left rtl:text-right" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {invitableRoles.map(r => (
                                            <SelectItem key={r} value={r}>{roleLabel(r)}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="submit" className="w-full sm:w-auto" loading={submitting} loadingText={t('Sending...')}>
                                <Send />
                                {t('Send invite')}
                            </Button>
                        </form>
                    </section>
                )}

                <Section
                    id="members-title"
                    title={`${t('Team members')} (${members.length})`}
                    description={t('These are the people who have access to your workspace.')}
                    toolbar={members.length > 0 && (
                        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                            <SearchInput
                                value={query}
                                onChange={setQuery}
                                placeholder={t('Search members...')}
                                className="sm:w-64"
                            />
                            <Select value={roleFilter} onValueChange={setRoleFilter}>
                                <SelectTrigger className="sm:w-40" aria-label={t('Filter by role')}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('All roles')}</SelectItem>
                                    {Object.keys(ROLE_LABEL).map(role => (
                                        <SelectItem key={role} value={role}>{roleLabel(role)}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                >
                    {members.length === 0 ? (
                        <EmptyState
                            compact
                            icon={Users}
                            title={t('No team members yet')}
                            description={t('Invite people to collaborate in this workspace.')}
                            action={canInvite ? { label: t('Invite member'), onClick: () => emailInputRef.current?.focus() } : undefined}
                        />
                    ) : visibleMembers.length === 0 ? (
                        <EmptyState
                            compact
                            icon={SearchX}
                            title={t('No members found')}
                            description={t('Try another name, email, or role.')}
                            secondaryAction={isFiltering ? { label: t('Clear filters'), onClick: () => { setQuery(''); setRoleFilter('all'); } } : undefined}
                        />
                    ) : (
                        <>
                            {/* Desktop and tablet: a table */}
                            <div className="hidden overflow-hidden rounded-lg md:block">
                                <Table>
                                    <TableCaption className="sr-only">{t('Team members')}</TableCaption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead scope="col">{t('Member')}</TableHead>
                                            <TableHead scope="col">{t('Role')}</TableHead>
                                            <TableHead scope="col">{t('Joined')}</TableHead>
                                            <TableHead scope="col">{t('Status')}</TableHead>
                                            {isOwner && <TableHead scope="col" className="text-right">{t('Actions')}</TableHead>}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {visibleMembers.map(member => (
                                            <TableRow key={member.id}>
                                                <TableCell>
                                                    <div className="flex min-w-0 items-center gap-3">
                                                        <UserAvatar name={member.name} />
                                                        <div className="min-w-0">
                                                            <div className="truncate font-medium text-ds-fg">{member.name}</div>
                                                            <div className="truncate text-sm text-ds-fg-muted">{member.email}</div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell><RoleBadge role={member.role}>{roleLabel(member.role)}</RoleBadge></TableCell>
                                                <TableCell className="whitespace-nowrap text-ds-fg-secondary">{formatDate(member.joined_at) ?? '—'}</TableCell>
                                                <TableCell>{memberStatus(member)}</TableCell>
                                                {isOwner && <TableCell className="text-right">{memberActions(member)}</TableCell>}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Phones: one card per person */}
                            <ul className="divide-y divide-ds-border-subtle md:hidden" aria-label={t('Team members')}>
                                {visibleMembers.map(member => (
                                    <li key={member.id} className="py-4 first:pt-0 last:pb-0">
                                        <div className="flex items-center gap-3">
                                            <UserAvatar name={member.name} />
                                            <div className="min-w-0">
                                                <div className="truncate font-medium text-ds-fg">{member.name}</div>
                                                <div className="truncate text-sm text-ds-fg-muted">{member.email}</div>
                                            </div>
                                        </div>
                                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                            <RoleBadge role={member.role}>{roleLabel(member.role)}</RoleBadge>
                                            {memberStatus(member)}
                                        </div>
                                        {member.joined_at && (
                                            <p className="mt-2 text-sm text-ds-fg-secondary">
                                                {t('Joined {{date}}', { date: formatDate(member.joined_at) })}
                                            </p>
                                        )}
                                        {canManage(member) && <div className="mt-3">{memberActions(member)}</div>}
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </Section>

                {pendingInvitations.length > 0 && (
                    <Section
                        id="invitations-title"
                        title={`${t('Pending invitations')} (${pendingInvitations.length})`}
                        description={t('Invitations that are waiting for a response.')}
                    >
                        <div className="hidden overflow-hidden rounded-lg md:block">
                            <Table>
                                <TableCaption className="sr-only">{t('Pending invitations')}</TableCaption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead scope="col">{t('Email')}</TableHead>
                                        <TableHead scope="col">{t('Role')}</TableHead>
                                        <TableHead scope="col">{t('Invited')}</TableHead>
                                        <TableHead scope="col">{t('Expires')}</TableHead>
                                        <TableHead scope="col">{t('Status')}</TableHead>
                                        {canInvite && <TableHead scope="col" className="text-right">{t('Actions')}</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pendingInvitations.map(invitation => (
                                        <TableRow key={invitation.id}>
                                            <TableCell>
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <UserAvatar name={invitation.email} />
                                                    <span className="break-all font-medium text-ds-fg">{invitation.email}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell><RoleBadge role={invitation.role}>{roleLabel(invitation.role)}</RoleBadge></TableCell>
                                            <TableCell className="whitespace-nowrap text-ds-fg-secondary">{formatDate(invitation.created_at) ?? '—'}</TableCell>
                                            <TableCell className="whitespace-nowrap text-ds-fg-secondary">{invitationExpiry(invitation)}</TableCell>
                                            <TableCell>{invitationStatus(invitation)}</TableCell>
                                            {canInvite && <TableCell className="text-right">{invitationActions(invitation)}</TableCell>}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <ul className="divide-y divide-ds-border-subtle md:hidden" aria-label={t('Pending invitations')}>
                            {pendingInvitations.map(invitation => (
                                <li key={invitation.id} className="py-4 first:pt-0 last:pb-0">
                                    <div className="flex items-center gap-3">
                                        <UserAvatar name={invitation.email} />
                                        <span className="min-w-0 break-all font-medium text-ds-fg">{invitation.email}</span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                        <RoleBadge role={invitation.role}>{roleLabel(invitation.role)}</RoleBadge>
                                        {invitationStatus(invitation)}
                                    </div>
                                    <p className="mt-2 text-sm text-ds-fg-secondary">
                                        {t('Expires')}: {invitationExpiry(invitation)}
                                    </p>
                                    {canManageInvitation(invitation) && <div className="mt-3">{invitationActions(invitation)}</div>}
                                </li>
                            ))}
                        </ul>

                        <div className="mt-4 flex items-start gap-2.5 rounded-lg border border-ds-info/20 bg-ds-info-bg px-4 py-3 text-sm text-ds-info-fg">
                            <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <p>
                                {canInvite
                                    ? t('Invitations expire after 7 days. You can cancel and resend if needed.')
                                    : t('Invitations expire after 7 days.')}
                            </p>
                        </div>
                    </Section>
                )}
            </div>

            <ConfirmDialog
                open={memberToDeactivate !== null}
                onOpenChange={(open) => { if (!open) setMemberToDeactivate(null); }}
                title={t('Deactivate {{name}}?', { name: memberToDeactivate?.name ?? '' })}
                description={t('{{name}} will lose access to this workspace and can no longer sign in. Their seat is freed and their data is kept, so you can reactivate them later.', { name: memberToDeactivate?.name ?? '' })}
                confirmLabel={t('Deactivate')}
                loadingLabel={t('Deactivating...')}
                loading={memberToDeactivate !== null && busyKey === `deactivate:${memberToDeactivate.user_id}`}
                onConfirm={confirmDeactivate}
            />
        </PageTemplate>
    );
}
