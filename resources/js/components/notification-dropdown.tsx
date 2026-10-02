import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuSeparator
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  Bell,
  MessageSquare,
  CheckCircle2,
  Clock,
  Briefcase,
  FileText,
  Calendar,
  CheckCheck
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { type SharedData } from '@/types';

const notificationTypeIcons: Record<string, React.ReactNode> = {
  chat_message: <MessageSquare className="h-4 w-4" />,
  comment: <MessageSquare className="h-4 w-4" />,
  task: <CheckCircle2 className="h-4 w-4" />,
  reminder: <Clock className="h-4 w-4" />,
  project: <Briefcase className="h-4 w-4" />,
  invoice: <FileText className="h-4 w-4" />,
  message: <MessageSquare className="h-4 w-4" />,
  deadline: <Calendar className="h-4 w-4" />,
  file: <FileText className="h-4 w-4" />
};

const notificationTypeColors: Record<string, string> = {
  chat_message: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
  comment: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
  task: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
  reminder: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
  project: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
  invoice: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
  message: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
  deadline: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  file: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
};

export function NotificationDropdown() {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const { props } = usePage<SharedData>();
  const notifications = props.recentNotifications ?? [];
  const unreadCount = props.unreadNotificationsCount ?? 0;

  // Track whether any OTHER Inertia visit (a form submit, a page navigation)
  // is currently in flight, so this poll never overlaps one. Overlapping visits
  // to the same URL both carry the globally-shared `errors` prop in their
  // response (Inertia includes shared props on every visit regardless of an
  // `only` filter) - if this poll's response (always errors: {}) resolves
  // after a form's error-carrying response, it silently wipes out validation
  // errors the user was supposed to see (no toast, no visible failure at all).
  const visitInFlightRef = useRef(false);
  useEffect(() => {
    const stop1 = router.on('start', () => { visitInFlightRef.current = true; });
    const stop2 = router.on('finish', () => { visitInFlightRef.current = false; });
    return () => { stop1(); stop2(); };
  }, []);

  // Keep the badge/list in sync while the app is open, mirroring the polling
  // pattern already used for chat's conversation list (resources/js/pages/chat/index.tsx).
  useEffect(() => {
    const interval = setInterval(() => {
      // Skip while any modal is open (e.g. Create/Edit Project). Even though
      // this reload is scoped with `only` and passes preserveState: true,
      // the resulting Inertia page-props update still re-renders the current
      // page component tree, and any open form modal's local React state
      // (whatever the user has typed so far) gets wiped when that happens -
      // reported as "the create-project form resets" (it reproduces with
      // zero interaction, just waiting ~15s with any modal open). Skipping
      // the poll while a dialog is open avoids disrupting in-progress input;
      // it resumes on the next tick once the modal closes.
      if (document.querySelector('[role="dialog"][data-state="open"]')) {
        return;
      }
      // Skip while another visit (e.g. a form submit) is in flight - see the
      // visitInFlightRef comment above for why this race matters.
      if (visitInFlightRef.current) {
        return;
      }
      router.reload({ only: ['unreadNotificationsCount', 'recentNotifications'], preserveScroll: true, preserveState: true });
    }, 15000);
    return () => clearInterval(interval);
  }, []);

  const formatTimeAgo = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) {
      return t('just now');
    } else if (diffInSeconds < 3600) {
      const minutes = Math.floor(diffInSeconds / 60);
      return t('{{count}} min ago', { count: minutes });
    } else if (diffInSeconds < 86400) {
      const hours = Math.floor(diffInSeconds / 3600);
      return t('{{count}} hours ago', { count: hours });
    } else {
      const days = Math.floor(diffInSeconds / 86400);
      return t('{{count}} days ago', { count: days });
    }
  };

  const markAllAsRead = () => {
    router.post(route('notifications.mark-all-read'), {}, {
      preserveScroll: true,
      only: ['unreadNotificationsCount', 'recentNotifications'],
    });
  };

  const openNotification = (notification: NonNullable<SharedData['recentNotifications']>[number]) => {
    router.post(route('notifications.read', notification.id), {}, {
      preserveScroll: true,
      onFinish: () => {
        setOpen(false);
        if (notification.link) {
          router.visit(notification.link);
        }
      },
    });
  };

  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <Badge 
              className="absolute -top-1 -right-1 h-5 min-w-5 flex items-center justify-center p-0 text-xs"
              variant="destructive"
            >
              {unreadCount}
            </Badge>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <div className="flex items-center justify-between p-4">
          <h3 className="font-medium">{t('Notifications')}</h3>
          {unreadCount > 0 && (
            <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={markAllAsRead}>
              <CheckCheck className="h-3.5 w-3.5 mr-1" />
              {t('Mark all as read')}
            </Button>
          )}
        </div>
        
        <ScrollArea className="h-80">
          {notifications.length > 0 ? (
            <div className="divide-y">
              {notifications.map((notification) => (
                <div
                  key={notification.id}
                  className={`p-3 flex items-start gap-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 ${notification.is_read ? '' : 'bg-blue-50 dark:bg-blue-950/30'}`}
                  onClick={() => openNotification(notification)}
                >
                  <div className={`rounded-full p-2 ${notificationTypeColors[notification.type ?? ''] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'}`}>
                    {notificationTypeIcons[notification.type ?? ''] ?? <Bell className="h-4 w-4" />}
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <h4 className="text-sm font-medium">{notification.title}</h4>
                        <p className="text-xs text-muted-foreground">{notification.content}</p>
                        <div className="text-xs text-muted-foreground mt-1">
                          {formatTimeAgo(notification.created_at)}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="p-4 text-center">
              <Bell className="h-8 w-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" />
              <p className="text-sm text-muted-foreground">{t('No notifications')}</p>
            </div>
          )}
        </ScrollArea>

        <DropdownMenuSeparator />
        <DropdownMenuItem
          className="cursor-pointer p-3 flex items-center justify-center"
          onClick={() => {
            router.visit(route('notifications.index'));
            setOpen(false);
          }}
        >
          <span className="text-sm font-medium">{t('View all notifications')}</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}