import type { ReactNode } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useEffect, useState } from 'react';
import { Palette, DollarSign, CreditCard, ShieldCheck, Zap, Key, ChevronRight, Layers } from 'lucide-react';
import SystemSettings from './components/system-settings';
import { Link, usePage } from '@inertiajs/react';

import CurrencySettings from './components/currency-settings';

import BrandSettings from './components/brand-settings';
import EmailSettings from './components/email-settings';
import PaymentSettings from './components/payment-settings';
import StorageSettings from './components/storage-settings';
import RecaptchaSettings from './components/recaptcha-settings';
import ChatGptSettings from './components/chatgpt-settings';
import CookieSettings from './components/cookie-settings';
import SeoSettings from './components/seo-settings';
import CacheSettings from './components/cache-settings';
import WebhookSettings from './components/webhook-settings';
import SlackSettings from './components/slack-settings';
import TelegramSettings from './components/telegram-settings';
import EmailNotificationSettings from './components/email-notification-settings';
import ZoomSettings from './components/zoom-settings';
import TaxSettings from './components/tax-settings';
import InvoiceSettings from './components/invoice-settings';
import GoogleCalendarSettings from './components/google-calendar-settings';
import GoogleMeetSettings from './components/google-meet-settings';

import { Toaster } from '@/components/ui/toaster';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/permissions';

declare const route: any;

interface RoleSummary {
  id: number;
  name: string;
  label: string;
  permission_count: number;
  modules: string[];
  is_current?: boolean;
}

/** Quick-access card for an integration/module that now lives inside Settings instead of the sidebar. */
function LinkCard({ href, icon, title, description }: { href: string; icon: ReactNode; title: string; description: string }) {
  return (
    <Link href={href}>
      <Card className="cursor-pointer hover:shadow-md transition-shadow group h-full">
        <CardHeader className="pb-2">
          <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
            {icon}
          </div>
          <CardTitle className="flex items-center justify-between text-base">
            {title}
            <ChevronRight className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
          </CardTitle>
        </CardHeader>
        <CardContent>
          <CardDescription>{description}</CardDescription>
        </CardContent>
      </Card>
    </Link>
  );
}

