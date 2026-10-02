import React from 'react';
import { Head, router } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowLeft, Calendar, Clock, User, DollarSign } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface TimesheetEntry {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    hours: number;
    description: string;
    is_billable: boolean;
    project: {
        id: number;
        title: string;
    };
    task?: {
        id: number;
        title: string;
    };
}

interface Timesheet {
    id: number;
    start_date: string;
    end_date: string;
    status: string;
    total_hours: number;
    billable_hours: number;
    notes?: string;
    submitted_at?: string;
    approved_at?: string;
    user: {
        name: string;
        email: string;
    };
    entries: TimesheetEntry[];
    approvals?: Array<{
        id: number;
        status: string;
        comments?: string;
        approved_at?: string;
        approver: {
            name: string;
        };
    }>;
}

interface Props {
    timesheet: Timesheet;
}

export default function Show({ timesheet }: Props) {
    const { t } = useTranslation();

    const getStatusColor = (status: string) => {
        const colors = {
            draft: 'bg-gray-100 text-gray-800',
            submitted: 'bg-blue-100 text-blue-800',
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-800'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Timesheets'), href: route('timesheets.index') },
        { title: `Timesheet #${timesheet.id}` }
    ];

    return (
        <PageTemplate 
            title={`Timesheet #${timesheet.id}`}
            breadcrumbs={breadcrumbs}
        >
            <Head title={`Timesheet #${timesheet.id}`} />

            <div className="space-y-6">
                {/* Back Button */}
                <Button 
                    variant="outline" 
                    onClick={() => router.visit(route('timesheet-approvals.index'))}
                >
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Back to Approvals
                </Button>

                {/* Timesheet Overview */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>Timesheet Details</CardTitle>
                            <Badge className={getStatusColor(timesheet.status)}>
                                {timesheet.status}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <User className="h-4 w-4 text-gray-500" />
                                    <div>
                                        <div className="text-sm text-gray-500">Employee</div>
                                        <div className="font-medium">{timesheet.user.name}</div>
                                        <div className="text-sm text-gray-500">{timesheet.user.email}</div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4 text-gray-500" />
                                    <div>
                                        <div className="text-sm text-gray-500">Period</div>
                                        <div className="font-medium">
                                            {new Date(timesheet.start_date).toLocaleDateString()} - {new Date(timesheet.end_date).toLocaleDateString()}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-gray-500" />
                                    <div>
                                        <div className="text-sm text-gray-500">Total Hours</div>
                                        <div className="font-medium text-lg">{timesheet.total_hours}h</div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <DollarSign className="h-4 w-4 text-gray-500" />
                                    <div>
                                        <div className="text-sm text-gray-500">Billable Hours</div>
                                        <div className="font-medium text-lg text-green-600">{timesheet.billable_hours}h</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {timesheet.notes && (
                            <div className="mt-6 p-4 bg-gray-50 rounded-lg">
                                <div className="text-sm text-gray-500 mb-1">Notes</div>
                                <div className="text-gray-900">{timesheet.notes}</div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Approval History */}
                {timesheet.approvals && timesheet.approvals.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Approval History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {timesheet.approvals.map((approval) => (
                                    <div key={approval.id} className="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                        <Badge className={getStatusColor(approval.status)}>
                                            {approval.status}
                                        </Badge>
                                        <div className="flex-1">
                                            <div className="font-medium">{approval.approver.name}</div>
                                            {approval.comments && (
                                                <div className="text-sm text-gray-600 mt-1">{approval.comments}</div>
                                            )}
                                            {approval.approved_at && (
                                                <div className="text-xs text-gray-500 mt-1">
                                                    {new Date(approval.approved_at).toLocaleString()}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Time Entries */}
                <Card>
                    <CardHeader>
                        <CardTitle>Time Entries ({timesheet.entries.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {timesheet.entries.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Project</TableHead>
                                        <TableHead>Task</TableHead>
                                        <TableHead>Time</TableHead>
                                        <TableHead>Hours</TableHead>
                                        <TableHead>Billable</TableHead>
                                        <TableHead>Description</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {timesheet.entries.map((entry) => (
                                        <TableRow key={entry.id}>
                                            <TableCell>{new Date(entry.date).toLocaleDateString()}</TableCell>
                                            <TableCell>{entry.project.title}</TableCell>
                                            <TableCell>{entry.task?.title || '-'}</TableCell>
                                            <TableCell className="text-sm">
                                                {entry.start_time} - {entry.end_time}
                                            </TableCell>
                                            <TableCell className="font-medium">{entry.hours}h</TableCell>
                                            <TableCell>
                                                <Badge variant={entry.is_billable ? 'default' : 'secondary'}>
                                                    {entry.is_billable ? 'Yes' : 'No'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="max-w-xs truncate">{entry.description || '-'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                No time entries found
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </PageTemplate>
    );
}
