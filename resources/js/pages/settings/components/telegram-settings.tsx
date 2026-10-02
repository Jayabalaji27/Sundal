import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Send, Save, MessageCircle, HelpCircle } from 'lucide-react';
import { SettingsSection } from '@/components/settings-section';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

interface TelegramSettingsProps {
  settings?: {
    telegram_enabled?: boolean;
    telegram_bot_token?: string;
    telegram_chat_id?: string;
    telegram_notifications?: {
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

export default function TelegramSettings({ settings = {} }: TelegramSettingsProps) {
  const { t } = useTranslation();
  const [isEnabled, setIsEnabled] = useState(settings.telegram_enabled === 1 || settings.telegram_enabled === true);
  const [botToken, setBotToken] = useState(settings.telegram_bot_token ?? '');
  const [chatId, setChatId] = useState(settings.telegram_chat_id ?? '');
  const [notifications, setNotifications] = useState({
    new_project: Boolean(settings.telegram_notifications?.new_project),
    new_task: Boolean(settings.telegram_notifications?.new_task),
    task_stage_updated: Boolean(settings.telegram_notifications?.task_stage_updated),
    new_milestone: Boolean(settings.telegram_notifications?.new_milestone),
    milestone_status_updated: Boolean(settings.telegram_notifications?.milestone_status_updated),
    new_task_comment: Boolean(settings.telegram_notifications?.new_task_comment),
    new_invoice: Boolean(settings.telegram_notifications?.new_invoice),
    invoice_status_updated: Boolean(settings.telegram_notifications?.invoice_status_updated),
    expense_approval: Boolean(settings.telegram_notifications?.expense_approval),
    new_budget: Boolean(settings.telegram_notifications?.new_budget),
  });
  const [isSaving, setIsSaving] = useState(false);
  const [isTesting, setIsTesting] = useState(false);

  // Update state when settings prop changes
  useEffect(() => {
    setIsEnabled(settings.telegram_enabled === 1 || settings.telegram_enabled === true);
    setBotToken(settings.telegram_bot_token ?? '');
    setChatId(settings.telegram_chat_id ?? '');
    
    const notificationSettings = settings.telegram_notifications || {};
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
    
    toast.loading(t("Saving Telegram settings..."));
    
    try {
      await router.post(route('telegram.settings.update'), {
        telegram_enabled: isEnabled,
        telegram_bot_token: botToken,
        telegram_chat_id: chatId,
        telegram_notifications: notifications
      }, {
        preserveState: true,
        onSuccess: () => {
          toast.dismiss();
          toast.success(t("Telegram settings updated successfully"));
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(t("Failed to save Telegram settings"));
          console.error('Telegram settings error:', errors);
        },
        onFinish: () => {
          setIsSaving(false);
        }
      });
    } catch (error) {
      toast.dismiss();
      toast.error(t("Failed to save Telegram settings"));
      setIsSaving(false);
    }
  };

  const handleTest = async () => {
    if (!botToken || !chatId) {
      toast.error(t("Please enter both Bot Token and Chat ID first"));
      return;
    }
    
    setIsTesting(true);
    toast.loading(t("Sending test message..."));
    
    try {
      await router.post(route('telegram.test'), {
        telegram_bot_token: botToken,
        telegram_chat_id: chatId
      }, {
        preserveState: true,
        onSuccess: () => {
          toast.dismiss();
          toast.success(t("Test message sent successfully to Telegram!"));
        },
        onError: (errors) => {
          toast.dismiss();
          toast.error(t("Failed to send test message"));
          console.error('Telegram test error:', errors);
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
      title={t("Telegram Integration")}
      description={t("Configure Telegram bot integration for real-time notifications")}
      action={
        <Button type="submit" form="telegram-settings-form" size="sm">
          <Save className="h-4 w-4 mr-2" />
          {t("Save Changes")}
        </Button>
      }
    >
      <form id="telegram-settings-form" onSubmit={handleSave}>
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Settings */}
          <div className="lg:col-span-2">
            <Card>
              <CardHeader className="pb-3">
                <div className="flex items-center space-x-2">
                  <MessageCircle className="h-5 w-5 text-primary" />
                  <h3 className="text-base font-medium">{t("Integration Settings")}</h3>
                </div>
              </CardHeader>
              <CardContent className="space-y-6">
                {/* Enable Integration */}
                <div className="flex items-center justify-between p-4 border rounded-md">
                  <div>
                    <Label className="font-medium">{t("Enable Telegram Integration")}</Label>
                    <p className="text-xs text-muted-foreground mt-1">{t("Turn on to receive notifications in Telegram")}</p>
                  </div>
                  <Switch
                    checked={isEnabled}
                    onCheckedChange={setIsEnabled}
                  />
                </div>

                {/* Bot Token */}
                <div className="space-y-3">
                  <div className="flex items-center gap-2">
                    <Label className="font-medium">{t("Bot Token")}</Label>
                    <HelpCircle className="h-4 w-4 text-muted-foreground" />
                  </div>
                  <Input
                    type="password"
                    placeholder={t("123456789:ABCdefGHIjklMNOpqrsTUVwxyz")}
                    value={botToken}
                    onChange={(e) => setBotToken(e.target.value)}
                    disabled={!isEnabled}
                    className="font-mono text-sm"
                  />
                  <p className="text-xs text-muted-foreground">
                    {t("Create a bot with @BotFather to get this token")}
                  </p>
                </div>

                {/* Chat ID */}
                <div className="space-y-3">
                  <div className="flex items-center gap-2">
                    <Label className="font-medium">{t("Chat ID")}</Label>
                    <HelpCircle className="h-4 w-4 text-muted-foreground" />
                  </div>
                  <Input
                    placeholder={t("123456789 or -123456789")}
                    value={chatId}
                    onChange={(e) => setChatId(e.target.value)}
                    disabled={!isEnabled}
                    className="font-mono text-sm"
                  />
                  <p className="text-xs text-muted-foreground">
                    {t("Use @userinfobot to get your chat ID")}
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
                    {t("Select which types of notifications to send to Telegram")}
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
                  <h3 className="text-base font-medium">{t("Test Telegram Integration")}</h3>
                </div>
                
                <p className="text-sm text-muted-foreground mb-4">
                  {t("Send a test message to verify your Telegram configuration is working correctly.")}
                </p>

                <Button
                  type="button"
                  onClick={handleTest}
                  disabled={!isEnabled || !botToken || !chatId || isTesting}
                  className="w-full"
                >
                  <Send className="h-4 w-4 mr-2" />
                  {isTesting ? t("Sending...") : t("Send Test Message")}
                </Button>

                <p className="text-xs text-muted-foreground text-center mb-6 mt-4">
                  {t("Enter bot token and chat ID to test the integration")}
                </p>

                {/* Setup Instructions */}
                <div className="p-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg">
                  <h4 className="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">
                    {t("Setup Instructions")}
                  </h4>
                  <ol className="text-xs text-blue-800 dark:text-blue-200 space-y-1 list-decimal list-inside">
                    <li>{t("Message @BotFather on Telegram")}</li>
                    <li>{t("Create a new bot with /newbot")}</li>
                    <li>{t("Copy the bot token")}</li>
                    <li>{t("Get your chat ID from @userinfobot")}</li>
                    <li>{t("Enter both values above")}</li>
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