export default function Settings() {
  const { t } = useTranslation();
  const {
    systemSettings = {}, cacheSize = '0.00', timezones = {}, dateFormats = {}, timeFormats = {},
    paymentSettings = {}, slackSettings = {}, telegramSettings = {}, webhooks = [], isSaasMode = true,
    isDemoMode = false, isSuperAdmin = false, roles = [] as RoleSummary[], taxes = [],
  } = usePage().props as any;

  useEffect(() => {
    if (typeof window !== 'undefined') {
      (window as any).appSettings = {
        ...(window as any).appSettings,
        isDemoMode
      };
    }
  }, [isDemoMode]);

  const canViewSettings = hasPermission('settings_view');
  // Only show tabs that have something in them for this role (e.g. Managers have
  // no Branding or Billing sections, so those tabs would be empty cards).
  const tabHasContent: Record<string, boolean> = {
    integrations: hasPermission('zapier_view_any') || hasPermission('api_key_view_any') || hasPermission('settings_google_calendar')
      || (canViewSettings && ['settings_slack', 'settings_telegram', 'settings_webhook', 'settings_zoom', 'settings_google_meet'].some(p => hasPermission(p))),
    branding: canViewSettings && hasPermission('settings_brand'),
    billing: (canViewSettings && (hasPermission('settings_currency') || hasPermission('settings_payment')))
      || hasPermission('tax_view_any') || hasPermission('settings_invoice'),
    roles: roles.length > 0,
    general: true,
  };
  const tabs = [
    { key: 'integrations', label: t('Integrations') },
    { key: 'branding', label: t('Branding') },
    { key: 'billing', label: t('Billing') },
    { key: 'roles', label: t('Roles') },
    { key: 'general', label: t('General') },
  ].filter(tb => tabHasContent[tb.key]);
  // "Configure" buttons elsewhere in the app link to #<section-id> anchors that
  // live inside a tab panel (e.g. #zoom-settings is inside the Integrations tab),
  // not to a top-level tab key. Map those section ids to the tab that contains them
  // so the hash still lands on the right tab instead of silently falling back to General.
  const sectionToTab: Record<string, string> = {
    'slack-settings': 'integrations',
    'telegram-settings': 'integrations',
    'webhook-settings': 'integrations',
    'zoom-settings': 'integrations',
    'google-calendar-settings': 'integrations',
    'google-meet-settings': 'integrations',
    'brand-settings': 'branding',
    'currency-settings': 'billing',
    'payment-settings': 'billing',
    'tax-settings': 'billing',
    'invoice-settings': 'billing',
    'system-settings': 'general',
    'email-settings': 'general',
    'email-notification-settings': 'general',
    'storage-settings': 'general',
    'recaptcha-settings': 'general',
    'chatgpt-settings': 'general',
    'cookie-settings': 'general',
    'seo-settings': 'general',
    'cache-settings': 'general',
  };
  const hashTab = typeof window !== 'undefined' ? window.location.hash.replace('#', '') : '';
  const resolvedTab = tabs.some(tb => tb.key === hashTab) ? hashTab : (sectionToTab[hashTab] ?? 'general');
  const [activeTab, setActiveTab] = useState(resolvedTab);

  // Once the resolved tab's panel is mounted, scroll to the specific section anchor.
  useEffect(() => {
    if (hashTab && sectionToTab[hashTab]) {
      const el = document.getElementById(hashTab);
      el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleTabChange = (key: string) => {
    setActiveTab(key);
    if (typeof window !== 'undefined') {
      window.history.replaceState(null, '', `#${key}`);
    }
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Settings') }
  ];

  return (
    <PageTemplate
      title={t('Settings')}
      url="/settings"
      breadcrumbs={breadcrumbs}
    >
      <Tabs value={activeTab} onValueChange={handleTabChange}>
        <div className="overflow-x-auto">
          <TabsList>
            {tabs.map(tb => (
              <TabsTrigger key={tb.key} value={tb.key}>{tb.label}</TabsTrigger>
            ))}
          </TabsList>
        </div>

        {/* ── Integrations ─────────────────────────────────────────────── */}
        <TabsContent value="integrations" className="space-y-8 mt-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {hasPermission('zapier_view_any') && (
              <LinkCard
                href={route('zapier.index')}
                icon={<Zap className="h-5 w-5" />}
                title={t('Zapier')}
                description={t('Connect workflows to Zapier via webhooks.')}
              />
            )}
            {hasPermission('api_key_view_any') && (
              <LinkCard
                href={route('api-keys.index')}
                icon={<Key className="h-5 w-5" />}
                title={t('BYOA (API Keys)')}
                description={t('Bring your own AI API key for agents and chat.')}
              />
            )}
          </div>

          {(hasPermission('settings_view') && hasPermission('settings_slack')) && (
            <section id="slack-settings"><SlackSettings settings={slackSettings} /></section>
          )}
          {(hasPermission('settings_view') && hasPermission('settings_telegram')) && (
            <section id="telegram-settings"><TelegramSettings settings={telegramSettings} /></section>
          )}
          {(hasPermission('settings_view') && hasPermission('settings_webhook')) && (
            <section id="webhook-settings"><WebhookSettings webhooks={webhooks} /></section>
          )}
          {(hasPermission('settings_view') && hasPermission('settings_zoom')) && (
            <section id="zoom-settings"><ZoomSettings settings={systemSettings} /></section>
          )}
          {hasPermission('settings_google_calendar') && (
            <section id="google-calendar-settings"><GoogleCalendarSettings settings={systemSettings} /></section>
          )}
          {(hasPermission('settings_view') && hasPermission('settings_google_meet')) && (
            <section id="google-meet-settings"><GoogleMeetSettings settings={systemSettings} /></section>
          )}
        </TabsContent>

        {/* ── Branding ──────────────────────────────────────────────────── */}
        <TabsContent value="branding" className="space-y-8 mt-6">
          {/* Not Super-Admin-gated: SystemSettingsController::updateBrand scopes to
              the acting company user's current workspace, so each company sets its
              own branding — this must stay open to them, not just Super Admin. */}
          {(hasPermission('settings_view') && hasPermission('settings_brand')) && (
            <section id="brand-settings"><BrandSettings /></section>
          )}
        </TabsContent>

        {/* ── Billing ───────────────────────────────────────────────────── */}
        <TabsContent value="billing" className="space-y-8 mt-6">
          {(hasPermission('settings_view') && hasPermission('settings_currency')) && (
            <section id="currency-settings"><CurrencySettings /></section>
          )}
          {/* Not Super-Admin-gated: both 'superadmin' and 'company' roles hold
              settings_payment (PaymentSetting rows are stored per user_id), same
              bug pattern as Branding above — restricting to Super Admin only
              blocked the role that actually has the permission. */}
          {(hasPermission('settings_view') && hasPermission('settings_payment')) && (
            <section id="payment-settings"><PaymentSettings settings={paymentSettings} /></section>
          )}
          {hasPermission('tax_view_any') && (
            <section id="tax-settings"><TaxSettings taxes={taxes} /></section>
          )}
          {hasPermission('settings_invoice') && (
            <section id="invoice-settings"><InvoiceSettings settings={systemSettings} /></section>
          )}
        </TabsContent>

        {/* ── Roles (read-only — custom role authoring is a later phase) ─── */}
        <TabsContent value="roles" className="space-y-4 mt-6">
          <div>
            <h2 className="text-base font-semibold">{t('Roles & Permissions')}</h2>
            <p className="text-sm text-muted-foreground">{t('The permissions granted to each built-in role. Custom roles are not available yet.')}</p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {roles.map((role: RoleSummary) => (
              <Card key={role.id} className={role.is_current ? 'border-primary ring-1 ring-primary' : undefined}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-base flex items-center gap-2">
                    {role.label}
                    {role.is_current && <Badge>{t('Your role')}</Badge>}
                  </CardTitle>
                  <CardDescription>{role.permission_count} {t('permissions')}</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-1.5">
                  {role.modules.map(module => (
                    <Badge key={module} variant="secondary" className="capitalize">{module}</Badge>
                  ))}
                </CardContent>
              </Card>
            ))}
            {roles.length === 0 && (
              <p className="text-sm text-muted-foreground">{t('No roles found.')}</p>
            )}
          </div>
        </TabsContent>

        {/* ── General ───────────────────────────────────────────────────── */}
        <TabsContent value="general" className="space-y-8 mt-6">
          {(hasPermission('settings_view') && hasPermission('settings_system')) && (
            <section id="system-settings">
              <SystemSettings
                settings={systemSettings}
                timezones={timezones}
                dateFormats={dateFormats}
                timeFormats={timeFormats}
              />
            </section>
          )}
          {(hasPermission('settings_view') && hasPermission('settings_email')) && (
            <section id="email-settings"><EmailSettings /></section>
          )}
          {(hasPermission('settings_view') && hasPermission('settings_email_notification')) && (
            <section id="email-notification-settings"><EmailNotificationSettings /></section>
          )}
          {hasPermission('contract_type_view_any') && (
            <LinkCard
              href={route('contract-types.index')}
              icon={<Layers className="h-5 w-5" />}
              title={t('Contract Types')}
              description={t('Manage the contract type dropdown used when creating contracts.')}
            />
          )}
          {isSuperAdmin && (hasPermission('settings_view') && hasPermission('settings_storage')) && (
            <section id="storage-settings"><StorageSettings settings={systemSettings} /></section>
          )}
          {isSuperAdmin && (hasPermission('settings_view') && hasPermission('settings_recaptcha')) && (
            <section id="recaptcha-settings"><RecaptchaSettings settings={systemSettings} /></section>
          )}
          {(hasPermission('settings_view') && hasPermission('settings_chatgpt')) && (
            <section id="chatgpt-settings"><ChatGptSettings settings={systemSettings} /></section>
          )}
          {isSuperAdmin && (hasPermission('settings_view') && hasPermission('settings_cookie')) && (
            <section id="cookie-settings"><CookieSettings settings={systemSettings} /></section>
          )}
          {isSuperAdmin && (hasPermission('settings_view') && hasPermission('settings_seo')) && (
            <section id="seo-settings"><SeoSettings settings={systemSettings} /></section>
          )}
          {isSuperAdmin && (hasPermission('settings_view') && hasPermission('settings_cache')) && (
            <section id="cache-settings"><CacheSettings cacheSize={cacheSize} /></section>
          )}
        </TabsContent>
      </Tabs>
      <Toaster />
    </PageTemplate>
  );
}
