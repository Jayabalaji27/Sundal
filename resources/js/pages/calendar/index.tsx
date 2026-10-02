import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { Dialog } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import CalendarEventView from './CalendarEventView';

export default function CalendarIndex() {
  const { t } = useTranslation();
  const { events, auth, googleCalendarEnabled } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [calendarView, setCalendarView] = useState('local');
  
  // Filter events based on calendar view
  const filteredEvents = calendarView === 'google' 
    ? events.filter((event: any) => event.is_googlecalendar_sync)
    : events;

  const handleEventClick = (info: any) => {
    info.jsEvent.preventDefault();
    const event = info.event;
    setSelectedEvent({
      title: event.title,
      start: event.start,
      end: event.end,
      type: event.extendedProps.type,
      ...event.extendedProps
    });
    setShowModal(true);
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Calendar') }
  ];

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
      className: 'hover:bg-transparent'
    });
  }

  return (
    <PageTemplate
      title={t('Calendar')}
      breadcrumbs={breadcrumbs}
      actions={pageActions}
    >
      <Card className="p-4">
        <div className="mb-4 flex flex-wrap gap-4 justify-end">
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded" style={{backgroundColor: '#3b82f6'}}></div>
            <span className="text-sm">{t('Zoom Meetings')}</span>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded" style={{backgroundColor: '#10B77F'}}></div>
            <span className="text-sm">{t('Google Meetings')}</span>
          </div>
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded" style={{backgroundColor: '#f59e0b'}}></div>
            <span className="text-sm">{t('Tasks')}</span>
          </div>
        </div>
        <FullCalendar
          plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
          initialView="dayGridMonth"
          headerToolbar={{
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
          }}
          events={filteredEvents}
          eventClick={handleEventClick}
          eventTimeFormat={{
            hour: '2-digit',
            minute: '2-digit',
            meridiem: 'short'
          }}
          height="auto"
          aspectRatio={1.8}
          eventDisplay="block"
          dayMaxEvents={1}
          moreLinkClick="popover"
          eventContent={(eventInfo) => {
            const isNotHeld = eventInfo.event.extendedProps.status === 'not_held';
            return (
              <div className="p-1 overflow-hidden cursor-pointer hover:opacity-80">
                <div className={`font-medium text-xs truncate ${isNotHeld ? 'line-through' : ''}`}>
                  {eventInfo.event.title}
                </div>
                {eventInfo.view.type !== 'dayGridMonth' && eventInfo.event.extendedProps.parent_name && (
                  <div className={`text-xs truncate ${isNotHeld ? 'line-through' : ''}`}>
                    {eventInfo.event.extendedProps.parent_name}
                  </div>
                )}
              </div>
            );
          }}
        />
      </Card>

      {/* Event Details Modal */}
      <Dialog open={showModal} onOpenChange={setShowModal}>
        {selectedEvent && <CalendarEventView event={selectedEvent} />}
      </Dialog>
    </PageTemplate>
  );
}