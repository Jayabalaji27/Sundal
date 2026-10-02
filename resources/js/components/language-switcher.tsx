import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import ReactCountryFlag from 'react-country-flag';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Globe, Plus, Settings } from 'lucide-react';
import { usePage, router, Link } from '@inertiajs/react';
import { hasRole } from '@/utils/authorization';
import { CreateLanguageModal } from '@/components/create-language-modal';
import { useLayout } from '@/contexts/LayoutContext';
import { isDemoMode, setCookie, getCookie } from '@/utils/cookie-utils';

interface Language {
    code: string;
    name: string;
    countryCode: string;
    enabled?: boolean;
}

export const LanguageSwitcher: React.FC = () => {
    const { t, i18n } = useTranslation();
    const { auth, globalSettings, isSaasMode = true } = usePage().props as any;
    const { updatePosition } = useLayout();
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [currentLanguage, setCurrentLanguage] = useState<Language | null>(null);

    const availableLanguages = globalSettings?.availableLanguages || [];

    useEffect(() => {
        const languages = (availableLanguages || []).filter((l: any) => l.enabled !== false);
        const lang = languages.find((l: Language) => l.code === i18n.language) || languages[0];
        setCurrentLanguage(lang);

        // DON'T automatically change layout direction based on language
        // Only sync it when language is explicitly changed by user via handleLanguageChange
        // This prevents overriding manual RTL/LTR selections
    }, [i18n.language, availableLanguages]);

    const isAuthenticated = auth?.user;
    const userRoles = auth?.roles || auth?.user?.roles?.map((role: any) => role.name) || [];
    const isSuperAdmin = isAuthenticated && (hasRole('superadmin', userRoles) || auth?.user?.type === 'superadmin');
    const isWorkspaceOwner = auth?.user?.workspace_role === 'owner';


    const canManageLanguages = (isSaasMode && isSuperAdmin) || (!isSaasMode && isWorkspaceOwner);

    // RTL languages list
    const rtlLanguages = ['ar', 'he'];

    const handleLanguageChange = async (languageCode: string) => {
        const lang = availableLanguages.find((l: Language) => l.code === languageCode);
        if (lang) {
            setCurrentLanguage(lang);
            try {
                await i18n.changeLanguage(languageCode);

                const isRtl = rtlLanguages.includes(languageCode);
                let newDirection: string;
                
                if (isDemoMode()) {
                    // In demo mode, preserve current layoutDirection from brandSettings cookie
                    const brandSettingsCookie = getCookie('brandSettings');
                    let currentDirection = 'left';
                    if (brandSettingsCookie) {
                        try {
                            const parsed = JSON.parse(brandSettingsCookie);
                            currentDirection = parsed.layoutDirection || 'left';
                        } catch (error) {
                            console.error('Failed to parse brandSettings cookie', error);
                        }
                    }
                    // In demo mode, set direction based on language: right for RTL, left for non-RTL
                    newDirection = isRtl ? 'right' : 'left';
                } else {
                    // In main version, set direction based on language: right for RTL, left for non-RTL
                    newDirection = isRtl ? 'right' : 'left';
                }

                updatePosition(newDirection as 'left' | 'right');

                // Save layoutDirection to database/cookies when RTL language is selected
                if (isAuthenticated && !isDemoMode()) {
                    // Save language change for authenticated non-demo users.
                    // user.update-language.web returns a plain JSON response
                    // (not an Inertia response), so it's called via fetch —
                    // Inertia's router.post expects an Inertia-formatted
                    // response and would error on a raw JSON one.
                    fetch(route('user.update-language.web'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ language: languageCode }),
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (!data.success) {
                                console.error('Failed to change language:', data.message);
                                return;
                            }
                            // After language is saved, update layoutDirection if RTL
                            if (isRtl || (!isRtl && globalSettings?.layoutDirection === 'right')) {
                                router.post(route('settings.appearance.update'), {
                                    settings: {
                                        layoutDirection: newDirection
                                    }
                                }, {
                                    preserveScroll: true,
                                    onError: (errors) => {
                                        console.error('Failed to update layout direction:', errors);
                                    }
                                });
                            }
                        })
                        .catch((error) => {
                            console.error('Failed to change language:', error);
                        });
                } else {
                    // For demo mode or non-authenticated users, save to cookies and localStorage
                    setCookie('app_language', languageCode);
                    setCookie('layoutDirection', newDirection);
                    localStorage.setItem('i18nextLng', languageCode);

                    // Also update brandSettings cookie to keep it in sync
                    const currentBrandSettings = getCookie('brandSettings');
                    let brandSettings = {};
                    if (currentBrandSettings) {
                        try {
                            brandSettings = JSON.parse(currentBrandSettings);
                        } catch (error) {
                            console.error('Failed to parse brand settings from cookie', error);
                        }
                    }
                    setCookie('brandSettings', JSON.stringify({
                        ...brandSettings,
                        layoutDirection: newDirection
                    }));
                }

                window.dispatchEvent(new CustomEvent('languageChanged', {
                    detail: { language: languageCode, direction: newDirection }
                }));

                window.dispatchEvent(new Event('resize'));
            } catch (error) {
                console.error('Error changing language:', error);
            }
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="flex items-center gap-2 rounded-md shadow-sm border bg-white">
                    <Globe className="h-4 w-4" />
                    {currentLanguage && (
                        <>
                            <span className="text-sm font-medium hidden md:inline-block">
                                {currentLanguage.name}
                            </span>
                            <ReactCountryFlag
                                countryCode={currentLanguage.countryCode}
                                svg
                                style={{
                                    width: '1.2em',
                                    height: '1.2em',
                                }}
                            />
                        </>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end" forceMount>
                <DropdownMenuGroup>
                    <div className="max-h-48 overflow-y-auto">
                        {(availableLanguages || []).filter((language: any) => language.enabled !== false).map((language: Language) => (
                            <DropdownMenuItem
                                key={language.code}
                                onClick={() => handleLanguageChange(language.code)}
                                className={`flex items-center gap-2 ${currentLanguage?.code === language.code ? 'bg-accent' : ''}`}
                            >
                                <ReactCountryFlag
                                    countryCode={language.countryCode}
                                    svg
                                    style={{
                                        width: '1.2em',
                                        height: '1.2em',
                                    }}
                                />
                                <span>{language.name}</span>
                            </DropdownMenuItem>
                        ))}
                    </div>
                </DropdownMenuGroup>
                {canManageLanguages && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => setShowCreateModal(true)}
                            className="justify-center text-primary font-semibold cursor-pointer"
                        >
                            <Plus className="h-4 w-4 mr-2" />
                            {t('Create Language')}
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild className="justify-center text-primary font-semibold cursor-pointer">
                            <Link href={route('manage-language')}>
                                <Settings className="h-4 w-4 mr-2" />
                                {t('Manage Language')}
                            </Link>
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
            <CreateLanguageModal
                open={showCreateModal}
                onOpenChange={setShowCreateModal}
                onSuccess={() => setShowCreateModal(false)}
            />
        </DropdownMenu>
    );
};