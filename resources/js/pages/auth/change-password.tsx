import { router, useForm } from '@inertiajs/react';
import { KeyRound, Lock, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';
import { useTranslation } from 'react-i18next';

import AuthButton from '@/components/auth/auth-button';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';
import AuthLayout from '@/layouts/auth-layout';

declare const route: any;

/** Shown after the workspace owner set a temporary password on the Team page. */
export default function ChangePassword({ email }: { email: string }) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];
    const { data, setData, put, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('password.change.update'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title={t('Choose a new password')}
            description={t('You signed in with a temporary password from your workspace owner. Choose your own password to continue.')}
            icon={<KeyRound className="h-7 w-7" style={{ color: primaryColor }} />}
        >
            <form onSubmit={submit} className="space-y-5">
                <div className="space-y-4">
                    <div>
                        <Label htmlFor="email" className="mb-1.5 block">{t('Email')}</Label>
                        <div className="relative">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Mail className="h-[18px] w-[18px] text-ds-fg-muted" aria-hidden="true" />
                            </div>
                            <Input id="email" type="email" readOnly value={email} autoComplete="username" className="pl-10" />
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="password" className="mb-1.5 block">{t('New password')}</Label>
                        <div className="relative">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Lock className="h-[18px] w-[18px] text-ds-fg-muted" aria-hidden="true" />
                            </div>
                            <Input
                                id="password"
                                type="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                className="pl-10"
                                aria-describedby="password-hint"
                            />
                        </div>
                        <p id="password-hint" className="mt-1 text-xs text-ds-fg-muted">{t('At least 8 characters.')}</p>
                        <InputError message={errors.password} />
                    </div>

                    <div>
                        <Label htmlFor="password_confirmation" className="mb-1.5 block">{t('Confirm new password')}</Label>
                        <div className="relative">
                            <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Lock className="h-[18px] w-[18px] text-ds-fg-muted" aria-hidden="true" />
                            </div>
                            <Input
                                id="password_confirmation"
                                type="password"
                                required
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                placeholder="••••••••"
                                className="pl-10"
                            />
                        </div>
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>

                <AuthButton processing={processing}>{t('Save new password')}</AuthButton>

                <p className="text-center text-sm text-ds-fg-muted">
                    <button type="button" className="underline underline-offset-4 hover:text-ds-fg" onClick={() => router.post(route('logout'))}>
                        {t('Sign out')}
                    </button>
                </p>
            </form>
        </AuthLayout>
    );
}
