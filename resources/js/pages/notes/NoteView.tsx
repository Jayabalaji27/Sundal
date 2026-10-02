import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from 'react-i18next';
import { StickyNote, User, Users, Calendar, Palette, FileText } from 'lucide-react';

interface NoteViewProps {
    record: any;
    users?: any[];
}

export default function NoteView({ record, users = [] }: NoteViewProps) {
    const { t } = useTranslation();

    const getTypeColor = (type: string) => {
        const colors: Record<string, string> = {
            personal: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
            shared: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
        };
        return colors[type] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const assignedUsers = record.assign_to
        ? record.assign_to.split(',').map((id: string) => {
              const user = users.find((u: any) => u.id === parseInt(id));
              return user ? user.name : null;
          }).filter(Boolean)
        : record.assigned_users?.map((u: any) => u.name) || [];

    return (
        <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-0" onOpenAutoFocus={(e) => e.preventDefault()}>
            <DialogHeader className="px-6 pt-6 pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <StickyNote className="h-5 w-5 text-primary" />
                    </div>
                    <DialogTitle className="text-xl font-semibold">{t('Note Details')}</DialogTitle>
                </div>
            </DialogHeader>

            <div className="px-6 py-4 pb-6 space-y-4">
                {/* Title & Type */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <StickyNote className="h-4 w-4" />
                            {t('Title')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{record.title || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            {record.type === 'shared' ? <Users className="h-4 w-4" /> : <User className="h-4 w-4" />}
                            {t('Type')}
                        </label>
                        <div className="mt-1">
                            <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getTypeColor(record.type)}`}>
                                {record.type === 'shared' ? t('Shared') : t('Personal')}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Creator & Date */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <User className="h-4 w-4" />
                            {t('Created By')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{record.creator?.name || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            {t('Created At')}
                        </label>
                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                            {record.created_at ? (window.appSettings?.formatDateTime(record.created_at, false) || new Date(record.created_at).toLocaleDateString()) : '-'}
                        </p>
                    </div>
                </div>

                {/* Color */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <Palette className="h-4 w-4" />
                            {t('Color')}
                        </label>
                        <div className="mt-1 flex items-center gap-2">
                            <div
                                className="w-6 h-6 rounded border border-gray-200"
                                style={{ backgroundColor: record.color || '#3B82F6' }}
                            />
                            <span className="text-sm font-medium text-gray-900 dark:text-white">{record.color || '-'}</span>
                        </div>
                    </div>

                    {/* Shared Users */}
                    {record.type === 'shared' && assignedUsers.length > 0 && (
                        <div>
                            <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                                <Users className="h-4 w-4" />
                                {t('Shared With')}
                            </label>
                            <div className="flex flex-wrap gap-1 mt-1">
                                {assignedUsers.map((name: string, i: number) => (
                                    <span key={i} className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20">
                                        {name}
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Content */}
                {record.text && (
                    <div>
                        <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            {t('Content')}
                        </label>
                        <div
                            className="mt-1 text-sm text-gray-900 dark:text-white prose prose-sm max-w-none"
                            dangerouslySetInnerHTML={{ __html: record.text }}
                        />
                    </div>
                )}
            </div>
        </DialogContent>
    );
}
