import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useState } from 'react';
import { Save, Mail, Send, Server, Lock, User, AlertCircle } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from 'react-i18next';
import { router, usePage } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';

export default function EmailSettings() {
  const { t } = useTranslation();
  const { settings = {} } = usePage().props as any;
  
  // Email Settings form state
  const [emailSettings, setEmailSettings] = useState({
    provider: settings.email_provider || 'smtp',
    driver: settings.email_driver || 'smtp',
    host: settings.email_host || 'smtp.example.com',
    port: settings.email_port || '587',
    username: settings.email_username || 'user@example.com',
    password: settings.email_password ? '••••••••••••' : '',
    encryption: settings.email_encryption || 'tls',
    fromAddress: settings.email_from_address || 'noreply@example.com',
    fromName: settings.email_from_name || ''
  });

  // Server validation errors from saving, keyed by form field (the keys match emailSettings)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  // Test email state
  const [testEmail, setTestEmail] = useState('');
  const [testEmailError, setTestEmailError] = useState<string | null>(null);
  const [isSending, setIsSending] = useState(false);
  const [testResult, setTestResult] = useState<{success: boolean, message: string} | null>(null);

  const EMAIL_FORMAT = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  // The form pre-fills these as visible placeholder-looking values (see
  // useState above) rather than leaving the fields empty, so a user who
  // never touched the SMTP config can still click "Send Test Email" and
  // get a confusing raw connection-failure message. Catch that case with
  // a clear, actionable message instead.
  const PLACEHOLDER_HOSTS = ['smtp.example.com', ''];

  // Handle email settings form changes
  const handleEmailSettingsChange = (field: string, value: string) => {
    setEmailSettings(prev => ({
      ...prev,
      [field]: value
    }));
    // The placeholder-host warning under the test-email button names this
    // field as the cause — once it's edited, that warning is stale even
    // though the test-email input itself hasn't changed.
    if (field === 'host') setTestEmailError(null);
    if (fieldErrors[field]) {
      setFieldErrors(prev => {
        const rest = { ...prev };
        delete rest[field];
        return rest;
      });
    }
  };

  const errorClass = (field: string) => (fieldErrors[field] ? 'border-red-500' : '');
  const renderFieldError = (field: string) =>
    fieldErrors[field] ? <p className="text-xs text-red-600">{fieldErrors[field]}</p> : null;

  // Handle email settings form submission
  const submitEmailSettings = (e: React.FormEvent) => {
    e.preventDefault();
    
    toast.loading(t('Saving email settings...'));
    setFieldErrors({});

    router.post(route('settings.email.update'), emailSettings, {
      preserveScroll: true,
      onSuccess: () => {
        // The server's flash ("Email settings updated successfully") is shown by the
        // global FlashMessages component, so don't toast a second confirmation here.
        toast.dismiss();
        setTestEmailError(null);
      },
      onError: (errors) => {
        toast.dismiss();
        const { error: generalError, ...perField } = errors;
        setFieldErrors(perField);

        const invalidFields = Object.keys(perField);
        if (invalidFields.length > 0) {
          toast.error(t('Please fix the highlighted fields.'));
          // Focus the first invalid field in on-screen order (emailSettings' key order matches the form).
          const firstInvalid = Object.keys(emailSettings).find(f => invalidFields.includes(f));
          const el = firstInvalid ? document.getElementById(firstInvalid) : null;
          el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          el?.focus({ preventScroll: true });
        } else {
          toast.error(generalError || t('Failed to save email settings'));
        }
      }
    });
  };

  // Handle test email submission
  const sendTestEmail = (e: React.FormEvent) => {
    e.preventDefault();
    if (!testEmail) return;

    setTestEmailError(null);
    if (!EMAIL_FORMAT.test(testEmail)) {
      setTestEmailError(t('Enter a valid email address (e.g. name@example.com).'));
      return;
    }
    if (PLACEHOLDER_HOSTS.includes(emailSettings.host.trim())) {
      setTestEmailError(t('SMTP Host is still the example value. Enter your mail server (e.g. smtp.gmail.com) in the highlighted SMTP Host field, click Save Changes, then send the test.'));
      // Point at the field itself: on wide screens the form is beside this panel, not above it.
      setFieldErrors(prev => ({ ...prev, host: t('Enter your mail server, e.g. smtp.gmail.com') }));
      const hostInput = document.getElementById('host');
      hostInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      hostInput?.focus({ preventScroll: true });
      return;
    }

    setIsSending(true);
    setTestResult(null);
    toast.loading(t('Sending test email...'));
    
    router.post(route('settings.email.test'), { email: testEmail }, {
      preserveScroll: true,
      onSuccess: () => {
        // Failures come back through onError, so reaching here means it was sent.
        // The toast is the server's flash, shown by the global FlashMessages component.
        setIsSending(false);
        toast.dismiss();
        setTestResult({ success: true, message: t('Test email sent successfully to {{email}}', { email: testEmail }) });

        // Reset result after 5 seconds
        setTimeout(() => {
          setTestResult(null);
        }, 5000);
      },
      onError: (errors) => {
        setIsSending(false);
        toast.dismiss();
        const errorMessage = errors.error || errors.email || Object.values(errors).join(', ') || t('Failed to send test email');
        toast.error(errorMessage);
        setTestResult({ success: false, message: errorMessage });
        
        // Reset result after 5 seconds
        setTimeout(() => {
          setTestResult(null);
        }, 5000);
      }
    });
  };

  return (
    <SettingsSection
      title={t("Email Settings")}
      description={t("Configure email server settings for system notifications and communications")}
      action={
        <Button type="submit" form="email-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Email Settings */}
        <div className="lg:col-span-2">
          <form id="email-settings-form" onSubmit={submitEmailSettings}>
            <Card>
              <CardContent className="pt-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <Mail className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="provider" className="font-medium">{t("Email Provider")}</Label>
                    </div>
                    <Select 
                      value={emailSettings.provider} 
                      onValueChange={(value) => {
                        handleEmailSettingsChange('provider', value);
                        // Set default values based on provider
                        if (value === 'smtp') {
                          handleEmailSettingsChange('driver', 'smtp');
                        } else if (value === 'mailgun') {
                          handleEmailSettingsChange('driver', 'mailgun');
                        } else if (value === 'ses') {
                          handleEmailSettingsChange('driver', 'ses');
                        }
                      }}
                    >
                      <SelectTrigger id="provider" className={errorClass('provider')}>
                        <SelectValue placeholder="Select provider" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="smtp">SMTP</SelectItem>
                        <SelectItem value="mailgun">Mailgun</SelectItem>
                        <SelectItem value="ses">Amazon SES</SelectItem>
                        <SelectItem value="sendmail">Sendmail</SelectItem>
                      </SelectContent>
                    </Select>
                    {renderFieldError('provider')}
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <Server className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="driver" className="font-medium">{t("Mail Driver")}</Label>
                    </div>
                    <Input
                      id="driver"
                      value={emailSettings.driver}
                      onChange={(e) => handleEmailSettingsChange('driver', e.target.value)}
                      placeholder="smtp"
                      className={errorClass('driver')}
                    />
                    {renderFieldError('driver')}
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <Server className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="host" className="font-medium">{t("SMTP Host")}</Label>
                    </div>
                    <Input
                      id="host"
                      value={emailSettings.host}
                      onChange={(e) => handleEmailSettingsChange('host', e.target.value)}
                      placeholder="smtp.example.com"
                      className={errorClass('host')}
                    />
                    {renderFieldError('host')}
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <Server className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="port" className="font-medium">{t("SMTP Port")}</Label>
                      <TooltipProvider>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <AlertCircle className="h-4 w-4 text-muted-foreground" />
                          </TooltipTrigger>
                          <TooltipContent>
                            <p>{t("Common ports: 25, 465, 587, 2525")}</p>
                          </TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                    </div>
                    <Input
                      id="port"
                      value={emailSettings.port}
                      onChange={(e) => handleEmailSettingsChange('port', e.target.value)}
                      placeholder="587"
                      className={errorClass('port')}
                    />
                    {renderFieldError('port')}
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <User className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="username" className="font-medium">{t("SMTP Username")}</Label>
                    </div>
                    <Input
                      id="username"
                      value={emailSettings.username}
                      onChange={(e) => handleEmailSettingsChange('username', e.target.value)}
                      placeholder="user@example.com"
                      className={errorClass('username')}
                    />
                    {renderFieldError('username')}
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <Lock className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="password" className="font-medium">{t("SMTP Password")}</Label>
                    </div>
                    <Input
                      id="password"
                      type="password"
                      value={emailSettings.password}
                      onChange={(e) => handleEmailSettingsChange('password', e.target.value)}
                      placeholder="••••••••••••"
                      className={errorClass('password')}
                    />
                    {renderFieldError('password')}
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <Lock className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="encryption" className="font-medium">{t("Mail Encryption")}</Label>
                    </div>
                    <Select 
                      value={emailSettings.encryption} 
                      onValueChange={(value) => handleEmailSettingsChange('encryption', value)}
                    >
                      <SelectTrigger id="encryption" className={errorClass('encryption')}>
                        <SelectValue placeholder="Select encryption" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="tls">TLS</SelectItem>
                        <SelectItem value="ssl">SSL</SelectItem>
                        <SelectItem value="none">None</SelectItem>
                      </SelectContent>
                    </Select>
                    {renderFieldError('encryption')}
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <Mail className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="fromAddress" className="font-medium">{t("From Address")}</Label>
                    </div>
                    <Input
                      id="fromAddress"
                      value={emailSettings.fromAddress}
                      onChange={(e) => handleEmailSettingsChange('fromAddress', e.target.value)}
                      placeholder="noreply@example.com"
                      className={errorClass('fromAddress')}
                    />
                    {renderFieldError('fromAddress')}
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2">
                      <User className="h-4 w-4 text-muted-foreground" />
                      <Label htmlFor="fromName" className="font-medium">{t("From Name")}</Label>
                    </div>
                    <Input
                      id="fromName"
                      value={emailSettings.fromName}
                      onChange={(e) => handleEmailSettingsChange('fromName', e.target.value)}
                      placeholder="System"
                      className={errorClass('fromName')}
                    />
                    {renderFieldError('fromName')}
                  </div>
                </div>
              </CardContent>
            </Card>
          </form>
        </div>

        {/* Test Email Section */}
        <div className="lg:col-span-1">
          <Card>
            <CardContent className="pt-6">
              <form onSubmit={sendTestEmail} noValidate className="space-y-4">
                <div className="flex items-center gap-2 mb-4">
                  <Send className="h-4 w-4 text-primary" />
                  <h3 className="text-base font-medium">{t("Test Email Configuration")}</h3>
                </div>
                
                <div className="space-y-1.5">
                  <Label htmlFor="testEmail" className="font-medium">{t("Send Test To")}</Label>
                  <Input
                    id="testEmail"
                    type="email"
                    value={testEmail}
                    onChange={(e) => { setTestEmail(e.target.value); setTestEmailError(null); }}
                    placeholder="test@example.com"
                    className={testEmailError ? 'border-red-500' : ''}
                    required
                  />
                  {testEmailError ? (
                    <p className="text-xs text-red-600">{testEmailError}</p>
                  ) : (
                    <p className="text-xs text-muted-foreground">
                      {t("Enter an email address to send a test message")}
                    </p>
                  )}
                </div>

                {testResult && (
                  <Alert variant={testResult.success ? "success" : "destructive"} className="py-2">
                    <AlertDescription>
                      {testResult.message}
                    </AlertDescription>
                  </Alert>
                )}

                <Button 
                  type="submit" 
                  className="w-full"
                  disabled={isSending || !testEmail}
                >
                  {isSending ? (
                    <>
                      <span className="animate-spin mr-2">◌</span>
                      {t("Sending...")}
                    </>
                  ) : (
                    <>
                      <Send className="h-4 w-4 mr-2" />
                      {t("Send Test Email")}
                    </>
                  )}
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </SettingsSection>
  );
}