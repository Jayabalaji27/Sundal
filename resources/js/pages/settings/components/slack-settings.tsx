import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Send, Save, MessageSquare, HelpCircle } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

interface SlackSettingsProps {
  settings?: {
    slack_enabled?: boolean;
    slack_webhook_url?: string;
    slack_notifications?: {
      new_project?: boolean;
      new_task?: boolean;
      task_stage_updated?: boolean;
      new_milestone?: boolean;
      milestone_status_updated?: boolean;
      new_task_comment?: boolean;
      new_invoice?: boolean;
      invoice_status_updated?: boolean;
      expense_approval?: boolean;
      new_budget?: boolean;
    };
  };
}

export default function SlackSettings({ settings = {} }: SlackSettingsProps) {
  const { t } = useTranslation();
  const [isEnabled, setIsEnabled] = useState(Boolean(settings.slack_enabled));
  const [webhookUrl, setWebhookUrl] = useState(settings.slack_webhook_url ?? '');
  const [notifications, setNotifications] = useState({
    new_project: Boolean(settings.slack_notifications?.new_project),
    new_task: Boolean(settings.slack_notifications?.new_task),
    task_stage_updated: Boolean(settings.slack_notifications?.task_stage_updated),
    new_milestone: Boolean(settings.slack_notifications?.new_milestone),
    milestone_status_updated: Boolean(settings.slack_notifications?.milestone_status_updated),
    new_task_comment: Boolean(settings.slack_notifications?.new_task_comment),
    new_invoice: Boolean(settings.slack_notifications?.new_invoice),
    invoice_status_updated: Boolean(settings.slack_notifications?.invoice_status_updated),
    expense_approval: Boolean(settings.slack_notifications?.expense_approval),
    new_budget: Boolean(settings.slack_notifications?.new_budget),
  });
  const [isSaving, setIsSaving] = useState(false);
  const [isTesting, setIsTesting] = useState(false);

  // Update state when settings prop changes
  useEffect(() => {
    setIsEnabled(Boolean(settings.slack_enabled));
    setWebhookUrl(settings.slack_webhook_url ?? '');
    
    const notificationSettings = settings.slack_notifications || {};
    setNotifications({
      new_project: Boolean(notificationSettings.new_project),
      new_task: Boolean(notificationSettings.new_task),
      task_stage_updated: Boolean(notificationSettings.task_stage_updated),
      new_milestone: Boolean(notificationSettings.new_milestone),
      milestone_status_updated: Boolean(notificationSettings.milestone_status_updated),
      new_task_comment: Boolean(notificationSettings.new_task_comment),
      new_invoice: Boolean(notificationSettings.new_invoice),
      invoice_status_updated: Boolean(notificationSettings.invoice_status_updated),
      expense_approval: Boolean(notificationSettings.expense_approval),
      new_budget: Boolean(notificationSettings.new_budget),
    });
  }, [settings]);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    
    toast.loading(t("Saving Slack settings..."));
    
    try {
      await router.post(route('slack.settings.update'), {
        slack_enabled: isEnabled,
        slack_webhook_url: webhookUrl,
        slack_notifications: notifications
      }, {
        preserveState: true,
        onSuccess: () => {
          toast.dismiss();
          toast.success(t("Slack settings updated successfully"));
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(t("Failed to save Slack settings"));
          console.error('Slack settings error:', errors);
        },
        onFinish: () => {
          setIsSaving(false);
        }
      });
    } catch (error) {
      toast.dismiss();
      toast.error(t("Failed to save Slack settings"));
      setIsSaving(false);
    }
  };

  const handleTest = async () => {
    if (!webhookUrl) {
      toast.error(t("Please enter a webhook URL first"));
      return;
    }
    
    setIsTesting(true);
    toast.loading(t("Sending test message..."));
    
    try {
      await router.post(route('slack.test-webhook'), {
        webhook_url: webhookUrl,
        debug: false
      }, {
        preserveState: true,
        onSuccess: () => {
          toast.dismiss();
          toast.success(t("Test message sent successfully to Slack!"));
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(t("Failed to send test message"));
          console.error('Slack test error:', errors);
        },
        onFinish: () => {
          setIsTesting(false);
        }
      });
    } catch (error) {
      toast.dismiss();
      toast.error(t("Failed to send test message"));
      setIsTesting(false);
    }
  };



  const handleNotificationChange = (key: string, value: boolean) => {
    setNotifications(prev => ({
      ...prev,
      [key]: value
    }));
  };

  return (
    <SettingsSection
      title={t("Slack Integration")}
      description={t("Configure Slack webhook integration for real-time notifications")}
      action={
        <Button type="submit" form="slack-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <form id="slack-settings-form" onSubmit={handleSave}>
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Settings */}
          <div className="lg:col-span-2">
            <Card>
              <CardHeader className="pb-3">
                <div className="flex items-center space-x-2">
                  <MessageSquare className="h-5 w-5 text-primary" />
                  <h3 className="text-base font-medium">{t("Integration Settings")}</h3>
                </div>
              </CardHeader>
              <CardContent className="space-y-6">
                {/* Enable Integration */}
                <div className="flex items-center justify-between p-4 border rounded-md">
                  <div>
                    <Label className="font-medium">{t("Enable Slack Integration")}</Label>
                    <p className="text-xs text-muted-foreground mt-1">{t("Turn on to receive notifications in Slack")}</p>
                  </div>
                  <Switch
                    checked={isEnabled}
                    onCheckedChange={setIsEnabled}
                  />
                </div>

                {/* Webhook URL */}
                <div className="space-y-3">
                  <div className="flex items-center gap-2">
                    <Label className="font-medium">{t("Webhook URL")}</Label>
                    <HelpCircle className="h-4 w-4 text-muted-foreground" />
                  </div>
                  <Input
                    type="url"
                    placeholder={t("https://hooks.slack.com/services/...")}
                    value={webhookUrl}
                    onChange={(e) => setWebhookUrl(e.target.value)}
                    disabled={!isEnabled}
                    className="font-mono text-sm"
                  />
                  <p className="text-xs text-muted-foreground">
                    {t("Create a Slack app and add an Incoming Webhook to get this URL")}
                  </p>
                </div>

                {/* Notification Types */}
                <div className="space-y-4">
                  <Label className="font-medium">{t("Notification Types")}</Label>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("New Project")}</span>
                      <Switch
                        checked={notifications.new_project}
                        onCheckedChange={(value) => handleNotificationChange('new_project', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("New Task")}</span>
                      <Switch
                        checked={notifications.new_task}
                        onCheckedChange={(value) => handleNotificationChange('new_task', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("Task Stage Updated")}</span>
                      <Switch
                        checked={notifications.task_stage_updated}
                        onCheckedChange={(value) => handleNotificationChange('task_stage_updated', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("New Milestone")}</span>
                      <Switch
                        checked={notifications.new_milestone}
                        onCheckedChange={(value) => handleNotificationChange('new_milestone', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("Milestone Status Updated")}</span>
                      <Switch
                        checked={notifications.milestone_status_updated}
                        onCheckedChange={(value) => handleNotificationChange('milestone_status_updated', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("New Task Comment")}</span>
                      <Switch
                        checked={notifications.new_task_comment}
                        onCheckedChange={(value) => handleNotificationChange('new_task_comment', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("New Invoice")}</span>
                      <Switch
                        checked={notifications.new_invoice}
                        onCheckedChange={(value) => handleNotificationChange('new_invoice', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("Invoice Status Updated")}</span>
                      <Switch
                        checked={notifications.invoice_status_updated}
                        onCheckedChange={(value) => handleNotificationChange('invoice_status_updated', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("Expense Approval")}</span>
                      <Switch
                        checked={notifications.expense_approval}
                        onCheckedChange={(value) => handleNotificationChange('expense_approval', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                    
                    <div className="flex items-center justify-between">
                      <span className="text-sm">{t("New Budget")}</span>
                      <Switch
                        checked={notifications.new_budget}
                        onCheckedChange={(value) => handleNotificationChange('new_budget', value)}
                        disabled={!isEnabled}
                      />
                    </div>
                  </div>
                  <p className="text-xs text-muted-foreground">
                    {t("Select which types of notifications to send to Slack")}
                  </p>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Test & Instructions */}
          <div className="space-y-6">
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center gap-2 mb-4">
                  <Send className="h-4 w-4 text-primary" />
                  <h3 className="text-base font-medium">{t("Test Slack Integration")}</h3>
                </div>
                
                <p className="text-sm text-muted-foreground mb-4">
                  {t("Send a test message to verify your Slack configuration is working correctly.")}
                </p>

                <Button
                  type="button"
                  onClick={handleTest}
                  disabled={!isEnabled || !webhookUrl || isTesting}
                  className="w-full"
                >
                  <Send className="h-4 w-4 mr-2" />
                  {isTesting ? t("Sending...") : t("Send Test Message")}
                </Button>

                <p className="text-xs text-muted-foreground text-center mb-6 mt-4">
                  {t("Enter a webhook URL to test the integration")}
                </p>

                {/* Setup Instructions */}
                <div className="p-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg">
                  <h4 className="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">
                    {t("Setup Instructions")}
                  </h4>
                  <ol className="text-xs text-blue-800 dark:text-blue-200 space-y-1 list-decimal list-inside">
                    <li>{t("Go to your Slack workspace")}</li>
                    <li>{t("Create a new Slack app")}</li>
                    <li>{t("Enable Incoming Webhooks")}</li>
                    <li>{t("Add webhook to workspace")}</li>
                    <li>{t("Copy the webhook URL here")}</li>
                  </ol>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </form>
    </SettingsSection>
  );
}