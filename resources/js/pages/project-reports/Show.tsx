import React, { useState, useEffect, Fragment, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Download, Search, Filter, ArrowLeft } from 'lucide-react';
import { usePdfDownload } from '@/hooks/usePdfDownload';
import { PageTemplate } from '@/components/page-template';
import { hasPermission } from '@/utils/authorization';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tooltip, TooltipTrigger, TooltipContent } from '@/components/ui/tooltip';
import { PieChart, Pie, Cell, BarChart, Bar, LineChart, Line, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip as ChartTooltip, ResponsiveContainer } from 'recharts';

interface Project {
    id: number;
    name: string;
    title: string;
    start_date: string;
    end_date: string;
    status: string;
    description?: string;
    members: Array<{
        id: number;
        name: string;
        avatar?: string;
    }>;
    clients: Array<{
        id: number;
        name: string;
        avatar?: string;
    }>;
    milestones: Array<{
        id: number;
        title: string;
        status: string;
        progress: number;
        cost: number;
        start_date: string;
        end_date: string;
    }>;
}

interface Stats {
    total_tasks: number;
    completed_tasks: number;
    completion_percentage: number;
    total_milestones: number;
    completed_milestones: number;
    milestone_completion_percentage: number;
    total_logged_hours: number;
    estimated_hours: number;
    priority_stats: Record<string, number>;
    status_stats: Record<string, number>;
    days_left: number | null;
}

interface Props {
    project: Project;
    stats: Stats;
    userStats: any[];
    users: any[];
    stages: any[];
    workspace: any;
    tasks?: any;
    filters?: any;
}

