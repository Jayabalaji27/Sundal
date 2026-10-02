import React from 'react';
import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, User, Calendar, Timer, DollarSign, MessageSquare, CheckCircle, XCircle } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface TimesheetApproval {
    id: number;
    status: string;
    comments?: string;
    approved_at?: string;
    timesheet: {
        id: number;
        start_date: string;
        end_date: string;
        total_hours: number;
        billable_hours: number;
        notes?: string;
        user: {
            name: string;
            email: string;
        };
        entries: Array<{
            id: number;
            date: string;
            start_time: string;
            end_time: string;
            hours: number;
            description?: string;
            is_billable: boolean;
            project: {
                title: string;
            };
            task?: {
                title: string;
            };
        }>;
    };
    approver?: {
        name: string;
    };
}

interface Props {
    approval: TimesheetApproval;
    userWorkspaceRole?: string;
}

export default function ApprovalDetails({ approval, userWorkspaceRole }: Props) {
    const { t } = useTranslation();

    const getStatusColor = (status: string) => {
        const colors = {
            pending: 'bg-yellow-100 text-yellow-800',
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-800'
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Timesheets'), href: route('timesheets.index') },
        { title: t('Approvals'), href: route('timesheet-approvals.index') },
        { title: t('Details') }
    ];

    return (
        <PageTemplate 
            title={t('Approval Details')} 
            breadcrumbs={breadcrumbs}
        >
            <Head title={t('Approval Details')} />
            
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Button 
                        variant="outline" 
                        onClick={() => router.get(route('timesheet-approvals.index'))}
                    >
                        <ArrowLeft className="h-4 w-4 mr-2" />
                        Back to Approvals
                    </Button>
                    
                    <Badge className={getStatusColor(approval.status)} variant="secondary">
                        {approval.status.charAt(0).toUpperCase() + approval.status.slice(1)}
                    </Badge>
                </div>

                {/* Employee & Period Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>Timesheet Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="flex items-center gap-3">
                                <User className="h-5 w-5 text-gray-400" />
                                <div>
                                    <div className="text-sm text-gray-500">Employee</div>
                                    <div className="font-medium">{approval.timesheet.user.name}</div>
                                    <div className="text-sm text-gray-500">{approval.timesheet.user.email}</div>
                                </div>
                            </div>
                            
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-gray-400" />
                                <div>
                                    <div className="text-sm text-gray-500">Period</div>
                                    <div className="font-medium">
                                        {new Date(approval.timesheet.start_date).toLocaleDateString()} - {new Date(approval.timesheet.end_date).toLocaleDateString()}
                                    </div>
                                </div>
                            </div>
                            
                            <div className="flex items-center gap-3">
                                <Timer className="h-5 w-5 text-blue-500" />
                                <div>
                                    <div className="text-sm text-gray-500">Total Hours</div>
                                    <div className="font-medium text-lg">{approval.timesheet.total_hours}h</div>
                                </div>
                            </div>
                            
                            <div className="flex items-center gap-3">
                                <DollarSign className="h-5 w-5 text-green-500" />
                                <div>
                                    <div className="text-sm text-gray-500">Billable Hours</div>
                                    <div className="font-medium text-lg text-green-600">{approval.timesheet.billable_hours}h</div>
                                </div>
                            </div>
                        </div>

                        {approval.timesheet.notes && (
                            <div className="pt-4 border-t">
                                <div className="text-sm text-gray-500 mb-1">Timesheet Notes</div>
                                <p className="text-gray-700">{approval.timesheet.notes}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Approval Status & Comments */}
                {(approval.comments || approval.approved_at) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {approval.status === 'approved' ? (
                                    <CheckCircle className="h-5 w-5 text-green-600" />
                                ) : approval.status === 'rejected' ? (
                                    <XCircle className="h-5 w-5 text-red-600" />
                                ) : null}
                                Approval Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {approval.approver && (
                                <div>
                                    <div className="text-sm text-gray-500">Reviewed By</div>
                                    <div className="font-medium">{approval.approver.name}</div>
                                </div>
                            )}
                            
                            {approval.approved_at && (
                                <div>
                                    <div className="text-sm text-gray-500">Reviewed At</div>
                                    <div className="font-medium">{new Date(approval.approved_at).toLocaleString()}</div>
                                </div>
                            )}
                            
                            {approval.comments && (
                                <div>
                                    <div className="text-sm text-gray-500 mb-1 flex items-center gap-1">
                                        <MessageSquare className="h-4 w-4" />
                                        Comments
                                    </div>
                                    <div className="bg-gray-50 p-3 rounded-md">
                                        <p className="text-gray-700">{approval.comments}</p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Time Entries */}
                <Card>
                    <CardHeader>
                        <CardTitle>Time Entries ({approval.timesheet.entries.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
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
                                {approval.timesheet.entries.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="font-medium">
                                            {new Date(entry.date).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>{entry.project.title}</TableCell>
                                        <TableCell>{entry.task?.title || '-'}</TableCell>
                                        <TableCell className="text-sm text-gray-600">
                                            {entry.start_time} - {entry.end_time}
                                        </TableCell>
                                        <TableCell className="font-medium">{entry.hours}h</TableCell>
                                        <TableCell>
                                            {entry.is_billable ? (
                                                <Badge className="bg-green-100 text-green-800">Yes</Badge>
                                            ) : (
                                                <Badge variant="secondary">No</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-xs">
                                            <span className="text-sm text-gray-600">{entry.description || '-'}</span>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </PageTemplate>
    );
}
