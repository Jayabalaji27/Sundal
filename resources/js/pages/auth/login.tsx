import { useForm, router, usePage } from '@inertiajs/react';
import { Mail, Lock } from 'lucide-react';
import { FormEventHandler, useState, useEffect } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import AuthLayout from '@/layouts/auth-layout';
import AuthButton from '@/components/auth/auth-button';
import Recaptcha from '@/components/recaptcha';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';


type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
    recaptcha_token?: string;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
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
                        <Label htmlFor="email" className="text-gray-700 dark:text-gray-300 font-medium mb-1 block">{t("Email address")}</Label>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Mail className="h-5 w-5 text-gray-400" />
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
                                className="pl-10 w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg transition-all duration-200"
                                style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <Label htmlFor="password" className="text-gray-700 dark:text-gray-300 font-medium">{t("Password")}</Label>
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
                                <Lock className="h-5 w-5 text-gray-400" />
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
                                className="pl-10 w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg transition-all duration-200"
                                style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
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
                            className="border-gray-300 rounded"
                            style={{ '--tw-ring-color': primaryColor, color: primaryColor } as React.CSSProperties}
                        />
                        <Label htmlFor="remember" className="ml-2 text-gray-600 dark:text-gray-400">{t("Remember me")}</Label>
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
                
                {isDemo && (
                    <div className="mt-6">
                        <div className="border-t border-gray-200 dark:border-gray-700 pt-5">
                            <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4 text-center">{t("Demo Quick Access")}</h3>
                            
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {pageProps.isSaasMode && (
                                    <Button 
                                        type="button" 
                                        onClick={() => {
                                            router.post(route('login'), {
                                                email: 'superadmin@example.com',
                                                password: 'password',
                                                remember: false
                                            });
                                        }}
                                        className="text-white px-4 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                        style={{ backgroundColor: primaryColor }}
                                    >
                                        {t("Login as Super Admin")}
                                    </Button>
                                )}
                                <Button 
                                    type="button" 
                                    onClick={() => {
                                        router.post(route('login'), {
                                            email: 'company@example.com',
                                            password: 'password',
                                            remember: false
                                        });
                                    }}
                                    className="text-white px-4 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                    style={{ backgroundColor: primaryColor }}
                                >
                                    {t("Login as Company")}
                                </Button>
                                        <Button 
                                            type="button" 
                                            onClick={() => {
                                                router.post(route('login'), {
                                                    email: 'sarah.johnson@techcorp.com',
                                                    password: 'password',
                                                    remember: false
                                                });
                                            }}
                                            className="text-white px-4 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            {t("Login as Manager")}
                                        </Button>
                                        <Button 
                                            type="button" 
                                            onClick={() => {
                                                router.post(route('login'), {
                                                    email: 'david.kim@techcorp.com',
                                                    password: 'password',
                                                    remember: false
                                                });
                                            }}
                                            className="text-white px-4 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            {t("Login as Member")}
                                        </Button>
                                        <Button 
                                            type="button" 
                                            onClick={() => {
                                                router.post(route('login'), {
                                                    email: 'globaltechindustries@client.com',
                                                    password: 'password',
                                                    remember: false
                                                });
                                            }}
                                            className="text-white px-4 py-2 rounded-md text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            {t("Login as Client")}
                                        </Button>
                            </div>
                        </div>
                    </div>
                )}

                {(pageProps.isSaasMode) && (
                    <div className="text-center text-sm text-gray-600 dark:text-gray-400 mt-6">
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