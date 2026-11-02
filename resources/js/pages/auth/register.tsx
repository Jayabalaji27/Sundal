import { useForm } from '@inertiajs/react';
import { Mail, Lock, User } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from 'react-i18next';
import AuthLayout from '@/layouts/auth-layout';
import AuthButton from '@/components/auth/auth-button';
import Recaptcha from '@/components/recaptcha';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';


type RegisterForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    terms: boolean;
    recaptcha_token?: string;
    plan_id?: string;
    referral_code?: string;
};

export default function Register({ referralCode, planId }: { referralCode?: string; planId?: string }) {
    const { t } = useTranslation();
    const [recaptchaToken, setRecaptchaToken] = useState<string>('');
    const { themeColor, customColor } = useBrand();

    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
        plan_id: planId,
        referral_code: referralCode,
        recaptcha_token: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            ...data,
            recaptcha_token: recaptchaToken
        }, {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title={t("Create your account")}
            description={t("Enter your details below to get started")}
        >
            <form className="space-y-5" onSubmit={submit}>
                <div className="space-y-4">
                    <div className="relative">
                        <Label htmlFor="name" className="mb-1.5 block">{t("Full name")}</Label>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <User className="h-[18px] w-[18px] text-ds-fg-muted" aria-hidden="true" />
                            </div>
                            <Input
                                id="name"
                                type="text"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={t("John Doe")}
                                className="pl-10"
                            />
                        </div>
                        <InputError message={errors.name} />
                    </div>

                    <div className="relative">
                        <Label htmlFor="email" className="mb-1.5 block">{t("Email address")}</Label>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Mail className="h-[18px] w-[18px] text-ds-fg-muted" aria-hidden="true" />
                            </div>
                            <Input
                                id="email"
                                type="email"
                                required
                                tabIndex={2}
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="email@example.com"
                                className="pl-10"
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    <div>
                        <Label htmlFor="password" className="mb-1.5 block">{t("Password")}</Label>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Lock className="h-[18px] w-[18px] text-ds-fg-muted" aria-hidden="true" />
                            </div>
                            <Input
                                id="password"
                                type="password"
                                required
                                tabIndex={3}
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                className="pl-10"
                            />
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    <div>
                        <Label htmlFor="password_confirmation" className="mb-1.5 block">{t("Confirm password")}</Label>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Lock className="h-[18px] w-[18px] text-ds-fg-muted" aria-hidden="true" />
                            </div>
                            <Input
                                id="password_confirmation"
                                type="password"
                                required
                                tabIndex={4}
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                placeholder="••••••••"
                                className="pl-10"
                            />
                        </div>
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <div className="flex items-start">
                        <Checkbox
                            id="terms"
                            name="terms"
                            checked={data.terms}
                            onCheckedChange={(checked) => setData('terms', checked)}
                            tabIndex={5}
                            className="mt-0.5"
                        />
                        <Label htmlFor="terms" className="ml-2 text-sm font-normal leading-5 text-ds-fg-secondary">
                            {t("I agree to the")}{' '}
                            <a href="/terms" target="_blank" rel="noopener noreferrer" style={{ color: primaryColor }}>
                                {t("Terms and Conditions")}
                            </a>
                        </Label>
                    </div>
                    <InputError message={errors.terms} />
                </div>

                <Recaptcha 
                    onVerify={(token) => {
                        setRecaptchaToken(token);
                        setData('recaptcha_token', token);
                    }}
                    onExpired={() => {
                        setRecaptchaToken('');
                        setData('recaptcha_token', '');
                    }}
                    onError={() => {
                        setRecaptchaToken('');
                        setData('recaptcha_token', '');
                    }}
                />
                <InputError message={errors.recaptcha_token} />

                <AuthButton 
                    tabIndex={6} 
                    processing={processing}
                >
                    {t("Create account")}
                </AuthButton>

                <div className="text-center text-sm text-ds-fg-secondary mt-6">
                    {t("Already have an account?")}{' '}
                    <TextLink 
                        href={route('login')} 
                        className="font-medium transition-colors duration-200" 
                        style={{ color: primaryColor }}
                        tabIndex={7}
                    >
                        {t("Log in")}
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}