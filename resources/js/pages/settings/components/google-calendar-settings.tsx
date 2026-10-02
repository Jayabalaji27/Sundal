import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Calendar, RefreshCw, Save } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { isDemoMode } from '@/utils/cookie-utils';
import { Card, CardContent } from '@/components/ui/card';

interface GoogleCalendarSettingsProps {
  settings?: Record<string, string>;
}

export default function GoogleCalendarSettings({ settings = {} }: GoogleCalendarSettingsProps) {
  const { t } = useTranslation();
  const isDemo = isDemoMode();

  const [formData, setFormData] = useState({
    googleCalendarEnabled: settings.googleCalendarEnabled === '1',
    googleCalendarId: settings.googleCalendarId || '',
  });

  const isGoogleCalendarSyncTested = settings.is_googlecalendar_sync === '1';

  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);

  const handleChange = (name: string, value: any) => {
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file && file.type === 'application/json') {
      setSelectedFile(file);
    } else {
      toast.error(t('Please select a valid JSON file'));
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    const data = new FormData();
    data.append('googleCalendarEnabled', formData.googleCalendarEnabled ? '1' : '0');
    data.append('googleCalendarId', formData.googleCalendarId);

    if (selectedFile) {
      data.append('googleCalendarJson', selectedFile);
    }

    router.post(route('settings.google-calendar.update'), data, {
      preserveScroll: true,
      onSuccess: (page) => {
            // Flash messages handled by useEffect
            setIsLoading(false);
            setSelectedFile(null);
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
        setIsLoading(false);
        const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to update Google Calendar settings');
        toast.error(errorMessage);
      }
    });
  };

  const handleSync = () => {
    setIsSyncing(true);

    router.post(route('settings.google-calendar.sync'), {}, {
      preserveScroll: true,
      onSuccess: () => {
        setIsSyncing(false);
      },
      onError: (errors) => {
        setIsSyncing(false);
        const errorMessage = errors.error || Object.values(errors).join(', ') || t('Sync failed');
        toast.error(errorMessage);
      }
    });
  };

  return (
    <SettingsSection
      title={t('Google Calendar Settings')}
      description={t('Configure Google Calendar integration for automatic task synchronization')}
      action={
        <Button type="submit" form="google-calendar-form" disabled={isLoading} size="sm">
          <Save className="h-4 w-4 mr-2" />
          {isLoading ? t('Saving...') : t('Save Changes')}
        </Button>
      }
    >
      <Card>
        <CardContent className="space-y-6 mt-6">
      <form id="google-calendar-form" onSubmit={handleSubmit} className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="space-y-0.5">
            <Label htmlFor="googleCalendarEnabled" className="flex items-center gap-2">
              <Calendar className="h-4 w-4" />
              {t('Enable Google Calendar Integration')}
            </Label>
            <p className="text-muted-foreground text-sm">
              {t('Automatically sync tasks with Google Calendar')}
            </p>
          </div>
          <Switch
            id="googleCalendarEnabled"
            checked={formData.googleCalendarEnabled}
            onCheckedChange={(checked) => handleChange('googleCalendarEnabled', checked)}
          />
        </div>

        <div className="grid gap-2">
          <Label required={formData.googleCalendarEnabled} htmlFor="googleCalendarId">{t('Google Calendar ID')}</Label>
          <Input
            id="googleCalendarId"
            type="text"
            placeholder={t('Enter your Google Calendar ID (or leave empty for primary)')}
            value={formData.googleCalendarId}
            onChange={(e) => handleChange('googleCalendarId', e.target.value)}
            disabled={!formData.googleCalendarEnabled}
            required={formData.googleCalendarEnabled}
          />
          <p className="text-xs text-muted-foreground">
            {t('Find your Calendar ID in Google Calendar settings. Leave empty to use your primary calendar.')}
          </p>
        </div>

        <div className="grid gap-2">
          <Label required={formData.googleCalendarEnabled} htmlFor="googleCalendarJson">{t('Service Account JSON File')}</Label>
          <Input
            id="googleCalendarJson"
            type="file"
            accept=".json"
            onChange={handleFileSelect}
            disabled={!formData.googleCalendarEnabled}
          />
          {selectedFile && (
            <p className="text-sm text-green-600">
              {t('Selected file')}: {selectedFile.name}
            </p>
          )}
          <p className="text-xs text-muted-foreground">
            {t('Upload your Google Service Account JSON credentials file')}
          </p>
        </div>

        <div className="flex justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={handleSync}
            disabled={isDemo || !formData.googleCalendarEnabled || isSyncing}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${isSyncing ? 'animate-spin' : ''}`} />
            {isSyncing ? t('Syncing...') : t('Test Sync')}
          </Button>
        </div>

      </form>
      </CardContent>
      </Card>
    </SettingsSection>
  );
}
