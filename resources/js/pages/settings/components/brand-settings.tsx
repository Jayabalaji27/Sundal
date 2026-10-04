import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ThemePreview } from '@/components/theme-preview';
import { useAppearance, type Appearance, type ThemeColor } from '@/hooks/use-appearance';
import { useLayout, type LayoutPosition } from '@/contexts/LayoutContext';
import { useSidebarSettings } from '@/contexts/SidebarContext';
import { useBrand } from '@/contexts/BrandContext';
import { Separator } from '@/components/ui/separator';
import { toast } from 'sonner';
import { Palette, Save, Upload, Check, Layout, Moon, FileText, Sidebar as SidebarIcon } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { SidebarPreview } from '@/components/sidebar-preview';
import MediaPicker from '@/components/MediaPicker';
import { useTranslation } from 'react-i18next';
import { usePage, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';

// Define the brand settings interface
export interface BrandSettings {
  logoDark: string;
  logoLight: string;
  favicon: string;
  titleText: string;
  footerText: string;
  themeColor: ThemeColor;
  customColor: string;
  sidebarVariant: string;
  sidebarStyle: string;
  layoutDirection: LayoutPosition;
  themeMode: Appearance;
}

// Default brand settings
export const DEFAULT_BRAND_SETTINGS: BrandSettings = {
  logoDark: 'images/logos/logo-dark.png',
  logoLight: 'images/logos/logo-light.png',
  favicon: 'images/logos/favicon.png',
  titleText: 'Taskly',
  footerText: `© ${new Date().getFullYear()} Taskly. All rights reserved.`,
  themeColor: 'green',
  customColor: '#10B77f',
  sidebarVariant: 'inset',
  sidebarStyle: 'plain',
  layoutDirection: 'left',
  themeMode: 'light',
};

// Cookie utility functions
const setCookie = (name: string, value: string, days = 365) => {
  if (typeof document === 'undefined') return;
  const maxAge = days * 24 * 60 * 60;
  document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getCookie = (name: string): string | null => {
  if (typeof document === 'undefined') return null;
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) {
    const cookieValue = parts.pop()?.split(';').shift();
    return cookieValue ? decodeURIComponent(cookieValue) : null;
  }
  return null;
};

// Get brand settings from database (normal mode) or cookies (demo mode)
export const getBrandSettings = (userSettings?: Record<string, string>, isDemoMode?: boolean): BrandSettings => {
  // Helper function to get cookie value
  const getCookie = (name: string): string | null => {
    if (typeof document === 'undefined') return null;
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return decodeURIComponent(parts.pop()?.split(';').shift() || '');
    return null;
  };

  // Demo mode: use localStorage for logos/favicon, cookies for other settings
  if (isDemoMode) {
    const cookieData = getCookie('brandSettings');
    const logoData = localStorage.getItem('brandLogos');

    let cookieSettings = {};
    let logoSettings = {};

    if (cookieData) {
      try {
        cookieSettings = JSON.parse(cookieData);
      } catch (error) {
        console.error('Failed to parse brand settings from cookie', error);
      }
    }

    if (logoData) {
      try {
        logoSettings = JSON.parse(logoData);
      } catch (error) {
        console.error('Failed to parse logo settings from localStorage', error);
      }
    }

    const getFullUrl = (path: string, defaultPath: string) => {
      if (!path) path = defaultPath;
      if (path.startsWith('http')) return path;
      const appUrl = (window as any).__APP_URL__ || (userSettings as any)?.base_url || window.location.origin;
      return `${appUrl}/${path.replace(/^\//, '')}`;
    };

    return {
      logoDark: getFullUrl((logoSettings as any).logoDark, DEFAULT_BRAND_SETTINGS.logoDark),
      logoLight: getFullUrl((logoSettings as any).logoLight, DEFAULT_BRAND_SETTINGS.logoLight),
      favicon: getFullUrl((logoSettings as any).favicon, DEFAULT_BRAND_SETTINGS.favicon),
      titleText: (cookieSettings as any).titleText || DEFAULT_BRAND_SETTINGS.titleText,
      footerText: (cookieSettings as any).footerText || DEFAULT_BRAND_SETTINGS.footerText,
      themeColor: ((cookieSettings as any).themeColor as ThemeColor) || DEFAULT_BRAND_SETTINGS.themeColor,
      customColor: (cookieSettings as any).customColor || DEFAULT_BRAND_SETTINGS.customColor,
      sidebarVariant: (cookieSettings as any).sidebarVariant || DEFAULT_BRAND_SETTINGS.sidebarVariant,
      sidebarStyle: (cookieSettings as any).sidebarStyle || DEFAULT_BRAND_SETTINGS.sidebarStyle,
      layoutDirection: ((cookieSettings as any).layoutDirection as LayoutPosition) || DEFAULT_BRAND_SETTINGS.layoutDirection,
      themeMode: ((cookieSettings as any).themeMode as Appearance) || DEFAULT_BRAND_SETTINGS.themeMode,
    };
  }

  // Normal mode: use database settings only (no cookies)
  if (userSettings) {
    const getFullUrl = (path: string, defaultPath: string) => {
      if (!path) path = defaultPath;
      if (path.startsWith('http')) return path;
      const appUrl = (window as any).__APP_URL__ || (userSettings as any)?.base_url || window.location.origin;
      return `${appUrl}/${path.replace(/^\//, '')}`;
    };

    return {
      logoDark: getFullUrl(userSettings.logoDark, DEFAULT_BRAND_SETTINGS.logoDark),
      logoLight: getFullUrl(userSettings.logoLight, DEFAULT_BRAND_SETTINGS.logoLight),
      favicon: getFullUrl(userSettings.favicon, DEFAULT_BRAND_SETTINGS.favicon),
      titleText: userSettings.titleText || DEFAULT_BRAND_SETTINGS.titleText,
      footerText: userSettings.footerText || DEFAULT_BRAND_SETTINGS.footerText,
      themeColor: (userSettings.themeColor as ThemeColor) || DEFAULT_BRAND_SETTINGS.themeColor,
      customColor: userSettings.customColor || DEFAULT_BRAND_SETTINGS.customColor,
      sidebarVariant: userSettings.sidebarVariant || DEFAULT_BRAND_SETTINGS.sidebarVariant,
      sidebarStyle: userSettings.sidebarStyle || DEFAULT_BRAND_SETTINGS.sidebarStyle,
      layoutDirection: (userSettings.layoutDirection as LayoutPosition) || DEFAULT_BRAND_SETTINGS.layoutDirection,
      themeMode: (userSettings.themeMode as Appearance) || DEFAULT_BRAND_SETTINGS.themeMode,
    };
  }

  // Fallback to defaults with full URLs
  const appUrl = (window as any).__APP_URL__ || (userSettings as any)?.base_url || window.location.origin;
  const fallbackSettings = {
    ...DEFAULT_BRAND_SETTINGS,
    logoDark: `${appUrl}/${DEFAULT_BRAND_SETTINGS.logoDark}`,
    logoLight: `${appUrl}/${DEFAULT_BRAND_SETTINGS.logoLight}`,
    favicon: `${appUrl}/${DEFAULT_BRAND_SETTINGS.favicon}`,
  };
  return fallbackSettings;
};

interface BrandSettingsProps {
  userSettings?: Record<string, string>;
}

export default function BrandSettings({ userSettings }: BrandSettingsProps) {
  const { t } = useTranslation();
  const { props } = usePage();
  const currentGlobalSettings = (props as any).globalSettings;
  const isDemoMode = (props as any).isDemoMode || (props as any).is_demo;
  const [settings, setSettings] = useState<BrandSettings>(() => getBrandSettings(currentGlobalSettings || userSettings, isDemoMode));
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [activeSection, setActiveSection] = useState<'logos' | 'text' | 'theme'>('logos');

  // Get theme hooks
  const {
    updateAppearance,
    updateThemeColor,
    updateCustomColor,
    saveThemeSettings
  } = useAppearance();

  // const { updatePosition, saveLayoutPosition, position: currentLayoutPosition } = useLayout();
  const { updatePosition, saveLayoutPosition } = useLayout();
  const { updateVariant, updateStyle, saveSidebarSettings } = useSidebarSettings();

  // Load settings when globalSettings change (but not while saving)
  useEffect(() => {
    if (isSaving) return; // Don't reset form while saving

    const brandSettings = getBrandSettings(currentGlobalSettings || userSettings, isDemoMode);

    // NEVER automatically update layout direction - only update other settings
    setSettings(prev => ({
      ...brandSettings,
      layoutDirection: prev.layoutDirection // Keep current user selection
    }));

    // Also sync sidebar settings from cookies or localStorage
    try {
      const isDemo = currentGlobalSettings?.is_demo || false;
      let sidebarSettings = null;

      if (isDemo) {
        // In demo mode, get from cookies
        sidebarSettings = getCookie('sidebarSettings');
      }
      // In non-demo mode, sidebar settings come from database via props

      if (sidebarSettings) {
        const parsedSettings = JSON.parse(sidebarSettings);
        setSettings(prev => ({
          ...prev,
          sidebarVariant: parsedSettings.variant || prev.sidebarVariant,
          sidebarStyle: parsedSettings.style || prev.sidebarStyle
        }));
      }
    } catch (error) {
      console.error('Error loading sidebar settings', error);
    }
  }, [currentGlobalSettings, userSettings, isSaving]);

  // Handle input changes
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setSettings(prev => ({ ...prev, [name]: value }));

    // Update brand context if the input is for a logo
    if (['logoLight', 'logoDark', 'favicon'].includes(name)) {
      updateBrandSettings({ [name]: value });
    }
  };



  // Convert full URL to relative path for storage
  const convertToRelativePath = (url: string): string => {
    if (!url) return url;

    // If it's already a relative path, return as is
    if (!url.startsWith('http')) return url;

    // Extract the path after /storage/
    const storageIndex = url.indexOf('/storage/');
    if (storageIndex !== -1) {
      return url.substring(storageIndex);
    }

    // Same-site files (e.g. the bundled /images/logos/*) are stored as paths,
    // otherwise the saved setting is pinned to whichever domain it was saved on.
    const origins = [(window as any).__APP_URL__, (userSettings as any)?.base_url, window.location.origin];
    for (const origin of origins) {
      if (origin && url.startsWith(`${origin.replace(/\/$/, '')}/`)) {
        return url.substring(origin.replace(/\/$/, '').length);
      }
    }

    return url;
  };

  // Handle media picker selection
  const handleMediaSelect = (name: string, url: string) => {
    setSettings(prev => ({ ...prev, [name]: url }));
    updateBrandSettings({ [name]: url });

    // In demo mode, save logos to localStorage
    if (isDemoMode && ['logoLight', 'logoDark', 'favicon'].includes(name)) {
      const currentLogos = JSON.parse(localStorage.getItem('brandLogos') || '{}');
      localStorage.setItem('brandLogos', JSON.stringify({
        ...currentLogos,
        [name]: convertToRelativePath(url)
      }));
    }
  };

  // Import useBrand hook
  const { updateBrandSettings } = useBrand();

  // Handle theme color change
  const handleThemeColorChange = (color: ThemeColor) => {
    setSettings(prev => ({ ...prev, themeColor: color }));
    updateThemeColor(color);
  };

  // Handle custom color change
  const handleCustomColorChange = (color: string) => {
    setSettings(prev => ({ ...prev, customColor: color }));
    // Set as active custom color when user is editing it
    updateCustomColor(color, true);
  };

  // Handle sidebar variant change
  const handleSidebarVariantChange = (variant: string) => {
    setSettings(prev => ({ ...prev, sidebarVariant: variant }));
    updateVariant(variant as any);
  };

  // Handle sidebar style change
  const handleSidebarStyleChange = (style: string) => {
    setSettings(prev => ({ ...prev, sidebarStyle: style }));
    updateStyle(style);
  };

  // Handle layout direction change
  const handleLayoutDirectionChange = (direction: LayoutPosition) => {
    setSettings(prev => ({ ...prev, layoutDirection: direction }));
    updatePosition(direction);
    window.dispatchEvent(new CustomEvent('layoutDirectionChanged', {
      detail: { direction }
    }));
  };

  // Handle theme mode change
  const handleThemeModeChange = (mode: Appearance) => {
    setSettings(prev => ({ ...prev, themeMode: mode }));
    updateAppearance(mode);

    // Force immediate logo update in brand context
    updateBrandSettings({
      logoLight: settings.logoLight,
      logoDark: settings.logoDark,
      favicon: settings.favicon
    });
  };



  // Save settings
  const saveSettings = () => {
    setIsLoading(true);
    setIsSaving(true);

    // Update theme settings
    updateThemeColor(settings.themeColor);
    if (settings.themeColor === 'custom') {
      updateCustomColor(settings.customColor);
    }
    updateAppearance(settings.themeMode);
    updatePosition(settings.layoutDirection);

    // Update sidebar settings
    updateVariant(settings.sidebarVariant as any);
    updateStyle(settings.sidebarStyle);

    // Save all settings to cookies in demo mode
    saveThemeSettings();
    saveSidebarSettings();
    saveLayoutPosition(settings.layoutDirection);

    // Update brand context
    updateBrandSettings({
      logoLight: settings.logoLight,
      logoDark: settings.logoDark,
      favicon: settings.favicon
    });

    if (isDemoMode) {
      // Demo mode: save logos to localStorage, other settings to cookies
      const logoSettings = {
        logoDark: convertToRelativePath(settings.logoDark),
        logoLight: convertToRelativePath(settings.logoLight),
        favicon: convertToRelativePath(settings.favicon)
      };
      localStorage.setItem('brandLogos', JSON.stringify(logoSettings));

      const otherSettings = {
        titleText: settings.titleText,
        footerText: settings.footerText,
        themeColor: settings.themeColor,
        customColor: settings.customColor,
        sidebarVariant: settings.sidebarVariant,
        sidebarStyle: settings.sidebarStyle,
        layoutDirection: settings.layoutDirection,
        themeMode: settings.themeMode
      };
      setCookie('brandSettings', JSON.stringify(otherSettings));

      setIsLoading(false);
      setIsSaving(false);
      toast.error('This action is disabled in demo mode. You can only create new data, not modify existing demo data.');
    } else {
      // Normal mode: save ALL settings to database (no cookies)
      const settingsToSave = {
        ...settings,
        logoDark: convertToRelativePath(settings.logoDark),
        logoLight: convertToRelativePath(settings.logoLight),
        favicon: convertToRelativePath(settings.favicon)
      };

      router.post(route('settings.brand.update'), {
        settings: settingsToSave
      }, {
        preserveScroll: true,
        onSuccess: (page) => {
            setIsLoading(false);
            const successMessage = page.props.flash?.success;
            const errorMessage = page.props.flash?.error;

            if (successMessage) {
                toast.success(successMessage);
            } else if (errorMessage) {
                toast.error(errorMessage);
            }
            setTimeout(() => setIsSaving(false), 500);
        },
        onError: (errors) => {
          setIsLoading(false);
          setIsSaving(false);
          const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to save brand settings');
          toast.error(errorMessage);
        }
      });
    }
  };

  return (
    <SettingsSection
      title={t("Brand Settings")}
      description={t("Customize your application's branding and appearance")}
      action={
        <Button onClick={saveSettings} disabled={isLoading} size="sm">
          <Save className="h-4 w-4 mr-2" />
          {isLoading ? t('Saving...') : t('Save Changes')}
        </Button>
      }
    >
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2">
        <Card>
          <CardContent>
              <div className="flex space-x-2 mb-6 mt-6">
                <Button
                  variant={activeSection === 'logos' ? "default" : "outline"}
                  size="sm"
                  onClick={() => setActiveSection('logos')}
                  className="flex-1"
                >
                  <Upload className="h-4 w-4 mr-2" />
                  {t("Logos")}
                </Button>
                <Button
                  variant={activeSection === 'text' ? "default" : "outline"}
                  size="sm"
                  onClick={() => setActiveSection('text')}
                  className="flex-1"
                >
                  <FileText className="h-4 w-4 mr-2" />
                  {t("Text")}
                </Button>
                <Button
                  variant={activeSection === 'theme' ? "default" : "outline"}
                  size="sm"
                  onClick={() => setActiveSection('theme')}
                  className="flex-1"
                >
                  <Palette className="h-4 w-4 mr-2" />
                  {t("Theme")}
                </Button>
              </div>

              {/* Logos Section */}
              {activeSection === 'logos' && (
                <div className="space-y-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-3">
                      <Label>{t("Logo (Dark Mode)")}</Label>
                      <div className="flex flex-col gap-3">
                        <div className="border rounded-md p-4 flex items-center justify-center bg-muted/30 dark:bg-white h-32">
                          {settings.logoDark ? (
                            <img
                              src={(() => {
                                const appUrl = (window as any).__APP_URL__ || currentGlobalSettings?.base_url || window.location.origin;
                                return settings.logoDark.startsWith('http') ? settings.logoDark : `${appUrl}/${settings.logoDark.replace(/^\//, '')}`;
                              })()}
                              alt="Dark Logo"
                              className="max-h-full max-w-full object-contain"
                              onError={(e) => {
                                (e.currentTarget as HTMLImageElement).src = 'images/default/image-not-found.jpg';
                              }}
                            />
                          ) : (
                            <div className="text-muted-foreground flex flex-col items-center gap-2">
                              <div className="h-12 w-24 bg-muted flex items-center justify-center rounded border border-dashed">
                                <span className="font-semibold text-muted-foreground">{t("Logo")}</span>
                              </div>
                              <span className="text-xs">No logo selected</span>
                            </div>
                          )}
                        </div>
                        <MediaPicker
                          label=""
                          value={settings.logoDark}
                          onChange={(url) => handleMediaSelect('logoDark', url)}
                          placeholder="Select dark mode logo..."
                          showPreview={false}
                        />
                      </div>
                    </div>

                    <div className="space-y-3">
                      <Label>{t("Logo (Light Mode)")}</Label>
                      <div className="flex flex-col gap-3">
                        <div className="border rounded-md p-4 flex items-center justify-center bg-black h-32">
                          {settings.logoLight ? (
                            <img
                              src={(() => {
                                const appUrl = (window as any).__APP_URL__ || currentGlobalSettings?.base_url || window.location.origin;
                                return settings.logoLight.startsWith('http') ? settings.logoLight : `${appUrl}/${settings.logoLight.replace(/^\//, '')}`;
                              })()}
                              alt="Light Logo"
                              className="max-h-full max-w-full object-contain"
                            />
                          ) : (
                            <div className="text-muted-foreground flex flex-col items-center gap-2">
                              <div className="h-12 w-24 bg-muted flex items-center justify-center rounded border border-dashed">
                                <span className="font-semibold text-muted-foreground">{t("Logo")}</span>
                              </div>
                              <span className="text-xs">No logo selected</span>
                            </div>
                          )}
                        </div>
                        <MediaPicker
                          label=""
                          value={settings.logoLight}
                          onChange={(url) => handleMediaSelect('logoLight', url)}
                          placeholder="Select light mode logo..."
                          showPreview={false}
                        />
                      </div>
                    </div>

                    <div className="space-y-3">
                      <Label>{t("Favicon")}</Label>
                      <div className="flex flex-col gap-3">
                        <div className="border rounded-md p-4 flex items-center justify-center bg-muted/30 h-20">
                          {settings.favicon ? (
                            <img
                              src={(() => {
                                const appUrl = (window as any).__APP_URL__ || currentGlobalSettings?.base_url || window.location.origin;
                                return settings.favicon.startsWith('http') ? settings.favicon : `${appUrl}/${settings.favicon.replace(/^\//, '')}`;
                              })()}
                              alt="Favicon"
                              className="h-16 w-16 object-contain"
                            />
                          ) : (
                            <div className="text-muted-foreground flex flex-col items-center gap-1">
                              <div className="h-10 w-10 bg-muted flex items-center justify-center rounded border border-dashed">
                                <span className="font-semibold text-xs text-muted-foreground">{t("Icon")}</span>
                              </div>
                              <span className="text-xs">No favicon selected</span>
                            </div>
                          )}
                        </div>
                        <MediaPicker
                          label=""
                          value={settings.favicon}
                          onChange={(url) => handleMediaSelect('favicon', url)}
                          placeholder="Select favicon..."
                          showPreview={false}
                        />
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* Text Section */}
              {activeSection === 'text' && (
                <div className="space-y-6">
                  <div className="grid grid-cols-1 gap-6">
                    <div className="space-y-3">
                      <Label required htmlFor="titleText">{t("Title Text")}</Label>
                      <Input
                      required
                        id="titleText"
                        name="titleText"
                        value={settings.titleText}
                        onChange={handleInputChange}
                        placeholder="WorkDo"
                      />
                      <p className="text-xs text-muted-foreground">
                        {t("Application title displayed in the browser tab")}
                      </p>
                    </div>

                    <div className="space-y-3">
                      <Label required htmlFor="footerText">{t("Footer Text")}</Label>
                      <Input
                      required
                        id="footerText"
                        name="footerText"
                        value={settings.footerText}
                        onChange={handleInputChange}
                        placeholder="© 2026 WorkDo. All rights reserved."
                      />
                      <p className="text-xs text-muted-foreground">
                        {t("Text displayed in the footer")}
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Theme Section */}
              {activeSection === 'theme' && (
                <div className="space-y-6">
                  <div className="flex flex-col space-y-8">
                    {/* Theme Color Section */}
                    <div className="space-y-4">
                      <div className="flex items-center">
                        <Palette className="h-5 w-5 mr-2 text-muted-foreground" />
                        <h3 className="text-base font-medium">{t("Theme Color")}</h3>
                      </div>
                      <Separator className="my-2" />

                      <div className="grid grid-cols-6 gap-2">
                        {Object.entries({ blue: '#3b82f6', green: '#10B77F', purple: '#8b5cf6', orange: '#f97316', red: '#ef4444' }).map(([color, hex]) => (
                          <Button
                            key={color}
                            type="button"
                            variant={settings.themeColor === color ? "default" : "outline"}
                            className="h-8 w-full p-0 relative"
                            style={{ backgroundColor: settings.themeColor === color ? hex : 'transparent' }}
                            onClick={() => handleThemeColorChange(color as ThemeColor)}
                          >
                            <span
                              className="absolute inset-1 rounded-sm"
                              style={{ backgroundColor: hex }}
                            />
                          </Button>
                        ))}
                        <Button
                          type="button"
                          variant={settings.themeColor === 'custom' ? "default" : "outline"}
                          className="h-8 w-full p-0 relative"
                          style={{ backgroundColor: settings.themeColor === 'custom' ? settings.customColor : 'transparent' }}
                          onClick={() => handleThemeColorChange('custom')}
                        >
                          <span
                            className="absolute inset-1 rounded-sm"
                            style={{ backgroundColor: settings.customColor }}
                          />
                        </Button>
                      </div>

                      {settings.themeColor === 'custom' && (
                        <div className="space-y-2 mt-4">
                          <Label htmlFor="customColor">{t("Custom Color")}</Label>
                          <div className="flex gap-2">
                            <div className="relative">
                              <Input
                                id="colorPicker"
                                type="color"
                                value={settings.customColor}
                                onChange={(e) => handleCustomColorChange(e.target.value)}
                                className="absolute inset-0 opacity-0 cursor-pointer"
                              />
                              <div
                                className="w-10 h-10 rounded border cursor-pointer"
                                style={{ backgroundColor: settings.customColor }}
                              />
                            </div>
                            <Input
                              id="customColor"
                              name="customColor"
                              type="text"
                              value={settings.customColor}
                              onChange={(e) => handleCustomColorChange(e.target.value)}
                              placeholder="#3b82f6"
                            />
                          </div>
                        </div>
                      )}
                    </div>

                    {/* Sidebar Section */}
                    <div className="space-y-4">
                      <div className="flex items-center">
                        <SidebarIcon className="h-5 w-5 mr-2 text-muted-foreground" />
                        <h3 className="text-base font-medium">{t("Sidebar")}</h3>
                      </div>
                      <Separator className="my-2" />

                      <div className="space-y-6">
                        <div>
                          <Label className="mb-2 block">{t("Sidebar Variant")}</Label>
                          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {['inset', 'floating', 'minimal'].map((variant) => (
                              <Button
                                key={variant}
                                type="button"
                                variant={settings.sidebarVariant === variant ? "default" : "outline"}
                                className="h-10 justify-start"
                                style={{
                                  backgroundColor: settings.sidebarVariant === variant ?
                                    (settings.themeColor === 'custom' ? settings.customColor : null) :
                                    'transparent'
                                }}
                                onClick={() => handleSidebarVariantChange(variant)}
                              >
                                {variant.charAt(0).toUpperCase() + variant.slice(1)}
                                {settings.sidebarVariant === variant && (
                                  <Check className="h-4 w-4 ml-2" />
                                )}
                              </Button>
                            ))}
                          </div>
                        </div>

                        <div>
                          <Label className="mb-2 block">{t("Sidebar Style")}</Label>
                          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {[
                              { id: 'plain', name: 'Plain' },
                              { id: 'colored', name: 'Colored' },
                              { id: 'gradient', name: 'Gradient' }
                            ].map((style) => (
                              <Button
                                key={style.id}
                                type="button"
                                variant={settings.sidebarStyle === style.id ? "default" : "outline"}
                                className="h-10 justify-start"
                                style={{
                                  backgroundColor: settings.sidebarStyle === style.id ?
                                    (settings.themeColor === 'custom' ? settings.customColor : null) :
                                    'transparent'
                                }}
                                onClick={() => handleSidebarStyleChange(style.id)}
                              >
                                {style.name}
                                {settings.sidebarStyle === style.id && (
                                  <Check className="h-4 w-4 ml-2" />
                                )}
                              </Button>
                            ))}
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Layout Section */}
                    <div className="space-y-4">
                      <div className="flex items-center">
                        <Layout className="h-5 w-5 mr-2 text-muted-foreground" />
                        <h3 className="text-base font-medium">{t("Layout")}</h3>
                      </div>
                      <Separator className="my-2" />

                      <div className="space-y-2">
                        <Label className="mb-2 block">{t("Layout Direction")}</Label>
                        <div className="grid grid-cols-2 gap-2">
                          <Button
                            type="button"
                            variant={settings.layoutDirection === "left" ? "default" : "outline"}
                            className="h-10 justify-start"
                            style={{
                              backgroundColor: settings.layoutDirection === "left" ?
                                (settings.themeColor === 'custom' ? settings.customColor : null) :
                                'transparent'
                            }}
                            onClick={() => handleLayoutDirectionChange("left")}
                          >
                            {t("Left-to-Right")}
                            {settings.layoutDirection === "left" && (
                              <Check className="h-4 w-4 ml-2" />
                            )}
                          </Button>
                          <Button
                            type="button"
                            variant={settings.layoutDirection === "right" ? "default" : "outline"}
                            className="h-10 justify-start"
                            style={{
                              backgroundColor: settings.layoutDirection === "right" ?
                                (settings.themeColor === 'custom' ? settings.customColor : null) :
                                'transparent'
                            }}
                            onClick={() => handleLayoutDirectionChange("right")}
                          >
                            {t("Right-to-Left")}
                            {settings.layoutDirection === "right" && (
                              <Check className="h-4 w-4 ml-2" />
                            )}
                          </Button>
                        </div>
                      </div>
                    </div>

                    {/* Mode Section */}
                    <div className="space-y-4">
                      <div className="flex items-center">
                        <Moon className="h-5 w-5 mr-2 text-muted-foreground" />
                        <h3 className="text-base font-medium">{t("Theme Mode")}</h3>
                      </div>
                      <Separator className="my-2" />

                      <div className="space-y-2">
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                          <Button
                            type="button"
                            variant={settings.themeMode === "light" ? "default" : "outline"}
                            className="h-10 justify-start"
                            style={{
                              backgroundColor: settings.themeMode === "light" ?
                                (settings.themeColor === 'custom' ? settings.customColor : null) :
                                'transparent'
                            }}
                            onClick={() => handleThemeModeChange("light")}
                          >
                            {t("Light")}
                            {settings.themeMode === "light" && (
                              <Check className="h-4 w-4 ml-2" />
                            )}
                          </Button>
                          <Button
                            type="button"
                            variant={settings.themeMode === "dark" ? "default" : "outline"}
                            className="h-10 justify-start"
                            style={{
                              backgroundColor: settings.themeMode === "dark" ?
                                (settings.themeColor === 'custom' ? settings.customColor : null) :
                                'transparent'
                            }}
                            onClick={() => handleThemeModeChange("dark")}
                          >
                            {t("Dark")}
                            {settings.themeMode === "dark" && (
                              <Check className="h-4 w-4 ml-2" />
                            )}
                          </Button>
                          <Button
                            type="button"
                            variant={settings.themeMode === "system" ? "default" : "outline"}
                            className="h-10 justify-start"
                            style={{
                              backgroundColor: settings.themeMode === "system" ?
                                (settings.themeColor === 'custom' ? settings.customColor : null) :
                                'transparent'
                            }}
                            onClick={() => handleThemeModeChange("system")}
                          >
                            {t("System")}
                            {settings.themeMode === "system" && (
                              <Check className="h-4 w-4 ml-2" />
                            )}
                          </Button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}
          </CardContent>
        </Card>
            </div>

        {/* Preview Column */}
        <div className="lg:col-span-1">
          <div className="sticky top-20 space-y-6">
            <div className="border rounded-md p-4">
              <div className="flex items-center gap-2 mb-4">
                <Palette className="h-4 w-4" />
                <h3 className="font-medium">{t("Live Preview")}</h3>
              </div>

              {/* Comprehensive Theme Preview */}
              <ThemePreview />

              {/* Text Preview */}
              <div className="mt-4 pt-4 border-t">
                <div className="text-xs mb-2 text-muted-foreground">Title: <span className="font-medium text-foreground">{settings.titleText}</span></div>
                <div className="text-xs text-muted-foreground">Footer: <span className="font-medium text-foreground">{settings.footerText}</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </SettingsSection>
  );
}
