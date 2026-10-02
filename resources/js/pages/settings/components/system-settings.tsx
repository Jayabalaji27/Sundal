import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useState } from 'react';
import { Save } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { useTranslation } from 'react-i18next';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import languageData from '@/../../resources/lang/language.json';
import ReactCountryFlag from 'react-country-flag';
import { Card, CardContent } from '@/components/ui/card';

interface SystemSettingsProps {
  settings?: Record<string, string>;
  timezones?: Record<string, string>;
  dateFormats?: Record<string, string>;
  timeFormats?: Record<string, string>;
}

export default function SystemSettings({
  settings = {},
  timezones = {},
  dateFormats = {},
  timeFormats = {}
}: SystemSettingsProps) {
  const { t } = useTranslation();
  const { pageProps, auth = {}, isSaasMode = false } = usePage().props as any;

  // SaaS mode ma company user with owner role - limited fields j show/save thay
  const isSaasCompany = isSaasMode && auth?.user?.type === 'company' && auth?.user?.workspace_role === 'owner';

  // Default settings
  const defaultSettings = {
    defaultLanguage: 'en',
    dateFormat: 'm-d-Y',
    timeFormat: 'g:i A',
    calendarStartDay: 'sunday',
    defaultTimezone: 'UTC',
    emailVerification: false,
    landingPageEnabled: true,
    registrationEnabled: true,
    termsConditionsUrl: '',
  };

  // Combine settings from props and page props
  const settingsData = Object.keys(settings).length > 0
    ? settings
    : (pageProps.settings || {});

  // Initialize state with merged settings
  const [systemSettings, setSystemSettings] = useState(() => ({
    defaultLanguage: settingsData.defaultLanguage || defaultSettings.defaultLanguage,
    dateFormat: settingsData.dateFormat || defaultSettings.dateFormat,
    timeFormat: settingsData.timeFormat || defaultSettings.timeFormat,
    calendarStartDay: settingsData.calendarStartDay || defaultSettings.calendarStartDay,
    defaultTimezone: settingsData.defaultTimezone || defaultSettings.defaultTimezone,
    emailVerification: settingsData.emailVerification === '1',
    landingPageEnabled: settingsData.landingPageEnabled === '1',
    registrationEnabled: settingsData.registrationEnabled === '1',
    termsConditionsUrl: settingsData.termsConditionsUrl || defaultSettings.termsConditionsUrl,
  }));



  // Handle system settings form changes
  const handleSystemSettingsChange = (field: string, value: string | boolean) => {
    setSystemSettings(prev => ({
      ...prev,
      [field]: value
    }));
  };

  // Handle system settings form submission
  const submitSystemSettings = (e: React.FormEvent) => {
    e.preventDefault();

    // SaaS company: only send limited fields
    const cleanSettings = isSaasCompany
      ? {
          defaultLanguage: systemSettings.defaultLanguage,
          dateFormat: systemSettings.dateFormat,
          timeFormat: systemSettings.timeFormat,
        }
      : {
          defaultLanguage: systemSettings.defaultLanguage,
          dateFormat: systemSettings.dateFormat,
          timeFormat: systemSettings.timeFormat,
          calendarStartDay: systemSettings.calendarStartDay,
          defaultTimezone: systemSettings.defaultTimezone,
          emailVerification: Boolean(systemSettings.emailVerification),
          landingPageEnabled: Boolean(systemSettings.landingPageEnabled),
          registrationEnabled: Boolean(systemSettings.registrationEnabled),
          termsConditionsUrl: systemSettings.termsConditionsUrl,
        };

    // Submit to backend using Inertia
    router.post(route('settings.system.update'), cleanSettings, {
      preserveScroll: true,
      onSuccess: (page) => {
            // Flash messages handled by useEffect
            toast.dismiss();
            const successMessage = page.props.flash?.success;
            const errorMessage = page.props.flash?.error;

            if (successMessage) {
                toast.success(successMessage);
            } else if (errorMessage) {
                toast.error(errorMessage);
            }
        },
      onError: (errors) => {
        const errorMessage = errors.error || Object.values(errors).join(', ') || t("Failed to update system settings");
        toast.error(errorMessage);
      }
    });
  };

  return (
    <SettingsSection
      title={t("System Settings")}
      description={t("Configure system-wide settings for your application")}
      action={
        <Button type="submit" form="system-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <Card>
        <CardContent className='p-6 space-y-4'>
      <form id="system-settings-form" onSubmit={submitSystemSettings} className="space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="grid gap-2">
            <Label htmlFor="defaultLanguage">{t("Default Language")}</Label>
            <Select
              value={systemSettings.defaultLanguage}
              onValueChange={(value) => handleSystemSettingsChange('defaultLanguage', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t("Select language")}>
                  {systemSettings.defaultLanguage && (() => {
                    const selectedLang = languageData.find(lang => lang.code === systemSettings.defaultLanguage);
                    return selectedLang ? <div className="flex items-center space-x-2">
                                        <ReactCountryFlag
                                            countryCode={selectedLang.countryCode}
                                            svg
                                            style={{
                                                width: '1.2em',
                                                height: '1.2em',
                                            }}
                                        /> <span>
                                            {t(selectedLang.name)}
                                        </span> </div> : t("Select language");
                  })()}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {languageData.map((language) => (
                  <SelectItem key={language.code} value={language.code}>
                    <div className="flex items-center space-x-2">
                       <ReactCountryFlag
                                            countryCode={language.countryCode}
                                            svg
                                            style={{
                                                width: '1.2em',
                                                height: '1.2em',
                                            }}
                                        /> <span>
                                            {t(language.name)}
                                        </span>
                    </div>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="dateFormat">{t("Date Format")}</Label>
            <Select
              value={systemSettings.dateFormat}
              onValueChange={(value) => handleSystemSettingsChange('dateFormat', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t("Select date format")} />
              </SelectTrigger>
              <SelectContent>
                {Object.keys(dateFormats).length > 0 ?
                  Object.entries(dateFormats).map(([format, example]) => (
                    <SelectItem key={format} value={format}>
                      <div className="flex items-center justify-between w-full">
                        <span>{format}</span>
                        <span className="text-muted-foreground text-sm ml-4">({example})</span>
                      </div>
                    </SelectItem>
                  )) : (
                    <>
                      <SelectItem value="M j, Y">{t("Jan 1, 2026")}</SelectItem>
                      <SelectItem value="d-m-Y">{t("01-01-2026")}</SelectItem>
                      <SelectItem value="Y-m-d">{t("2026-01-01")}</SelectItem>
                      <SelectItem value="F j, Y">{t("January 1, 2026")}</SelectItem>
                    </>
                  )
                }
              </SelectContent>
            </Select>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="timeFormat">{t("Time Format")}</Label>
            <Select
              value={systemSettings.timeFormat}
              onValueChange={(value) => handleSystemSettingsChange('timeFormat', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t("Select time format")} />
              </SelectTrigger>
              <SelectContent>
                {Object.keys(timeFormats).length > 0 ?
                  Object.entries(timeFormats).map(([format, example]) => (
                    <SelectItem key={format} value={format}>
                      <div className="flex items-center justify-between w-full">
                        <span>{format}</span>
                        <span className="text-muted-foreground text-sm ml-4">({example})</span>
                      </div>
                    </SelectItem>
                  )) : (
                    <>
                      <SelectItem value="g:i A">{t("1:30 PM")}</SelectItem>
                      <SelectItem value="H:i">{t("13:30")}</SelectItem>
                      <SelectItem value="g:i a">{t("1:30 pm")}</SelectItem>
                    </>
                  )
                }
              </SelectContent>
            </Select>
          </div>

          {!isSaasCompany && (
            <div className="grid gap-2">
              <Label htmlFor="calendarStartDay">{t("Calendar Start Day")}</Label>
              <Select
                value={systemSettings.calendarStartDay}
                onValueChange={(value) => handleSystemSettingsChange('calendarStartDay', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t("Select start day")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="sunday">{t("Sunday")}</SelectItem>
                  <SelectItem value="monday">{t("Monday")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          )}

          {!isSaasCompany && (
            <div className="grid gap-2 md:col-span-2">
              <Label htmlFor="defaultTimezone">{t("Default Timezone")}</Label>
              <Select
                value={systemSettings.defaultTimezone}
                onValueChange={(value) => handleSystemSettingsChange('defaultTimezone', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t("Select timezone")} />
                </SelectTrigger>
                <SelectContent searchable>
                  {Object.keys(timezones).length > 0 ?
                    Object.entries(timezones).map(([timezone, description]) => (
                      <SelectItem key={timezone} value={timezone}>
                        {description}
                      </SelectItem>
                    )) : (
                      <>
                        <SelectItem value="UTC">{t("UTC")}</SelectItem>
                        <SelectItem value="America/New_York">{t("Eastern Time (ET)")}</SelectItem>
                        <SelectItem value="America/Chicago">{t("Central Time (CT)")}</SelectItem>
                        <SelectItem value="Europe/London">{t("London (GMT)")}</SelectItem>
                      </>
                    )
                  }
                </SelectContent>
              </Select>
            </div>
          )}

          {!isSaasCompany && (
            <div className="grid gap-2 md:col-span-2">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="emailVerification">{t("Email Verification")}</Label>
                  <p className="text-sm text-muted-foreground">
                    {t("Require users to verify their email addresses")}
                  </p>
                </div>
                <Switch
                  id="emailVerification"
                  checked={systemSettings.emailVerification}
                  onCheckedChange={(checked) => handleSystemSettingsChange('emailVerification', checked)}
                />
              </div>
            </div>
          )}

          {!isSaasCompany && (
            <div className="grid gap-2 md:col-span-2">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="landingPageEnabled">{t("Landing Page")}</Label>
                  <p className="text-sm text-muted-foreground">
                    {t("Enable or disable the public landing page")}
                  </p>
                </div>
                <Switch
                  id="landingPageEnabled"
                  checked={systemSettings.landingPageEnabled}
                  onCheckedChange={(checked) => handleSystemSettingsChange('landingPageEnabled', checked)}
                />
              </div>
            </div>
          )}

          {(!isSaasCompany && auth.user && auth.user.type === 'superadmin') && (
            <div className="grid gap-2 md:col-span-2">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="registrationEnabled">{t("User Registration")}</Label>
                  <p className="text-sm text-muted-foreground">
                    {t("Allow new users to register accounts")}
                  </p>
                </div>
                <Switch
                  id="registrationEnabled"
                  checked={systemSettings.registrationEnabled}
                  onCheckedChange={(checked) => handleSystemSettingsChange('registrationEnabled', checked)}
                />
              </div>
            </div>
          )}

          {(!isSaasCompany && auth.user && auth.user.type === 'superadmin') && (
            <div className="grid gap-2 md:col-span-2">
              <Label htmlFor="termsConditionsUrl">{t("Terms and Conditions URL")}</Label>
              <Input
                id="termsConditionsUrl"
                type="url"
                value={systemSettings.termsConditionsUrl}
                onChange={(e) => handleSystemSettingsChange('termsConditionsUrl', e.target.value)}
                placeholder="https://example.com/terms"
              />
              <p className="text-sm text-muted-foreground">
                {t("URL for Terms and Conditions page used in registration form")}
              </p>
            </div>
          )}
        </div>
      </form>
      </CardContent>
      </Card>
    </SettingsSection>
  );
}
