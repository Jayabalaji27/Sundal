import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Copy, ArrowLeft, Edit, Trash2, Calendar, Clock, Users } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
// import GoogleMeetingModal from './GoogleMeetingModal';

export default function GoogleMeetingShow() {
    const { t } = useTranslation();
    const { meeting, projects, members, permissions, flash, googleCalendarEnabled } = usePage().props as any;

    const formatText = (text: string) => {
        return text.replace(/_/g, ' ').split(' ').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    };

    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const copyToClipboard = (text: string, type: string) => {
        navigator.clipboard.writeText(text);
        toast.success(`${type} copied to clipboard`);
    };

    const handleDeleteConfirm = () => {
        router.delete(route('google-meetings.destroy', meeting.id), {
            onSuccess: () => {
                router.visit(route('google-meetings.index'));
            }
        });
    };

    const getStatusColor = (status: string) => {
        const colors = {
            scheduled: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            started: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            ended: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
            cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
        };
        return colors[status as keyof typeof colors] || colors.scheduled;
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Google Meetings'), href: route('google-meetings.index') },
        { title: meeting.title }
    ];

    const pageActions = [];

    // Add Edit button if user has update permission
    if (permissions?.google_meeting_update) {
        pageActions.push({
            label: t('Edit'),
            icon: <Edit className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => setIsEditModalOpen(true)
        });
    }

    // Add Delete button if user has delete permission
    if (permissions?.google_meeting_delete) {
        pageActions.push({
            label: t('Delete'),
            icon: <Trash2 className="h-4 w-4 mr-2" />,
            variant: 'destructive',
            onClick: () => setIsDeleteModalOpen(true)
        });
    }

    pageActions.push({
        label: t('Back'),
        icon: <ArrowLeft className="h-4 w-4 mr-2" />,
        variant: 'outline',
        onClick: () => router.get(route('google-meetings.index'))
    });


    return (
        <PageTemplate
            title={meeting.title}
            url={`/google-meetings/${meeting.id}`}
            breadcrumbs={breadcrumbs}
            actions={pageActions}
        >
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main Content */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Meeting Details */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className='text-lg'>{t('Meeting Details')}</CardTitle>
                                <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(meeting.status)}`}>
                                    {formatText(meeting.status)}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {meeting.description && (
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-gray-500">{t('Description')}</p>
                                    <p className="text-sm text-gray-700 mt-1">{meeting.description}</p>
                                </div>
                            )}

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="flex items-center space-x-2">
                                    <Calendar className="h-4 w-4 text-gray-400" />
                                    <div>
                                        <p className="text-xs text-gray-500">{t('Start Time')}</p>
                                        <p className="text-sm font-medium text-gray-900">{meeting.start_time ? window.appSettings.formatDateTime(new Date(meeting.start_time), true) : ''}</p>
                                    </div>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Calendar className="h-4 w-4 text-gray-400" />
                                    <div>
                                        <p className="text-xs text-gray-500">{t('End Time')}</p>
                                        <p className="text-sm font-medium text-gray-900">{meeting.end_time ? window.appSettings.formatDateTime(new Date(meeting.end_time), true) : ''}</p>
                                    </div>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Clock className="h-4 w-4 text-gray-400" />
                                    <div>
                                        <p className="text-xs text-gray-500">{t('Duration')}</p>
                                        <p className="text-sm font-medium text-gray-900">{meeting.duration} {t('minutes')}</p>
                                    </div>
                                </div>
                            </div>

                            {meeting.project && (
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-gray-500">{t('Project')}</p>
                                    <p className="text-sm text-gray-700 mt-1">{meeting.project.title}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Meeting URLs */}
                    {(meeting.join_url || meeting.start_url) && (
                        <Card>
                            <CardHeader>
                                <CardTitle className='text-lg'>{t('Meeting URLs')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {meeting.join_url && (
                                    <div>
                                        <p className="text-xs font-medium tracking-wide text-gray-500">{t('Join URL')}</p>
                                        <div className="flex items-center space-x-2 mt-1">
                                            <Input
                                                value={meeting.join_url}
                                                readOnly
                                                className="flex-1"
                                            />
                                            <Button
                                                size="icon"
                                                variant="outline"
                                                onClick={() => copyToClipboard(meeting.join_url, 'Join URL')}
                                                className="text-blue-600 border-blue-200 hover:bg-blue-50"
                                                title={t('Copy Join URL')}
                                            >
                                                <Copy className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {meeting.start_url && (
                                    <div>
                                        <p className="text-xs font-medium tracking-wide text-gray-500">{t('Start URL')}</p>
                                        <div className="flex items-center space-x-2 mt-1">
                                            <Input
                                                value={meeting.start_url}
                                                readOnly
                                                className="flex-1"
                                            />
                                            <Button
                                                size="icon"
                                                variant="outline"
                                                onClick={() => copyToClipboard(meeting.start_url, 'Start URL')}
                                                className="text-green-600 border-green-200 hover:bg-green-50"
                                                title={t('Copy Start URL')}
                                            >
                                                <Copy className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Host Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className='text-lg'>{t('Host')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center space-x-3">
                                <Avatar className="h-10 w-10 rounded-full">
                                    <AvatarImage src={meeting.user?.avatar} />
                                    <AvatarFallback className="text-sm font-semibold bg-indigo-100 text-indigo-700">
                                        {meeting.user?.name?.charAt(0)?.toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{meeting.user?.name || t('Unknown')}</p>
                                    <p className="text-xs text-gray-500">{meeting.user?.email || ''}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Members */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center text-lg">
                                <Users className="h-4 w-4 mr-2" />
                                {t('Members')} ({meeting.members?.length || 0})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {meeting.members && meeting.members.length > 0 ? (
                                meeting.members.map((member: any) => (
                                    <div key={member.id} className="flex items-center space-x-2">
                                        <Avatar className="h-6 w-6">
                                            <AvatarImage src={member.avatar} />
                                            <AvatarFallback className="text-xs font-semibold bg-indigo-100 text-indigo-700">
                                                {member.name?.charAt(0)?.toUpperCase() || 'U'}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{member.name}</p>
                                            <p className="text-xs text-gray-500">{member.email}</p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-gray-500">{t('No members assigned')}</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Edit Modal */}
            {/* <GoogleMeetingModal
                isOpen={isEditModalOpen}
                onClose={() => setIsEditModalOpen(false)}
                meeting={meeting}
                projects={projects || []}
                members={members || []}
                googleCalendarEnabled={googleCalendarEnabled}
            /> */}

            {/* Delete Modal */}
            <CrudDeleteModal
                isOpen={isDeleteModalOpen}
                onClose={() => setIsDeleteModalOpen(false)}
                onConfirm={handleDeleteConfirm}
                itemName={meeting?.title || ''}
                entityName={t('meeting')}
                warningMessage={t('This meeting will be permanently deleted.')}
            />
        </PageTemplate>
    );
}
