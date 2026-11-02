import { useForm, usePage } from '@inertiajs/react';
import { isRegistrationEnabled } from '@/utils/helpers';
import { Mail, Lock } from 'lucide-react';
import { FormEventHandler, useState, useEffect } from 'react';

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
import { DemoLogins, DemoLoginsPanel } from '@/components/auth/demo-logins';


type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
    recaptcha_token?: string;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    /** Demo accounts to list, only while DEMO_LOGIN_PANEL is on (config/demo.php). */
    demoLogins?: DemoLogins | null;
}

export default function Login({ status, canResetPassword, demoLogins }: LoginProps) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();

    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];
    const [isDemo, setIsDemo] = useState<boolean>(false);
    const [recaptchaToken, setRecaptchaToken] = useState<string>('');
    

    const pageProps = usePage().props as any;
    
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
        recaptcha_token: '',
    });
    
    useEffect(() => {
        const isDemoMode = (window as any).isDemo === true;
        setIsDemo(isDemoMode);
        
        if (isDemoMode) {
            setData({
                email: 'company@example.com',
                password: 'password',
                remember: false
            });
        }
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        
        // Check if ReCaptcha is enabled and token is required
        const { settings = {} } = pageProps;
        const recaptchaEnabled = settings.recaptchaEnabled === 1 || settings.recaptchaEnabled === '1';
        const recaptchaVersion = settings.recaptchaVersion || 'v2';
        
        // Only check for token if ReCaptcha v2 is enabled (v3 generates token automatically)
        if (recaptchaEnabled && recaptchaVersion === 'v2' && !recaptchaToken) {
            alert('Please complete the ReCaptcha verification.');
            return;
        }
        
        // Update the form data with the token
        const updatedData = {
            ...data,
            recaptcha_token: recaptchaToken
        };
        
        post(route('login'), updatedData, {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title={t("Log in to your account")}
            description={t("Enter your credentials to access your account")}
            status={status}
        >
            <form className="space-y-5" onSubmit={submit}>
                <div className="space-y-4">
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
                                autoFocus
                                tabIndex={1}
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
                        <div className="flex items-center justify-between mb-1">
                            <Label htmlFor="password">{t("Password")}</Label>
                            {canResetPassword && (
                                <TextLink 
                                    href={route('password.request')} 
                                    className="text-sm transition-colors duration-200" 
                                    style={{ color: primaryColor }}
                                    tabIndex={5}
                                >
                                    {t("Forgot password?")}
                                </TextLink>
                            )}
                        </div>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Lock className="h-[18px] w-[18px] text-ds-fg-muted" aria-hidden="true" />
                            </div>
                            <Input
                                id="password"
                                type="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                className="pl-10"
                            />
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center">
                        <Checkbox
                            id="remember"
                            name="remember"
                            checked={data.remember}
                            onClick={() => setData('remember', !data.remember)}
                            tabIndex={3}
                           
                        />
                        <Label htmlFor="remember" className="ml-2 font-normal text-ds-fg-secondary">{t("Remember me")}</Label>
                    </div>
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
                    tabIndex={4} 
                    processing={processing}
                >
                    {t("Log in")}
                </AuthButton>
                
                {demoLogins && demoLogins.accounts.length > 0 && (
                    <DemoLoginsPanel
                        logins={demoLogins}
                        primaryColor={primaryColor}
                        selectedEmail={data.email}
                        onFill={(email, password) => setData({ ...data, email, password })}
                    />
                )}

                {(pageProps.isSaasMode && isRegistrationEnabled(pageProps)) && (
                    <div className="text-center text-sm text-ds-fg-secondary mt-6">
                        {t("Don't have an account?")}{' '}
                        <TextLink 
                            href={route('register')} 
                            className="font-medium transition-colors duration-200" 
                            style={{ color: primaryColor }}
                            tabIndex={6}
                        >
                            {t("Sign up")}
                        </TextLink>
                    </div>
                )}
            </form>
        </AuthLayout>
    );
}