export default function Show({ project, stats, userStats, users, stages, workspace, tasks: initialTasks, filters: pageFilters = {} }: Props) {
    const { t } = useTranslation();
    const reportRef = useRef<HTMLDivElement>(null);
    const { downloadPDF, isGeneratingPDF } = usePdfDownload();
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];

    const formatText = (text: string) => {
        return text.replace(/_/g, ' ').split(' ').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    };
    const [tasks, setTasks] = useState<any[]>(initialTasks?.data || []);
    const [pagination, setPagination] = useState<any>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');
    const [selectedUser, setSelectedUser] = useState(pageFilters.user_id || 'all');
    const [selectedMilestone, setSelectedMilestone] = useState(pageFilters.milestone_id || 'all');
    const [selectedStatus, setSelectedStatus] = useState(pageFilters.status || 'all');
    const [selectedPriority, setSelectedPriority] = useState(pageFilters.priority || 'all');
    const [showFilters, setShowFilters] = useState(false);
    const [perPage, setPerPage] = useState(pageFilters.per_page || 10);
    const [currentPage, setCurrentPage] = useState(1);

    useEffect(() => {
        if (initialTasks?.data && initialTasks.data.length > 0) {
            setTasks(initialTasks.data);
        } else {
            fetchTasks();
        }
    }, []);

    // Debounced search effect
    useEffect(() => {
        const timeoutId = setTimeout(() => {
            if (searchTerm || selectedUser !== 'all' || selectedMilestone !== 'all' || selectedStatus !== 'all' || selectedPriority !== 'all') {
                fetchTasks();
            }
        }, 300); // 300ms debounce

        return () => clearTimeout(timeoutId);
    }, [searchTerm]);

    // Immediate effect for filters (no debounce needed)
    useEffect(() => {
        setCurrentPage(1);
        fetchTasks();
    }, [selectedUser, selectedMilestone, selectedStatus, selectedPriority, perPage]);

    const fetchTasks = async () => {
        try {
            setLoading(true);
            setError(null);

            const params = {
                search: searchTerm || undefined,
                user_id: selectedUser !== 'all' ? selectedUser : undefined,
                milestone_id: selectedMilestone !== 'all' ? selectedMilestone : undefined,
                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                priority: selectedPriority !== 'all' ? selectedPriority : undefined,
                per_page: perPage,
                page: currentPage
            };

            // Filter out undefined values
            const filteredParams = Object.fromEntries(
                Object.entries(params).filter(([_, value]) => value !== undefined)
            );

            const response = await fetch(route('project-reports.tasks', project.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(filteredParams),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            setTasks(data.data || []);
            setPagination(data.pagination || null);
        } catch (error) {
            console.error('Error fetching tasks:', error);
            setError('Failed to load tasks. Please try again.');
            setTasks([]);
        } finally {
            setLoading(false);
        }
    };

    const handleExport = () => {
        window.open(route('project-reports.export', project.id), '_blank');
    };

    const formatDate = (dateString: string) => {
        if (!dateString) return '-';
        return window.appSettings.formatDateTime(new Date(dateString),false);
    };

    const getStatusColor = (status: string) => {
        const colors = {
            'active': 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            'planning': 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            'on_hold': 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            'completed': 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
            'cancelled': 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            'to do': 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
            'todo': 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
            'in progress': 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            'inprogress': 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
            'review': 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20',
            'done': 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            'blocked': 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
            'testing': 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20'
        };
        return colors[status?.toLowerCase() as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const getPriorityColor = (priority: string) => {
        const colors = {
            low: '#10B77F',
            medium: '#f59e0b',
            high: '#f97316',
            critical: '#ef4444'
        };
        return colors[priority as keyof typeof colors] || '#6b7280';
    };

    const getPriorityBadgeColor = (priority: string) => {
        const colors = {
            low: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20',
            medium: 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20',
            high: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20',
            critical: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'
        };
        return colors[priority as keyof typeof colors] || 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20';
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        fetchTasks();
    };

    const hasActiveFilters = () => {
        return selectedUser !== 'all' || selectedMilestone !== 'all' || selectedStatus !== 'all' || selectedPriority !== 'all' || searchTerm !== '';
    };

    const activeFilterCount = () => {
        return (selectedUser !== 'all' ? 1 : 0) +
               (selectedMilestone !== 'all' ? 1 : 0) +
               (selectedStatus !== 'all' ? 1 : 0) +
               (selectedPriority !== 'all' ? 1 : 0) +
               (searchTerm ? 1 : 0);
    };

    const handleResetFilters = () => {
        setSelectedUser('all');
        setSelectedMilestone('all');
        setSelectedStatus('all');
        setSelectedPriority('all');
        setSearchTerm('');
        setShowFilters(false);
        setCurrentPage(1);
    };

    const handlePageChange = (page: number) => {
        setCurrentPage(page);
        fetchTasksForPage(page);
    };

    const fetchTasksForPage = async (page: number) => {
        try {
            setLoading(true);
            setError(null);

            const params = {
                search: searchTerm || undefined,
                user_id: selectedUser !== 'all' ? selectedUser : undefined,
                milestone_id: selectedMilestone !== 'all' ? selectedMilestone : undefined,
                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                priority: selectedPriority !== 'all' ? selectedPriority : undefined,
                per_page: perPage,
                page: page
            };

            const filteredParams = Object.fromEntries(
                Object.entries(params).filter(([_, value]) => value !== undefined)
            );

            const response = await fetch(route('project-reports.tasks', project.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(filteredParams),
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            setTasks(data.data || []);
            setPagination(data.pagination || null);
        } catch (error) {
            console.error('Error fetching tasks:', error);
            setError('Failed to load tasks. Please try again.');
            setTasks([]);
        } finally {
            setLoading(false);
        }
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Project Report'), href: route('project-reports.index') },
        { title: project.title || project.name }
    ];

    const pageActions = [
        ...(hasPermission(permissions, 'project_report_export') ? [{
            label: t('Download PDF'),
            icon: <Download className="h-4 w-4 mr-2" />,
            variant: 'default' as const,
            onClick: handleExport
        }] : []),
        {
            label: t('Back'),
            icon: <ArrowLeft className="h-4 w-4 mr-2" />,
            variant: 'outline',
            onClick: () => router.get(route('project-reports.index'))
        }
    ];

    // Prepare chart data
    const priorityOrder = ['critical', 'high', 'medium', 'low'];
    const priorityData = priorityOrder.map(priority => ({
        name: priority,
        value: stats.priority_stats?.[priority] || 0,
        fill: getPriorityColor(priority)
    }));

    const statusData = Object.entries(stats.status_stats || {}).map(([key, value]) => ({
        name: key,
        value: value,
        fill: key.includes('Progress') ? '#3b82f6' : key.includes('Done') ? '#10B77F' : key.includes('Review') ? '#8b5cf6' : key.includes('Blocked') ? '#ef4444' : '#6b7280'
    }));

    return (
        <PageTemplate
            title={t('Project Detail')}
            url="/project-reports"
            actions={pageActions}
            breadcrumbs={breadcrumbs}
            noPadding
        >

            {/* First Row - Overview and Milestone Progress */}
            <div className="grid grid-cols-1 lg:grid-cols-10 gap-6 mb-6">
                <Card className="bg-white rounded-lg shadow lg:col-span-4">
                    <CardContent className="p-4">
                        <h3 className="text-lg font-bold mb-4">
                            {t('Overview')}
                        </h3>
                        <div className="grid grid-cols-12 gap-4 items-start">
                            <div className="col-span-4 space-y-4">
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">{t('Project Name')}:</p>
                                    <p className="text-sm font-medium">{project.title || project.name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">{t('Project Status')}:</p>
                                    <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getStatusColor(project.status)}`}>
                                        {formatText(project.status || '')}
                                    </span>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">{t('Total Members')}:</p>
                                    <p className="text-sm font-medium">{(project.members?.length || 0) + (project.clients?.length || 0)}</p>
                                </div>
                            </div>
                            <div className="col-span-3 flex flex-col justify-center space-y-4">
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">{t('Start Date')}:</p>
                                    <p className="text-sm font-medium">{formatDate(project.start_date)}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-600 mb-1">{t('Due Date')}:</p>
                                    <p className="text-sm font-medium">{formatDate(project.deadline || project.end_date)}</p>
                                </div>

                            </div>
                            <div className="col-span-5 flex justify-center">
                                <div className="relative w-48 h-48">
                                    <svg className="w-48 h-48 transform -rotate-90">
                                        <circle cx="96" cy="96" r="80" stroke="#e5e7eb" strokeWidth="12" fill="none" />
                                        <circle
                                            cx="96"
                                            cy="96"
                                            r="80"
                                            stroke="#f97316"
                                            strokeWidth="12"
                                            fill="none"
                                            strokeDasharray={`${2 * Math.PI * 80}`}
                                            strokeDashoffset={`${2 * Math.PI * 80 * (1 - (stats.completion_percentage || 0) / 100)}`}
                                            strokeLinecap="round"
                                        />
                                    </svg>
                                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                                        <span className="text-xl font-bold">{stats.completion_percentage || 0}%</span>
                                        <span className="text-xs text-gray-500">{t('Project Progress')}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="bg-white rounded-lg shadow lg:col-span-3">
                    <CardContent className="p-4">
                        <h3 className="text-lg font-bold mb-4">
                            {t('Milestone Progress')}
                        </h3>
                        <div className="flex justify-center">
                            <div className="relative w-80 h-52">
                                <svg className="w-80 h-52" viewBox="0 0 320 208">
                                    {/* Background arc */}
                                    <path
                                        d="M 40 170 A 120 120 0 0 1 280 170"
                                        stroke="#e5e7eb"
                                        strokeWidth="20"
                                        fill="none"
                                    />
                                    {/* Progress arc */}
                                    <path
                                        d="M 40 170 A 120 120 0 0 1 280 170"
                                        stroke="#22c55e"
                                        strokeWidth="20"
                                        fill="none"
                                        strokeDasharray={`${((stats.milestone_completion_percentage || 0) / 100) * 377} 377`}
                                        strokeLinecap="round"
                                        className="transition-all duration-1000 ease-out"
                                    />
                                </svg>
                                <div className="absolute inset-0 flex flex-col items-center justify-center mt-6">
                                    {stats.total_milestones > 0 ? (
                                        <>
                                            <span className="text-xl font-bold text-gray-900">{stats.milestone_completion_percentage || 0}%</span>
                                            <span className="text-xl text-green-600 font-medium">{t('Progress')}</span>
                                        </>
                                    ) : (
                                        <span className="text-sm text-gray-500">{t('No milestones')}</span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="bg-white rounded-lg shadow lg:col-span-3">
                    <CardContent className="p-4">
                        <h3 className="text-lg font-bold mb-14">
                            {t('Task Priority')}
                        </h3>
                        <ResponsiveContainer width="100%" height={120}>
                            <BarChart data={priorityData}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="name" />
                                <YAxis />
                                <ChartTooltip />
                                <Bar dataKey="value" />
                            </BarChart>
                        </ResponsiveContainer>

                        <div className="flex items-center justify-center gap-4 text-xs mt-4">
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 bg-red-500 rounded"></div>
                                <span>Critical</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 bg-orange-500 rounded"></div>
                                <span>High</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 bg-yellow-500 rounded"></div>
                                <span>Medium</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 bg-green-500 rounded"></div>
                                <span>Low</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Second Row - Task Status and Hours Estimation */}
            <div className="grid grid-cols-1 lg:grid-cols-10 gap-6 mb-6">
                <Card className="bg-white rounded-lg shadow lg:col-span-4">
                    <CardContent className="p-4">
                        <h3 className="text-lg font-bold mb-2">
                            {t('Task Status')}
                        </h3>
                        <ResponsiveContainer width="100%" height={200}>
                            <PieChart>
                                <Pie
                                    data={statusData}
                                    cx="50%"
                                    cy="50%"
                                    outerRadius={70}
                                    dataKey="value"
                                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                                >
                                    {statusData.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={entry.fill} />
                                    ))}
                                </Pie>
                                <ChartTooltip />
                            </PieChart>
                        </ResponsiveContainer>

                        <div className="flex items-center justify-center gap-4 text-xs mt-2">
                            {statusData.map((entry, index) => (
                                <div key={index} className="flex items-center gap-2">
                                    <div className="w-3 h-3 rounded" style={{ backgroundColor: entry.fill }}></div>
                                    <span>{entry.name}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card className="bg-white rounded-lg shadow lg:col-span-6">
                    <CardContent className="p-6">
                        <h3 className="text-lg font-bold mb-4">
                            {t('Hours Estimation')}
                        </h3>
                        <div className="space-y-2">
                            <div className="space-y-3">

                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-600">{t('Logged Hours')}</span>
                                    <span className="font-medium">{stats.total_logged_hours || 0}h</span>
                                </div>
                            </div>

                            <ResponsiveContainer width="100%" height={200}>
                                <BarChart
                                    data={stats.task_hours_data || []}
                                    margin={{ top: 5, right: 30, left: 20}}
                                >
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="task_name"
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{ fontSize: 10, width: 100, wordWrap: 'break-word' }}
                                        interval={0}
                                        height={80}
                                    />
                                    <YAxis
                                        axisLine={false}
                                        tickLine={false}
                                        tick={{ fontSize: 10 }}
                                    />
                                    <ChartTooltip
                                        formatter={(value, name) => [
                                            `${value}h`,
                                            name === 'estimated_hours' ? 'Estimated Hours' : 'Logged Hours'
                                        ]}
                                    />
                                    <Bar dataKey="logged_hours" fill="#f59e0b" name="logged_hours" />
                                </BarChart>
                            </ResponsiveContainer>

                            <div className="flex items-center justify-center gap-6 text-xs">
                                <div className="flex items-center gap-2">
                                    <div className="w-3 h-3 bg-orange-500 rounded"></div>
                                    <span>{t('Logged Hours')}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Third Row - Users and Milestones Tables */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <Card className="bg-white rounded-lg shadow">
                    <CardContent className="p-6">
                        <h3 className="text-lg font-bold mb-4">
                            {t('Users')}
                        </h3>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-gray-500 text-xs">
                                        <th className="text-left py-2 font-medium">{t('Name')}</th>
                                        <th className="text-left py-2 font-medium">{t('Assigned tasks')}</th>
                                        <th className="text-left py-2 font-medium">{t('Done tasks')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {userStats?.map((user, index) => (
                                        <tr key={index} className="border-b hover:bg-gray-50">
                                            <td className="py-3">
                                                <div className="flex items-center gap-2">
                                                    <span>{user.name}</span>
                                                </div>
                                            </td>
                                            <td className="py-3">{user.assigned_tasks}</td>
                                            <td className="py-3">{user.done_tasks}</td>
                                        </tr>
                                    )) || (
                                        <tr>
                                            <td colSpan={3} className="py-8 text-center text-gray-500">
                                                {t('No users found')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card className="bg-white rounded-lg shadow">
                    <CardContent className="p-6">
                        <h3 className="text-lg font-bold mb-4">
                            {t('Milestones')}
                        </h3>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-gray-500 text-xs">
                                        <th className="text-left py-2 font-medium">{t('Name')}</th>
                                        <th className="text-left py-2 font-medium">{t('Progress')}</th>
                                        <th className="text-left py-2 font-medium">{t('Status')}</th>
                                        <th className="text-left py-2 font-medium">{t('Due date')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {project.milestones?.map((milestone) => (
                                        <tr key={milestone.id} className="border-b hover:bg-gray-50">
                                            <td className="py-3">{milestone.title}</td>
                                            <td className="py-3">
                                                <div className="flex items-center gap-2">
                                                    <Progress value={milestone.progress} className="w-16 h-2" />
                                                    <span>{milestone.progress}%</span>
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <span className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20">
                                                    {formatText(milestone.status)}
                                                </span>
                                            </td>
                                            <td className="py-3">{formatDate(milestone.due_date)}</td>
                                        </tr>
                                    )) || (
                                        <tr>
                                            <td colSpan={4} className="py-8 text-center text-gray-500">
                                                {t('No milestones found')}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Tasks Table */}
            <div className="bg-white rounded-lg border shadow overflow-hidden">
                {/* Search and filters section */}
                <div className="p-4 border-b bg-gray-50">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder={t('Search tasks...')}
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="w-full pl-9 h-9"
                                    />
                                </div>
                                <Button type="submit" size="sm" className="bg-green-600 hover:bg-green-700 text-white h-9">
                                    <Search className="h-4 w-4 mr-1.5" />
                                    {t('Search')}
                                </Button>
                            </form>

                            <div className="ml-2">
                                <Button
                                    variant={"default"}
                                    size="sm"
                                    className="h-9 px-3"
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter className="h-3.5 w-3.5 mr-1.5" />
                                    {showFilters ? t('Hide Filters') : t('Filters')}
                                    {hasActiveFilters() && (
                                        <span className="ml-1 bg-primary-foreground text-primary rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                            {activeFilterCount()}
                                        </span>
                                    )}
                                </Button>
                            </div>

                        </div>

                        <div className="flex items-center gap-2">
                            <Label className="text-xs text-muted-foreground">{t('Per Page')}:</Label>
                            <Select
                                value={perPage.toString()}
                                onValueChange={(value) => setPerPage(parseInt(value))}
                            >
                                <SelectTrigger className="w-16 h-9">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="10">10</SelectItem>
                                    <SelectItem value="25">25</SelectItem>
                                    <SelectItem value="50">50</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {showFilters && (
                        <div className="w-full mt-3 p-4 bg-white border rounded-md">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div className="space-y-2">
                                    <Label>{t('User')}</Label>
                                    <Select value={selectedUser} onValueChange={setSelectedUser}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Users')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Users')}</SelectItem>
                                            {users?.map((user: any) => (
                                                <SelectItem key={user.user?.id || user.id} value={(user.user?.id || user.id).toString()}>
                                                    {user.user?.name || user.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('Milestone')}</Label>
                                    <Select value={selectedMilestone} onValueChange={setSelectedMilestone}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Milestones')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Milestones')}</SelectItem>
                                            {project.milestones?.map((milestone: any) => (
                                                <SelectItem key={milestone.id} value={milestone.id.toString()}>
                                                    {milestone.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('Status')}</Label>
                                    <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Status')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Status')}</SelectItem>
                                            {stages?.map((stage: any) => (
                                                <SelectItem key={stage.id} value={stage.name}>
                                                    {stage.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label>{t('Priority')}</Label>
                                    <Select value={selectedPriority} onValueChange={setSelectedPriority}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder={t('All Priority')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Priority')}</SelectItem>
                                            <SelectItem value="low">{t('Low')}</SelectItem>
                                            <SelectItem value="medium">{t('Medium')}</SelectItem>
                                            <SelectItem value="high">{t('High')}</SelectItem>
                                            <SelectItem value="critical">{t('Critical')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-9"
                                    onClick={handleResetFilters}
                                    disabled={!hasActiveFilters()}
                                >
                                    {t('Reset Filters')}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-[#F0F0F1] dark:bg-gray-800 border-b">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 tracking-wider">{t('Task Name')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 tracking-wider">{t('Milestone')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 tracking-wider">{t('Start Date')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 tracking-wider">{t('Due Date')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 tracking-wider">{t('Assigned To')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 tracking-wider">{t('Total Logged Hours')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 tracking-wider">{t('Priority')}</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 tracking-wider">{t('Status')}</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {loading ? (
                                <tr>
                                    <td colSpan={8} className="px-6 py-12 text-center">
                                        <div className="text-gray-500">
                                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
                                            <p>{t('Loading tasks...')}</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : error ? (
                                <tr>
                                    <td colSpan={8} className="px-6 py-12 text-center">
                                        <div className="text-red-500">
                                            <p className="text-lg mb-2">{error}</p>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => fetchTasks()}
                                                className="mt-2"
                                            >
                                                {t('Retry')}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ) : tasks && tasks.length > 0 ? tasks.map((task, index) => (
                                <tr key={task.id || index} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <div className="text-sm font-medium text-gray-900">
                                            {task.title || task.name}
                                        </div>
                                        {task.description && (
                                            <div className="text-sm text-gray-500 truncate max-w-xs">
                                                {task.description}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {task.milestone?.title || task.milestone_title || '-'}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {formatDate(task.start_date)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {formatDate(task.due_date || task.end_date)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {task.assigned_users && task.assigned_users.length > 0 ? (
                                            <div className="flex -space-x-1">
                                                {task.assigned_users.slice(0, 3).map((user: any, userIndex: number) => (
                                                    <Tooltip key={userIndex}>
                                                        <TooltipTrigger asChild>
                                                            <Avatar className="h-6 w-6 border-2 border-white cursor-pointer">
                                                                <AvatarImage src={user.avatar} />
                                                                <AvatarFallback className="bg-blue-500 text-white text-[10px] font-medium">
                                                                    {(user.name || user.user?.name)?.charAt(0)?.toUpperCase() || '?'}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>{user.name || user.user?.name || 'Unknown'}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                ))}
                                                {task.assigned_users.length > 3 && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <div className="h-6 w-6 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs cursor-pointer">
                                                                +{task.assigned_users.length - 3}
                                                            </div>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <div className="space-y-1">
                                                                {task.assigned_users.slice(3).map((user: any, idx: number) => (
                                                                    <p key={idx}>{user.name || user.user?.name || 'Unknown'}</p>
                                                                ))}
                                                            </div>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-sm text-gray-500">-</span>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {task.logged_hours || task.total_logged_hours || 0}h
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${getPriorityBadgeColor(task.priority || 'medium')}`}>
                                            {formatText(task.priority || 'medium')}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        <span
                                            className="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                            style={{
                                                backgroundColor: (task.task_stage?.color || '#6b7280') + '20',
                                                color: task.task_stage?.color || '#6b7280',
                                                boxShadow: `inset 0 0 0 1px ${task.task_stage?.color}33`,
                                            }}
                                        >
                                            {formatText(task.status || task.stage || 'To Do')}
                                        </span>
                                    </td>
                                </tr>
                            )) : (
                                <tr>
                                    <td colSpan={8} className="px-6 py-12 text-center">
                                        <div className="text-gray-500">
                                            <p className="text-lg mb-2">{t('No tasks found matching your criteria.')}</p>
                                            <p className="text-sm">{t('Try adjusting your search or filter criteria.')}</p>
                                            {!hasActiveFilters() && (
                                                <p className="text-sm mt-2">{t('This project may not have any tasks yet.')}</p>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {pagination && (
                    <div className="px-6 py-3 border-t flex items-center justify-between text-sm bg-[#F0F0F1] dark:bg-gray-800">
                        <span className="text-gray-700">
                            {t('Showing')} {pagination.from || 0} {t('to')} {pagination.to || 0} {t('of')} {pagination.total || 0} {t('entries')}
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handlePageChange(currentPage - 1)}
                                disabled={currentPage <= 1}
                                className="h-8 px-3"
                            >
                                {t('Previous')}
                            </Button>
                            {Array.from({ length: pagination.last_page }, (_, i) => i + 1)
                                .filter(page => {
                                    const current = currentPage;
                                    return page === 1 || page === pagination.last_page ||
                                           (page >= current - 1 && page <= current + 1);
                                })
                                .map((page, index, array) => {
                                    const prevPage = array[index - 1];
                                    const showEllipsis = prevPage && page - prevPage > 1;
                                    return (
                                        <Fragment key={page}>
                                            {showEllipsis && <span className="px-2">...</span>}
                                            <Button
                                                variant={page === currentPage ? 'default' : 'outline'}
                                                size="sm"
                                                onClick={() => handlePageChange(page)}
                                                className="h-8 w-8"
                                            >
                                                {page}
                                            </Button>
                                        </Fragment>
                                    );
                                })}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handlePageChange(currentPage + 1)}
                                disabled={currentPage >= pagination.last_page}
                                className="h-8 px-3"
                            >
                                {t('Next')}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </PageTemplate>
    );
}
