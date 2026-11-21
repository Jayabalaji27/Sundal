import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { AlertTriangle, Clock, Copy, Eye, EyeOff, Info, KeyRound, Mail, MoreVertical, SearchX, Send, Sparkles, UserPlus, UserRound, Users, Wand2, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageTemplate } from '@/components/page-template';
import { EmptyState } from '@/components/EmptyState';
import { UserAvatar } from '@/components/ui/avatar';
import { Button, IconButton } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
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
    project_ids: number[];
}

interface ProjectOption {
    id: number;
    title: string;
}

/** How many project tags a row shows before collapsing the rest into "+N more". */
const MAX_PROJECT_TAGS = 2;

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
    projects: ProjectOption[];
    pendingInvitations: PendingInvitation[];
    isOwner: boolean;
    /** Why invitation emails won't be sent right now (WorkspaceInvitationController::invitationEmailIssue). */
    invitationEmailIssue?: 'not_configured' | 'turned_off' | null;
}

const ROLE_LABEL: Record<string, string> = {
    owner: 'Owner', manager: 'Manager', member: 'Member', client: 'Client',
};

const ASSIGNABLE_ROLES = ['manager', 'member', 'client'] as const;

const EMAIL_FORMAT = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** A random 14-character temporary password (no look-alike characters such as l/1/O/0). */
function generatePassword(): string {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%*?';
    const values = crypto.getRandomValues(new Uint32Array(14));
    return Array.from(values, v => chars[v % chars.length]).join('');
}

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

