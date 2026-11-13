import { PageTemplate } from '@/components/page-template';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useMemo, useRef, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import type { DatesSetArg, DayHeaderContentArg, EventContentArg } from '@fullcalendar/core';
import { AlertCircle, CheckCircle2, ChevronLeft, ChevronRight, Circle, Video } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import CalendarEventView from './CalendarEventView';
import './calendar.css';

// Event kinds sent by CalendarController, with the colour used for the chip and legend.
const EVENT_TYPES = [
  { type: 'meeting', label: 'Zoom Meetings', color: '#3b82f6' },
  { type: 'google_meeting', label: 'Google Meetings', color: '#10b981' },
  { type: 'task', label: 'Tasks', color: '#f59e0b' },
] as const;

// Local calendar day as YYYY-MM-DD, to compare with a task's due_day.
const todayKey = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const nextDay = (day: string) => {
  const [y, m, d] = day.split('-').map(Number);
  const n = new Date(y, m - 1, d + 1);
  return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`;
};

const taskStatus = (e: any): 'done' | 'overdue' | 'open' =>
  e.is_completed ? 'done' : e.due_day && e.due_day < todayKey() ? 'overdue' : 'open';

const VIEWS = [
  { id: 'dayGridMonth', label: 'Month' },
  { id: 'timeGridWeek', label: 'Week' },
  { id: 'timeGridDay', label: 'Day' },
] as const;

export default function CalendarIndex() {
  const { t } = useTranslation();
  const { events, googleCalendarEnabled } = usePage().props as any;
  const calendarRef = useRef<FullCalendar>(null);
  const [selectedEvent, setSelectedEvent] = useState<any>(null);
  const [showModal, setShowModal] = useState(false);
  const [calendarView, setCalendarView] = useState('local');
  const [title, setTitle] = useState('');
  const [view, setView] = useState<string>('dayGridMonth');
  const [hiddenTypes, setHiddenTypes] = useState<string[]>([]);
  // Tasks sit on their due date by default; "range" draws start-to-due bars.
  const [taskMode, setTaskMode] = useState<'due' | 'range'>('due');

  const api = () => calendarRef.current?.getApi();

  // Google filter first, then the legend toggles. The server's solid colours are
  // dropped so the chips can use the softer tinted style from calendar.css.
  const sourceEvents: any[] = useMemo(
    () => (calendarView === 'google' ? events.filter((e: any) => e.is_googlecalendar_sync) : events) ?? [],
    [events, calendarView],
  );
  const visibleEvents = useMemo(
    () =>
      sourceEvents
        .filter((e) => !hiddenTypes.includes(e.type))
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        .map(({ backgroundColor, borderColor, ...e }) => {
          const isTask = e.type === 'task';
          const status = isTask ? taskStatus(e) : null;
          const onDueDay = isTask && taskMode === 'due' && e.due_day;
          return {
            ...e,
            ...(onDueDay ? { start: e.due_day, end: nextDay(e.due_day) } : {}),
            status_key: status,
            // Meetings first in a day, then open and overdue tasks, done tasks last.
            sortOrder: isTask ? (status === 'done' ? 3 : status === 'overdue' ? 1 : 2) : 0,
            classNames: ['sd-event', `sd-event--${e.type}`, status ? `sd-event--${status}` : ''],
          };
        }),
    [sourceEvents, hiddenTypes, taskMode],
  );
  const countByType = (type: string) => sourceEvents.filter((e) => e.type === type).length;

  const toggleType = (type: string) =>
    setHiddenTypes((prev) => (prev.includes(type) ? prev.filter((x) => x !== type) : [...prev, type]));

  const handleEventClick = (info: any) => {
    info.jsEvent.preventDefault();
    const event = info.event;
    setSelectedEvent({
      title: event.title,
      start: event.start,
      end: event.end,
      type: event.extendedProps.type,
      ...event.extendedProps,
    });
    setShowModal(true);
  };

  const handleDatesSet = (arg: DatesSetArg) => {
    setTitle(arg.view.title);
    setView(arg.view.type);
  };

  // Week and day views: small weekday over a large date, today in a brand circle.
  const renderDayHeader = (arg: DayHeaderContentArg) => {
    const weekday = arg.date.toLocaleDateString(undefined, { weekday: 'short' });
    if (arg.view.type === 'dayGridMonth') return <span>{weekday}</span>;
    return (
      <div className="flex flex-col items-center gap-1 py-1">
        <span>{weekday}</span>
        <span className={cn('sd-cal-date', arg.isToday && 'sd-cal-date--today')}>{arg.date.getDate()}</span>
      </div>
    );
  };

  const statusLabel = { done: t('Done'), overdue: t('Overdue'), open: t('Open') } as const;

  const renderEvent = (arg: EventContentArg) => {
    const props = arg.event.extendedProps;
    const isTask = props.type === 'task';
    const status = props.status_key as 'done' | 'overdue' | 'open' | null;
    const isNotHeld = props.status === 'not_held';
    const parent = props.parent_name;
    const isTimeGrid = arg.view.type !== 'dayGridMonth';
    const StatusIcon = !isTask ? Video : status === 'done' ? CheckCircle2 : status === 'overdue' ? AlertCircle : Circle;
    const tooltip = [
      arg.event.title,
      parent,
      isTask ? `${props.stage ?? ''}${status === 'overdue' ? ` · ${t('Overdue')}` : ''}` : arg.timeText,
      isTask && props.due_day ? `${t('Due')} ${new Date(props.due_day + 'T00:00').toLocaleDateString()}` : '',
    ].filter(Boolean).join('\n');
    return (
      <div className={cn('sd-event-body', (isNotHeld || status === 'done') && 'sd-event-body--struck')} title={tooltip}>
        <div className="flex min-w-0 items-center gap-1.5">
          <StatusIcon className="sd-event-icon" aria-label={isTask && status ? statusLabel[status] : t('Meeting')} />
          {arg.timeText && !isTimeGrid && <span className="sd-event-time">{arg.timeText}</span>}
          <span className="truncate font-medium">{arg.event.title}</span>
        </div>
        {isTimeGrid && arg.timeText && <div className="sd-event-time">{arg.timeText}</div>}
        {isTimeGrid && parent && <div className="truncate opacity-80">{parent}</div>}
      </div>
    );
  };

  const breadcrumbs = [{ title: t('Dashboard'), href: route('dashboard') }, { title: t('Calendar') }];

  const pageActions = [];
  if (googleCalendarEnabled) {
    pageActions.push({
      label: '',
      icon: (
        <Select value={calendarView} onValueChange={setCalendarView}>
          <SelectTrigger className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="local">{t('Local Calendar')}</SelectItem>
            <SelectItem value="google">{t('Google Calendar')}</SelectItem>
          </SelectContent>
        </Select>
      ),
      variant: 'ghost' as const,
      onClick: () => {},
      className: 'hover:bg-transparent',
    });
  }

  return (
    <PageTemplate title={t('Calendar')} breadcrumbs={breadcrumbs} actions={pageActions} noPadding>
      <div className="sd-calendar overflow-hidden rounded-xl border border-ds-border bg-ds-surface shadow-ds-xs">
        {/* Toolbar: period and navigation on the left, legend filters and view switch on the right. */}
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ds-border-subtle px-4 py-3 sm:px-5">
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={() => api()?.today()}>
              {t('Today')}
            </Button>
            <div className="flex items-center">
              <Button variant="ghost" size="icon" className="size-8" onClick={() => api()?.prev()} aria-label={t('Previous')}>
                <ChevronLeft className="size-4" />
              </Button>
              <Button variant="ghost" size="icon" className="size-8" onClick={() => api()?.next()} aria-label={t('Next')}>
                <ChevronRight className="size-4" />
              </Button>
            </div>
            <h2 className="text-lg font-semibold tracking-tight text-ds-fg">{title}</h2>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <div className="flex flex-wrap items-center gap-1.5" role="group" aria-label={t('Show event types')}>
              {EVENT_TYPES.map(({ type, label, color }) => {
                const on = !hiddenTypes.includes(type);
                return (
                  <button
                    key={type}
                    type="button"
                    onClick={() => toggleType(type)}
                    aria-pressed={on}
                    className={cn(
                      'inline-flex h-8 cursor-pointer items-center gap-2 rounded-full border px-3 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus',
                      on
                        ? 'border-ds-border bg-ds-surface text-ds-fg hover:bg-ds-surface-hover'
                        : 'border-dashed border-ds-border text-ds-fg-muted hover:text-ds-fg',
                    )}
                  >
                    <span className="size-2 rounded-full" style={{ backgroundColor: on ? color : 'var(--ds-border-strong)' }} />
                    {t(label)}
                    <span className="tabular-nums text-ds-fg-muted">{countByType(type)}</span>
                  </button>
                );
              })}
            </div>

            <div className="inline-flex rounded-lg bg-ds-surface-sunken p-0.5" role="tablist" aria-label={t('Calendar view')}>
              {VIEWS.map(({ id, label }) => (
                <button
                  key={id}
                  type="button"
                  role="tab"
                  aria-selected={view === id}
                  onClick={() => api()?.changeView(id)}
                  className={cn(
                    'h-7 cursor-pointer rounded-md px-3 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus',
                    view === id ? 'bg-ds-surface text-ds-fg shadow-ds-xs' : 'text-ds-fg-muted hover:text-ds-fg',
                  )}
                >
                  {t(label)}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Key for the task status icons, and how tasks are placed on the grid. */}
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-ds-border-subtle bg-ds-surface-subtle px-4 py-2 text-xs text-ds-fg-secondary sm:px-5">
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span className="inline-flex items-center gap-1.5"><Video className="size-3.5 text-[#3b82f6]" />{t('Meeting')}</span>
            <span className="inline-flex items-center gap-1.5"><Circle className="size-3.5 text-[#d97706]" />{t('Task due')}</span>
            <span className="inline-flex items-center gap-1.5"><AlertCircle className="size-3.5 text-[#dc2626]" />{t('Overdue')}</span>
            <span className="inline-flex items-center gap-1.5"><CheckCircle2 className="size-3.5 text-ds-fg-muted" />{t('Done')}</span>
          </div>
          <div className="flex items-center gap-2">
            <span>{t('Show tasks')}</span>
            <div className="inline-flex rounded-md bg-ds-surface-sunken p-0.5" role="radiogroup" aria-label={t('Show tasks')}>
              {([['due', t('On due date')], ['range', t('Start to due')]] as const).map(([mode, label]) => (
                <button
                  key={mode}
                  type="button"
                  role="radio"
                  aria-checked={taskMode === mode}
                  onClick={() => setTaskMode(mode)}
                  className={cn(
                    'h-6 cursor-pointer rounded px-2.5 font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus',
                    taskMode === mode ? 'bg-ds-surface text-ds-fg shadow-ds-xs' : 'text-ds-fg-muted hover:text-ds-fg',
                  )}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>
        </div>

        <FullCalendar
          ref={calendarRef}
          eventOrder="sortOrder,start,title"
          plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
          initialView="dayGridMonth"
          headerToolbar={false}
          datesSet={handleDatesSet}
          events={visibleEvents}
          eventClick={handleEventClick}
          eventContent={renderEvent}
          dayHeaderContent={renderDayHeader}
          eventTimeFormat={{ hour: 'numeric', minute: '2-digit', meridiem: 'short', omitZeroMinute: true }}
          slotLabelFormat={{ hour: 'numeric', meridiem: 'short' }}
          // Month grows with its rows; week and day scroll inside a fixed height so they open at 8 am.
          height={view.startsWith('timeGrid') ? 760 : 'auto'}
          eventDisplay="block"
          dayMaxEvents={3}
          moreLinkClick="popover"
          nowIndicator
          scrollTime="08:00:00"
          allDayText={t('All day')}
          fixedWeekCount={false}
        />
      </div>

      <Dialog open={showModal} onOpenChange={setShowModal}>
        {selectedEvent && <CalendarEventView event={selectedEvent} />}
      </Dialog>
    </PageTemplate>
  );
}
