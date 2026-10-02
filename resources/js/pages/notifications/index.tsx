import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Bell, CheckCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { type NotificationItem } from '@/types';

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

export default function NotificationsIndex() {
  const { t } = useTranslation();
  const { notifications } = usePage().props as unknown as { notifications: Paginated<NotificationItem> };

  const openNotification = (notification: NotificationItem) => {
    router.post(route('notifications.read', notification.id), {}, {
      preserveScroll: true,
      onFinish: () => {
        if (notification.link) {
          router.visit(notification.link);
        }
      },
    });
  };

  const markAllAsRead = () => {
    router.post(route('notifications.mark-all-read'), {}, { preserveScroll: true });
  };

  return (
    <PageTemplate
      title={t('Notifications')}
      actions={[
        {
          label: t('Mark all as read'),
          icon: <CheckCheck className="h-4 w-4" />,
          variant: 'outline',
          onClick: markAllAsRead,
        },
      ]}
    >
      {notifications.data.length > 0 ? (
        <div className="divide-y rounded-md border">
          {notifications.data.map((notification) => (
            <div
              key={notification.id}
              className={`p-4 cursor-pointer hover:bg-muted/50 ${notification.is_read ? '' : 'bg-blue-50 dark:bg-blue-950/30'}`}
              onClick={() => openNotification(notification)}
            >
              <h4 className="text-sm font-medium">{notification.title}</h4>
              <p className="text-sm text-muted-foreground">{notification.content}</p>
              <div className="text-xs text-muted-foreground mt-1">
                {new Date(notification.created_at).toLocaleString()}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="p-12 text-center text-muted-foreground">
          <Bell className="h-10 w-10 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
          {t('No notifications yet')}
        </div>
      )}

      {notifications.links.length > 3 && (
        <div className="flex items-center justify-center gap-1 mt-4">
          {notifications.links.map((link, i) => (
            <Button
              key={i}
              variant={link.active ? 'default' : 'outline'}
              size="sm"
              disabled={!link.url}
              onClick={() => link.url && router.visit(link.url)}
              dangerouslySetInnerHTML={{ __html: link.label }}
            />
          ))}
        </div>
      )}
    </PageTemplate>
  );
}