export default function TeamIndex({ workspace, members, projects = [], pendingInvitations, isOwner, invitationEmailIssue = null }: Props) {
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
    const [projectFilter, setProjectFilter] = useState('all');
    const projectTitles = useMemo(() => new Map(projects.map(p => [p.id, p.title])), [projects]);

    // The row action in flight (e.g. "role:12"), so its button shows progress
    // and the row can't be submitted twice.
    const [busyKey, setBusyKey] = useState<string | null>(null);
    const [memberToDeactivate, setMemberToDeactivate] = useState<Member | null>(null);

    // "Set password" dialog: the owner gives a teammate a temporary password.
    const [passwordMember, setPasswordMember] = useState<Member | null>(null);
    const [tempPassword, setTempPassword] = useState('');
    const [tempPasswordConfirm, setTempPasswordConfirm] = useState('');
    const [showTempPassword, setShowTempPassword] = useState(false);
    const [passwordError, setPasswordError] = useState<string | null>(null);

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

    const openSetPassword = (member: Member) => {
        setPasswordMember(member);
        setTempPassword('');
        setTempPasswordConfirm('');
        setShowTempPassword(false);
        setPasswordError(null);
    };

    const fillGeneratedPassword = () => {
        const password = generatePassword();
        setTempPassword(password);
        setTempPasswordConfirm(password);
        setShowTempPassword(true);
        setPasswordError(null);
    };

    const copyTempPassword = async () => {
        try {
            await navigator.clipboard.writeText(tempPassword);
            toast.success(t('Password copied.'));
        } catch {
            toast.error(t('Could not copy. Select the password and copy it yourself.'));
        }
    };

    const submitSetPassword = () => {
        if (!passwordMember) return;
        if (tempPassword.length < 8) {
            setPasswordError(t('Use at least 8 characters.'));
            return;
        }
        if (tempPassword !== tempPasswordConfirm) {
            setPasswordError(t('The two passwords don\'t match.'));
            return;
        }
        const member = passwordMember;
        setBusyKey(`password:${member.user_id}`);
        router.put(route('team.set-password', [workspace.id, member.user_id]), {
            password: tempPassword, password_confirmation: tempPasswordConfirm,
        }, {
            preserveScroll: true,
            onSuccess: (page) => {
                // A refusal (e.g. they belong to another company) comes back as an error flash.
                if (!(page.props as any).flash?.error) setPasswordMember(null);
            },
            onError: (formErrors) => setPasswordError(Object.values(formErrors)[0] as string),
            onFinish: () => setBusyKey(null),
        });
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
        (projectFilter === 'all' || (member.project_ids ?? []).includes(Number(projectFilter))) &&
        (!normalizedQuery ||
            member.name.toLowerCase().includes(normalizedQuery) ||
            member.email.toLowerCase().includes(normalizedQuery) ||
            roleLabel(member.role).toLowerCase().includes(normalizedQuery)),
    );
    const isFiltering = normalizedQuery !== '' || roleFilter !== 'all' || projectFilter !== 'all';
    const clearFilters = () => { setQuery(''); setRoleFilter('all'); setProjectFilter('all'); };

    /** A member's projects as small tags; the rest collapse into "+N more" (full list in its tooltip). */
    const memberProjects = (member: Member) => {
        // The project being filtered on goes first, so it's never hidden under "+N more".
        const selectedId = projectFilter === 'all' ? null : Number(projectFilter);
        const titles = [...(member.project_ids ?? [])]
            .sort((a, b) => Number(b === selectedId) - Number(a === selectedId))
            .map(id => projectTitles.get(id))
            .filter((title): title is string => Boolean(title));
        if (titles.length === 0) {
            return <span className="text-sm text-ds-fg-placeholder">{t('No projects')}</span>;
        }
        const shown = titles.slice(0, MAX_PROJECT_TAGS);
        const hidden = titles.slice(MAX_PROJECT_TAGS);
        const tagClass = 'inline-block max-w-40 truncate rounded-md border border-ds-border bg-ds-surface-subtle px-2 py-0.5 text-xs text-ds-fg-secondary';
        return (
            <div className="flex flex-wrap items-center gap-1">
                {shown.map(title => <span key={title} className={tagClass} title={title}>{title}</span>)}
                {hidden.length > 0 && (
                    <span className={tagClass} title={hidden.join(', ')}>
                        {t('+{{count}} more', { count: hidden.length })}
                    </span>
                )}
            </div>
        );
    };

    const isInactive = (member: Member) => member.is_deactivated || member.status !== 'active';
    const canManage = (member: Member) => isOwner && member.role !== 'owner';
    const canSetPassword = isOwner && permissions.includes('team_reset_password');
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
                        {canSetPassword && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onSelect={() => openSetPassword(member)}>
                                    <KeyRound aria-hidden="true" />
                                    {t('Set password...')}
                                </DropdownMenuItem>
                            </>
                        )}
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

                        {invitationEmailIssue && (
                            <div role="status" className="mt-4 flex items-start gap-2.5 rounded-lg border border-ds-warning/30 bg-ds-warning-bg px-4 py-3 text-sm text-ds-warning-fg">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                <p>
                                    {invitationEmailIssue === 'turned_off'
                                        ? t('Invitation emails are turned off, so invitees won\'t get an email.')
                                        : t('Your company\'s mail server isn\'t set up yet, so invitees won\'t get an email.')}{' '}
                                    {invitationEmailIssue === 'not_configured' && (
                                        permissions.includes('settings_email')
                                            ? <><a href={route('settings') + '#email-settings'} className="font-medium underline underline-offset-2">{t('Set up your mail server in Email Settings')}</a>{'. '}</>
                                            : <>{t('Ask the workspace owner to set up the mail server in Email Settings.')}{' '}</>
                                    )}
                                    {invitationEmailIssue === 'turned_off' && (
                                        permissions.includes('settings_email_notification')
                                            ? <><a href={route('settings') + '#email-notification-settings'} className="font-medium underline underline-offset-2">{t('Turn on "Workspace Invitation" in Email Notification Settings')}</a>{'. '}</>
                                            : <>{t('Ask the workspace owner to turn on "Workspace Invitation" in Email Notification Settings.')}{' '}</>
                                    )}
                                    {t('You can still send the invite and share its link yourself.')}
                                </p>
                            </div>
                        )}

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
                            {projects.length > 0 && (
                                <Select value={projectFilter} onValueChange={setProjectFilter}>
                                    <SelectTrigger className="sm:w-48" aria-label={t('Filter by project')}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All projects')}</SelectItem>
                                        {projects.map(project => (
                                            <SelectItem key={project.id} value={String(project.id)}>{project.title}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
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
                            description={t('Try another name, email, role, or project.')}
                            secondaryAction={isFiltering ? { label: t('Clear filters'), onClick: clearFilters } : undefined}
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
                                            <TableHead scope="col">{t('Projects')}</TableHead>
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
                                                <TableCell>{memberProjects(member)}</TableCell>
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
                                        <div className="mt-2">{memberProjects(member)}</div>
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

            <Dialog open={passwordMember !== null} onOpenChange={(open) => { if (!open) setPasswordMember(null); }}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={(e) => { e.preventDefault(); submitSetPassword(); }}>
                        <DialogHeader>
                            <DialogTitle>{t('Set a temporary password for {{name}}', { name: passwordMember?.name ?? '' })}</DialogTitle>
                            <DialogDescription>
                                {t('Use this when the password reset email doesn\'t arrive. {{name}} will be signed out everywhere and asked to choose their own password the next time they sign in.', { name: passwordMember?.name ?? '' })}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <div className="mb-1.5 flex items-center justify-between gap-2">
                                    <Label htmlFor="temp-password">{t('Temporary password')}</Label>
                                    <Button type="button" variant="ghost" size="sm" onClick={fillGeneratedPassword}>
                                        <Wand2 aria-hidden="true" /> {t('Generate')}
                                    </Button>
                                </div>
                                <div className="flex gap-1">
                                    <Input
                                        id="temp-password"
                                        type={showTempPassword ? 'text' : 'password'}
                                        autoComplete="new-password"
                                        value={tempPassword}
                                        onChange={(e) => { setTempPassword(e.target.value); setPasswordError(null); }}
                                        aria-invalid={passwordError !== null || undefined}
                                        aria-describedby="temp-password-hint"
                                        className="font-mono"
                                        autoFocus
                                    />
                                    <IconButton type="button" aria-label={showTempPassword ? t('Hide password') : t('Show password')}
                                        onClick={() => setShowTempPassword(v => !v)}>
                                        {showTempPassword ? <EyeOff /> : <Eye />}
                                    </IconButton>
                                    <IconButton type="button" aria-label={t('Copy password')} onClick={copyTempPassword} disabled={!tempPassword}>
                                        <Copy />
                                    </IconButton>
                                </div>
                                <p id="temp-password-hint" className="mt-1 text-xs text-ds-fg-muted">{t('At least 8 characters.')}</p>
                            </div>

                            <div>
                                <Label htmlFor="temp-password-confirm" className="mb-1.5 block">{t('Confirm temporary password')}</Label>
                                <Input
                                    id="temp-password-confirm"
                                    type={showTempPassword ? 'text' : 'password'}
                                    autoComplete="new-password"
                                    value={tempPasswordConfirm}
                                    onChange={(e) => { setTempPasswordConfirm(e.target.value); setPasswordError(null); }}
                                    className="font-mono"
                                />
                            </div>

                            {passwordError && <p role="alert" className="text-sm text-ds-danger-fg">{passwordError}</p>}

                            <div className="flex items-start gap-2.5 rounded-lg border border-ds-info/20 bg-ds-info-bg px-4 py-3 text-sm text-ds-info-fg">
                                <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                <p>{t('Share it with {{name}} privately, for example in person or by phone, not in a group chat.', { name: passwordMember?.name ?? '' })}</p>
                            </div>
                        </div>

                        <DialogFooter className="mt-6">
                            <Button type="button" variant="outline" onClick={() => setPasswordMember(null)}>{t('Cancel')}</Button>
                            <Button type="submit" loading={passwordMember !== null && busyKey === `password:${passwordMember.user_id}`}
                                loadingText={t('Saving...')}>
                                {t('Set password')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PageTemplate>
    );
}
