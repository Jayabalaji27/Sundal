import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

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

export default function TeamIndex({ workspace, members, pendingInvitations, isOwner }: Props) {
    const { t } = useTranslation();
    const { props } = usePage();
    const { auth, flash } = props as any;
    const permissions = auth?.permissions || [];
    const canInvite = permissions.includes('team_invite');
    // Manager can only invite Member; Owner can invite any of the three.
    const invitableRoles = isOwner ? ['manager', 'member', 'client'] : ['member'];

    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteEmailError, setInviteEmailError] = useState<string | null>(null);
    const [inviteRole, setInviteRole] = useState(invitableRoles[0]);
    const [submitting, setSubmitting] = useState(false);

    const EMAIL_FORMAT = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Team') },
    ];

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const sendInvite = () => {
        if (!inviteEmail) return;
        setInviteEmailError(null);
        if (!EMAIL_FORMAT.test(inviteEmail)) {
            setInviteEmailError(t('Enter a valid email address (e.g. name@example.com).'));
            return;
        }
        setSubmitting(true);
        router.post(route('workspace.invitations.store', workspace.id), {
            email: inviteEmail, role: inviteRole,
        }, {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
            onSuccess: () => setInviteEmail(''),
            onError: (formErrors) => {
                if (formErrors.email) setInviteEmailError(formErrors.email);
                toast.error(Object.values(formErrors)[0] as string || t('Failed to send invitation.'));
            },
        });
    };

    const changeRole = (member: Member, role: string) => {
        router.patch(route('team.update-role', [workspace.id, member.user_id]), { role }, { preserveScroll: true });
    };

    const deactivate = (member: Member) => {
        if (!confirm(t('Deactivate {{name}}? Their seat will be freed and their data preserved. They will be blocked from logging in until reactivated.', { name: member.name }))) return;
        router.post(route('team.deactivate', [workspace.id, member.user_id]), {}, { preserveScroll: true });
    };

    const reactivate = (member: Member) => {
        router.post(route('team.reactivate', [workspace.id, member.user_id]), {}, { preserveScroll: true });
    };

    const cancelInvitation = (invitation: PendingInvitation) => {
        router.delete(route('invitations.destroy', invitation.id), { preserveScroll: true });
    };

    return (
        <PageTemplate title={t('Team')} breadcrumbs={breadcrumbs}>
            <div className="space-y-8">
                {canInvite && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Invite someone')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
                                <div className="flex-1 space-y-1.5">
                                    <Label htmlFor="invite-email">{t('Email')}</Label>
                                    <Input id="invite-email" type="email" value={inviteEmail}
                                        onChange={e => { setInviteEmail(e.target.value); setInviteEmailError(null); }}
                                        placeholder="name@example.com"
                                        className={inviteEmailError ? 'border-red-500' : ''} />
                                    {inviteEmailError && <p className="text-xs text-red-600">{inviteEmailError}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>{t('Role')}</Label>
                                    <Select value={inviteRole} onValueChange={setInviteRole}>
                                        <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            {invitableRoles.map(r => (
                                                <SelectItem key={r} value={r}>{t(ROLE_LABEL[r])}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button onClick={sendInvite} disabled={submitting || !inviteEmail}>
                                    {t('Send invite')}
                                </Button>
                            </div>
                            {!isOwner && (
                                <p className="text-xs text-muted-foreground mt-2">
                                    {t('Managers can only invite new members as Member.')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Members')} ({members.length})</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {members.map(member => (
                            <div key={member.id} className="flex flex-wrap items-center justify-between gap-3 py-2 border-b last:border-0">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium truncate">{member.name}</span>
                                        {member.is_deactivated && (
                                            <Badge variant="secondary" className="text-muted-foreground">{t('Inactive')}</Badge>
                                        )}
                                    </div>
                                    <span className="text-sm text-muted-foreground">{member.email}</span>
                                </div>
                                <div className="flex items-center gap-2 shrink-0">
                                    {isOwner && member.role !== 'owner' ? (
                                        <Select value={member.role} onValueChange={(role) => changeRole(member, role)}>
                                            <SelectTrigger className="w-32 h-8"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                {(['manager', 'member', 'client'] as const).map(r => (
                                                    <SelectItem key={r} value={r}>{t(ROLE_LABEL[r])}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Badge variant="outline">{t(ROLE_LABEL[member.role] ?? member.role)}</Badge>
                                    )}
                                    {isOwner && member.role !== 'owner' && (
                                        member.is_deactivated ? (
                                            <Button size="sm" variant="outline" onClick={() => reactivate(member)}>
                                                {t('Reactivate')}
                                            </Button>
                                        ) : (
                                            <Button size="sm" variant="outline" onClick={() => deactivate(member)}>
                                                {t('Deactivate')}
                                            </Button>
                                        )
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {pendingInvitations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Pending invitations')} ({pendingInvitations.length})</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {pendingInvitations.map(inv => (
                                <div key={inv.id} className="flex flex-wrap items-center justify-between gap-3 py-2 border-b last:border-0">
                                    <div className="min-w-0">
                                        <span className="font-medium break-all">{inv.email}</span>{' '}
                                        <Badge variant="outline">{t(ROLE_LABEL[inv.role] ?? inv.role)}</Badge>
                                        {inv.is_expired && (
                                            <Badge variant="secondary" className="ml-1 text-muted-foreground">{t('Expired')}</Badge>
                                        )}
                                    </div>
                                    {/* Managers can only cancel the Member invitations they're allowed to send */}
                                    {canInvite && invitableRoles.includes(inv.role) && (
                                        <Button size="sm" variant="ghost" onClick={() => cancelInvitation(inv)}>
                                            {t('Cancel')}
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </PageTemplate>
    );
}
