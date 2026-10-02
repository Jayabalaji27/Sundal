import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshCw, AlertCircle, Save, Info, Copy, Check } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { SettingsSection } from '@/components/settings-section';
import { toast } from '@/components/custom-toast';
import { Card, CardContent } from '@/components/ui/card';

interface GoogleMeetSettingsProps {
  settings: any;
}

export default function GoogleMeetSettings({ settings }: GoogleMeetSettingsProps) {
  const { t } = useTranslation();
  const [isUploading, setIsUploading] = useState(false);
  const [copied, setCopied] = useState(false);
  const { appUrl } = usePage().props as any;

  const { data, setData, post, processing, errors, reset } = useForm({
    google_meet_json_file: null as File | null,
  });

  const redirectUrl = `${appUrl}/oauth`;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setData('google_meet_json_file', file);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsUploading(true);

    post(route('settings.google-meet.update'), {
      preserveScroll: true,
      onSuccess: (page) => {
            // Flash messages handled by useEffect
            setIsUploading(false);
            toast.dismiss();
            const successMessage = page.props.flash?.success;
            const errorMessage = page.props.flash?.error;

            if (successMessage) {
                toast.success(successMessage);
            } else if (errorMessage) {
                toast.error(errorMessage);
            }
        },
      onError: () => {
        setIsUploading(false);
      }
    });
  };

  const handleSync = () => {
    window.location.href = route('settings.google-meet.redirectToGoogle');
  };

  const copyToClipboard = async () => {
    try {
      await navigator.clipboard.writeText(redirectUrl);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
      toast.success(t('URL copied to clipboard successfully!'));
    } catch (err) {
      console.error('Failed to copy: ', err);
      toast.error(t('Failed to copy URL to clipboard'));
    }
  };

  const isConfigured = settings?.google_meet_json_file;
  const hasEmptyTokens = (!settings?.google_meet_token || settings?.google_meet_token === '') &&
                        (!settings?.google_meet_refresh_token || settings?.google_meet_refresh_token === '');

  return (
    <SettingsSection
      title={t('Google Meet Settings')}
      description={t('Configure Google Meet integration for video meetings')}
      action={
        <Button
          type="submit"
          form="google-meet-form"
          disabled={processing || isUploading}
          size="sm"
        >
          <Save className="h-4 w-4 mr-2" />
          {processing || isUploading ? t('Saving...') : t('Save Changes')}
        </Button>
      }
    >
      <Card>
        <CardContent className="space-y-6 mt-6">
        <div className="mt-4 space-y-2">
          <Label>{t('Redirect URL')}</Label>
          <div className="flex items-center gap-2">
            <Input
              value={redirectUrl}
              readOnly
              className="flex-1 bg-gray-50"
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={copyToClipboard}
              className="flex items-center gap-2"
            >
              {copied ? (
                <>
                  <Check className="h-4 w-4" />
                  {t('Copied')}
                </>
              ) : (
                <>
                  <Copy className="h-4 w-4" />
                  {t('Copy Link')}
                </>
              )}
            </Button>
          </div>
        </div>

        <form id="google-meet-form" onSubmit={handleSubmit} className="space-y-4 mt-6">
          <div className="space-y-2">
            <Label htmlFor="google_meet_json_file">
              {t('Google Meet JSON File')}
            </Label>
            <div className="flex items-center gap-2">
              <Input
                id="google_meet_json_file"
                type="file"
                accept=".json"
                onChange={handleFileChange}
                className="flex-1"
              />
            </div>
            {data.google_meet_json_file && (
              <p className="text-sm text-green-600">
                {t('Selected file')}: {data.google_meet_json_file.name}
              </p>
            )}
            <p className="text-xs text-muted-foreground">
              {t('Upload your Google Meet JSON credentials file')}
            </p>
            {errors.google_meet_json_file && (
              <p className="text-sm text-destructive">{errors.google_meet_json_file}</p>
            )}
          </div>
        </form>

        {hasEmptyTokens && isConfigured && (
          <div className="mt-6 p-4 border border-red-200 rounded-lg bg-red-50">
            <div className="flex items-center gap-3">
              <Info className="h-5 w-5 text-red-600 flex-shrink-0" />
              <p className="text-sm text-red-800">
                <span className="font-medium">{t('Info')}: </span>
                {t('You haven\'t authorized your google account to Create Google Meeting. Click ')}
                <span className="font-semibold">Sync</span>
                {t(' button to authorize.')}
              </p>
            </div>
          </div>
        )}

        {isConfigured && hasEmptyTokens && (
          <div className="flex justify-end gap-2 mt-6">
            <Button
              type="button"
              variant="outline"
              onClick={handleSync}
            >
              <RefreshCw className="h-4 w-4 mr-2" />
              {t('Sync')}
            </Button>
          </div>
        )}

        <p className="text-xs text-amber-600 bg-amber-50 p-2 rounded border border-amber-200">
          <strong>{t('Note: ')}</strong>
          {t('While creating JSON credentials, copy the Redirect URL below and add it to the Authorized redirect URIs section.')}
        </p>
        </CardContent>
        </Card>
    </SettingsSection>
  );
}
