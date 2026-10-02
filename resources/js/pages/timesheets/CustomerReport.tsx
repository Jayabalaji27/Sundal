import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    FileText, Users, Calendar, Clock, DollarSign, CheckCircle, 
    AlertCircle, Activity, Target, StickyNote, TrendingUp, 
    BarChart3, Download, Search
} from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { formatCurrency } from '@/utils/currency';
import { hasPermission } from '@/utils/authorization';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

interface Project {
    id: number;
    title: string;
}

interface User {
    id: number;
    name: string;
}

interface Props {
    projects: Project[];
    members: User[];
}

export default function CustomerReport({ projects, members }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const userPermissions = auth?.permissions || [];
    const [filters, setFilters] = useState({
        project_id: '',
        start_date: '',
        end_date: '',
        member_id: ''
    });
    const [reportData, setReportData] = useState<any>(null);
    const [isLoading, setIsLoading] = useState(false);

    const handleGenerateReport = async () => {
        if (!filters.project_id) return;

        setIsLoading(true);
        try {
            // Empty-string date/member filters (the initial state before a user
            // touches those fields) fail the backend's `nullable|date` rule - Laravel's
            // "nullable" only skips validation for an actual null, not ''. Send null
            // instead so generating a report right after picking just a project works.
            const payload = Object.fromEntries(
                Object.entries(filters).map(([key, value]) => [key, value === '' ? null : value])
            );
            const response = await fetch(route('customer-reports.generate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!response.ok) {
                const firstError = data?.errors ? (Object.values(data.errors)[0] as string[])?.[0] : null;
                toast.error(firstError || data?.message || t('Failed to generate report.'));
                return;
            }
            setReportData(data);
        } catch (error) {
            console.error('Failed to generate report:', error);
            toast.error(t('Failed to generate report.'));
        } finally {
            setIsLoading(false);
        }
    };

    const breadcrumbs = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Reports', href: route('timesheet-reports.index') },
        { title: 'Customer Report' }
    ];

    return (
        <PageTemplate title="Customer Report" breadcrumbs={breadcrumbs}>
            <Head title="Customer Report" />
            
            {/* Filters */}
            <Card className="mb-6">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Search className="h-5 w-5" />
                        Report Filters
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div className="space-y-2">
                            <Label>Project <span className="text-red-500">*</span></Label>
                            <Select 
                                value={filters.project_id} 
                                onValueChange={(value) => setFilters(prev => ({ ...prev, project_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select project" />
                                </SelectTrigger>
                                <SelectContent>
                                    {projects.map(project => (
                                        <SelectItem key={project.id} value={project.id.toString()}>
                                            {project.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label>Start Date</Label>
                            <Input
                                type="date"
                                value={filters.start_date}
                                onChange={(e) => setFilters(prev => ({ ...prev, start_date: e.target.value }))}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>End Date</Label>
                            <Input
                                type="date"
                                value={filters.end_date}
                                onChange={(e) => setFilters(prev => ({ ...prev, end_date: e.target.value }))}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Team Member</Label>
                            <Select 
                                value={filters.member_id} 
                                onValueChange={(value) => setFilters(prev => ({ ...prev, member_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All members" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All Members</SelectItem>
                                    {members.map(member => (
                                        <SelectItem key={member.id} value={member.id.toString()}>
                                            {member.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    
                    <div className="mt-4">
                        <Button 
                            onClick={handleGenerateReport}
                            disabled={isLoading || !filters.project_id}
                            className="w-full md:w-auto"
                        >
                            {isLoading ? 'Generating...' : 'Generate Report'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Report Results */}
            {reportData && (
                <div className="space-y-6">
                    {/* Project Overview */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Project Overview
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <h3 className="font-semibold text-lg mb-2">{reportData.project.title}</h3>
                                    <p className="text-gray-600 mb-4">{reportData.project.description}</p>
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Status:</span>
                                            <Badge variant={reportData.project.status === 'completed' ? 'default' : 'secondary'}>
                                                {reportData.project.status}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Priority:</span>
                                            <Badge variant="outline">{reportData.project.priority}</Badge>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Progress:</span>
                                            <span className="font-medium">{reportData.project.progress}%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 className="font-semibold mb-3">Timeline</h4>
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Start Date:</span>
                                            <span className="text-sm">{reportData.project.start_date || 'Not set'}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Deadline:</span>
                                            <span className="text-sm">{reportData.project.deadline || 'Not set'}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">Estimated Hours:</span>
                                            <span className="text-sm">{reportData.project.estimated_hours || 0}h</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 className="font-semibold mb-3">Summary</h4>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="text-center p-3 bg-blue-50 rounded-lg">
                                            <div className="text-2xl font-bold text-blue-600">
                                                {reportData.summary.timesheet.total_hours.toFixed(1)}h
                                            </div>
                                            <div className="text-xs text-blue-600">Total Hours</div>
                                        </div>
                                        <div className="text-center p-3 bg-green-50 rounded-lg">
                                            <div className="text-2xl font-bold text-green-600">
                                                {reportData.summary.tasks.completed_tasks}/{reportData.summary.tasks.total_tasks}
                                            </div>
                                            <div className="text-xs text-green-600">Tasks Done</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Detailed Tabs */}
                    <Tabs defaultValue="tasks" className="w-full">
                        <TabsList className="flex h-auto w-full flex-wrap gap-1">
                            <TabsTrigger value="tasks">Tasks</TabsTrigger>
                            <TabsTrigger value="team">Team</TabsTrigger>
                            <TabsTrigger value="milestones">Milestones</TabsTrigger>
                            <TabsTrigger value="notes">Notes</TabsTrigger>
                            <TabsTrigger value="activity">Activity</TabsTrigger>
                            <TabsTrigger value="budget">Budget</TabsTrigger>
                            <TabsTrigger value="timesheet">Timesheet</TabsTrigger>
                        </TabsList>

                        <TabsContent value="tasks">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CheckCircle className="h-5 w-5" />
                                        Tasks ({reportData.project.tasks?.length || 0})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {reportData.project.tasks?.length > 0 ? (
                                        <div className="space-y-3">
                                            {reportData.project.tasks.map((task: any) => (
                                                <div key={task.id} className="border rounded-lg p-4">
                                                    <div className="flex justify-between items-start mb-2">
                                                        <h4 className="font-semibold">{task.title}</h4>
                                                        <Badge variant={task.stage?.name === 'Done' ? 'default' : 'secondary'}>
                                                            {task.stage?.name || 'No Stage'}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-gray-600 mb-2">{task.description}</p>
                                                    <div className="flex justify-between text-xs text-gray-500">
                                                        <span>Priority: {task.priority}</span>
                                                        <span>Due: {task.due_date || 'Not set'}</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <CheckCircle className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500">No tasks found</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="team">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Users className="h-5 w-5" />
                                        Team Members ({reportData.project.users?.length || 0})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {reportData.project.users?.length > 0 ? (
                                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            {reportData.project.users.map((user: any) => (
                                                <div key={user.id} className="border rounded-lg p-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                                                            <Users className="h-5 w-5 text-white" />
                                                        </div>
                                                        <div>
                                                            <h4 className="font-semibold">{user.name}</h4>
                                                            <p className="text-sm text-gray-600">{user.pivot?.role || 'Member'}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <Users className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500">No team members assigned</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="milestones">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Target className="h-5 w-5" />
                                        Milestones ({reportData.project.milestones?.length || 0})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {reportData.project.milestones?.length > 0 ? (
                                        <div className="space-y-4">
                                            {reportData.project.milestones.map((milestone: any) => (
                                                <div key={milestone.id} className="border rounded-lg p-4">
                                                    <div className="flex justify-between items-start mb-2">
                                                        <h4 className="font-semibold">{milestone.title}</h4>
                                                        <Badge variant={milestone.status === 'completed' ? 'default' : 'secondary'}>
                                                            {milestone.status}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-gray-600 mb-2">{milestone.description}</p>
                                                    <div className="flex justify-between items-center">
                                                        <span className="text-sm text-gray-500">
                                                            Due: {milestone.due_date || 'Not set'}
                                                        </span>
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-20 bg-gray-200 rounded-full h-2">
                                                                <div 
                                                                    className="bg-blue-600 h-2 rounded-full" 
                                                                    style={{ width: `${milestone.progress}%` }}
                                                                ></div>
                                                            </div>
                                                            <span className="text-sm">{milestone.progress}%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <Target className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500">No milestones found</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="notes">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <StickyNote className="h-5 w-5" />
                                        Notes ({reportData.project.notes?.length || 0})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {reportData.project.notes?.length > 0 ? (
                                        <div className="space-y-4">
                                            {reportData.project.notes.map((note: any) => (
                                                <div key={note.id} className="border rounded-lg p-4">
                                                    <div className="flex justify-between items-start mb-2">
                                                        <h4 className="font-semibold">{note.title}</h4>
                                                        {note.is_pinned && <Badge variant="outline">Pinned</Badge>}
                                                    </div>
                                                    <p className="text-sm text-gray-600 mb-2">{note.content}</p>
                                                    <div className="text-xs text-gray-500">
                                                        By {note.creator?.name} • {new Date(note.created_at).toLocaleDateString()}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <StickyNote className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500">No notes found</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="activity">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Activity className="h-5 w-5" />
                                        Recent Activity ({reportData.project.activities?.length || 0})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {reportData.project.activities?.length > 0 ? (
                                        <div className="space-y-3">
                                            {reportData.project.activities.map((activity: any) => (
                                                <div key={activity.id} className="flex items-start gap-3 p-3 border rounded-lg">
                                                    <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                        <Activity className="h-4 w-4 text-blue-600" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <p className="text-sm">{activity.description}</p>
                                                        <div className="text-xs text-gray-500 mt-1">
                                                            {activity.user?.name} • {new Date(activity.created_at).toLocaleString()}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <Activity className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500">No recent activity</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="budget">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <DollarSign className="h-5 w-5" />
                                        Budget Overview
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {reportData.summary.budget ? (
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div className="text-center p-4 bg-blue-50 rounded-lg">
                                                    <div className="text-2xl font-bold text-blue-600">
                                                        {formatCurrency(reportData.summary.budget.total_budget)}
                                                    </div>
                                                    <div className="text-sm text-blue-600">Total Budget</div>
                                                </div>
                                                <div className="text-center p-4 bg-red-50 rounded-lg">
                                                    <div className="text-2xl font-bold text-red-600">
                                                        {formatCurrency(reportData.summary.budget.total_spent)}
                                                    </div>
                                                    <div className="text-sm text-red-600">Total Spent</div>
                                                </div>
                                                <div className="text-center p-4 bg-green-50 rounded-lg">
                                                    <div className="text-2xl font-bold text-green-600">
                                                        {formatCurrency(reportData.summary.budget.remaining)}
                                                    </div>
                                                    <div className="text-sm text-green-600">Remaining</div>
                                                </div>
                                            </div>
                                            <div className="mt-4">
                                                <div className="flex justify-between mb-2">
                                                    <span className="text-sm font-medium">Budget Utilization</span>
                                                    <span className="text-sm">{reportData.summary.budget.utilization.toFixed(1)}%</span>
                                                </div>
                                                <div className="w-full bg-gray-200 rounded-full h-3">
                                                    <div 
                                                        className="bg-blue-600 h-3 rounded-full" 
                                                        style={{ width: `${Math.min(reportData.summary.budget.utilization, 100)}%` }}
                                                    ></div>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <DollarSign className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500">No budget information available</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="timesheet">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Clock className="h-5 w-5" />
                                        Timesheet Entries ({reportData.project.timesheet_entries?.length || 0})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {reportData.project.timesheet_entries?.length > 0 ? (
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                                <div className="text-center p-4 bg-blue-50 rounded-lg">
                                                    <div className="text-2xl font-bold text-blue-600">
                                                        {reportData.summary.timesheet.total_hours.toFixed(1)}h
                                                    </div>
                                                    <div className="text-sm text-blue-600">Total Hours</div>
                                                </div>
                                                <div className="text-center p-4 bg-green-50 rounded-lg">
                                                    <div className="text-2xl font-bold text-green-600">
                                                        {reportData.summary.timesheet.billable_hours.toFixed(1)}h
                                                    </div>
                                                    <div className="text-sm text-green-600">Billable Hours</div>
                                                </div>
                                                <div className="text-center p-4 bg-purple-50 rounded-lg">
                                                    <div className="text-2xl font-bold text-purple-600">
                                                        {formatCurrency(reportData.summary.timesheet.total_amount)}
                                                    </div>
                                                    <div className="text-sm text-purple-600">Total Amount</div>
                                                </div>
                                            </div>
                                            
                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-gray-200">
                                                    <thead className="bg-gray-50">
                                                        <tr>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hours</th>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Billable</th>
                                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="bg-white divide-y divide-gray-200">
                                                        {reportData.project.timesheet_entries.map((entry: any) => (
                                                            <tr key={entry.id}>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    {new Date(entry.date).toLocaleDateString()}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    {entry.user?.name}
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    {parseFloat(entry.hours).toFixed(1)}h
                                                                </td>
                                                                <td className="px-6 py-4 whitespace-nowrap">
                                                                    <Badge variant={entry.is_billable ? 'default' : 'secondary'}>
                                                                        {entry.is_billable ? 'Yes' : 'No'}
                                                                    </Badge>
                                                                </td>
                                                                <td className="px-6 py-4 text-sm text-gray-900">
                                                                    {entry.description || '-'}
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <Clock className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500">No timesheet entries found</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>
            )}

            {!reportData && !isLoading && (
                <Card>
                    <CardContent className="p-8 text-center">
                        <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <p className="text-gray-500">Select a project and generate report to view comprehensive details</p>
                        <p className="text-sm text-gray-400 mt-2">All project data including tasks, team, milestones, notes, activity, budget, and timesheet will be displayed</p>
                    </CardContent>
                </Card>
            )}

            {isLoading && (
                <Card>
                    <CardContent className="p-8 text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p className="text-gray-500">Generating comprehensive report...</p>
                    </CardContent>
                </Card>
            )}
        </PageTemplate>
    );
}