import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Mail, Building } from 'lucide-react';

interface Invitation {
    id: number;
    token: string;
    email: string;
    role: string;
    workspace: {
        id: number;
        name: string;
        description?: string;
    };
    invitedBy?: {
        id: number;
        name: string;
        email: string;
    };
}

interface Props {
    invitation: Invitation;
    existingUser: boolean;
}

export default function Accept({ invitation, existingUser }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        password: '',
        password_confirmation: ''
    });

    const handleAccept = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('invitations.accept', invitation.token));
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <Head title={t('Accept Invitation')} />
            
            <div className="max-w-md w-full space-y-8">
                <div className="text-center">
                    <Building className="mx-auto h-12 w-12 text-blue-600" />
                    <h2 className="mt-6 text-3xl font-extrabold text-gray-900">
                        {t("You're Invited!")}
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        {t('Join the workspace and start collaborating')}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <Mail className="w-5 h-5 mr-2" />
                            {t('Workspace Invitation')}
                        </CardTitle>
                        <CardDescription>
                            {t("You've been invited to join")} <strong>{invitation.workspace.name}</strong> {t('as a')} {invitation.role}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="bg-gray-50 p-4 rounded-lg">
                            <p className="text-sm text-gray-600">
                                <strong>{t('Workspace')}:</strong> {invitation.workspace.name}
                            </p>
                            {invitation.workspace.description && (
                                <p className="text-sm text-gray-600 mt-1">
                                    {invitation.workspace.description}
                                </p>
                            )}
                            {invitation.invitedBy && (
                                <p className="text-sm text-gray-600 mt-2">
                                    <strong>{t('Invited by')}:</strong> {invitation.invitedBy.name} ({invitation.invitedBy.email})
                                </p>
                            )}
                            <p className="text-sm text-gray-600 mt-1">
                                <strong>{t('Role')}:</strong> {invitation.role}
                            </p>
                        </div>

                        <form onSubmit={handleAccept} className="space-y-4">
                            {!existingUser && (
                                <>
                                    <div>
                                        <Label htmlFor="password">{t('Create Password')}</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder={t('Enter your password')}
                                            required
                                        />
                                        {errors.password && (
                                            <p className="text-red-500 text-sm mt-1">{errors.password}</p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="password_confirmation">{t('Confirm Password')}</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            placeholder={t('Confirm your password')}
                                            required
                                        />
                                        {errors.password_confirmation && (
                                            <p className="text-red-500 text-sm mt-1">{errors.password_confirmation}</p>
                                        )}
                                    </div>
                                </>
                            )}

                            {errors.error && (
                                <div className="bg-red-50 border border-red-200 rounded-md p-3">
                                    <p className="text-red-600 text-sm">{errors.error}</p>
                                </div>
                            )}

                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing ? t('Accepting...') : t('Accept Invitation')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}