import { useState, useEffect } from 'react';
import { router, usePage, Head } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import GanttChart from '@/components/gantt/GanttChart';
import axios from 'axios';
import { t } from 'i18next';

export default function ProjectGantt() {
    const { auth, project: initialProject, tasks: initialTasks } = usePage().props as any;
    const [viewMode, setViewMode] = useState('Week');
    const [tasks, setTasks] = useState<any[]>(initialTasks || []);
    const [project, setProject] = useState<any>(initialProject);

    // Filter and fix tasks for Gantt chart
    const validTasks = tasks.filter(task => {
        return task.start && task.end;
    }).map(task => ({
        ...task,
        start: task.start.split('T')[0],
        end: task.end.split('T')[0]
    }));

    const handleDateChange = async (task: any, start: Date, end: Date) => {
        const newStart = start.toISOString().split('T')[0];
        const newEnd = end.toISOString().split('T')[0];

        // Optimistically update tasks state so chart doesn't go blank
        setTasks(prev => prev.map(t =>
            t.id === task.id ? { ...t, start: newStart, end: newEnd } : t
        ));

        try {
            const response = await axios.post(route('projects.gantt.update', project.id), {
                task_id: task.id,
                start: newStart + ' 00:00:00',
                end: newEnd + ' 23:59:59'
            });

            if (response.data.is_success) {
                toast.success('Task dates updated successfully');
            } else {
                toast.error(response.data.message || 'Failed to update task dates');
                // Revert on failure
                setTasks(prev => prev.map(t =>
                    t.id === task.id ? { ...t, start: task.start, end: task.end } : t
                ));
            }
        } catch (error: any) {
            console.error('Error updating task dates:', error);
            toast.error(error.response?.data?.message || 'Failed to update task dates');
            // Revert on error
            setTasks(prev => prev.map(t =>
                t.id === task.id ? { ...t, start: task.start, end: task.end } : t
            ));
        }
    };

    const breadcrumbs = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Projects', href: route('projects.index') },
        { title: project?.title || 'Project', href: project ? route('projects.show', project) : '#' },
        { title: 'Gantt Chart' }
    ];

    const pageActions = [
        {
            label: 'Back',
            icon: <ArrowLeft className="h-4 w-4 mr-2" />,
            variant: 'outline' as const,
            onClick: () => project && router.get(route('projects.show', project))
        }
    ];

    return (
        <>
            <PageTemplate
                title="Gantt Chart"
                url={`/projects/${project?.id}/gantt`}
                breadcrumbs={breadcrumbs}
                actions={pageActions}
            >
                <div className="space-y-4">
                    <div className="bg-white rounded-lg shadow p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">{project?.title}</h2>
                            <div className="flex flex-wrap gap-2">
                                {['Quarter Day', 'Half Day', 'Day', 'Week', 'Month'].map((mode) => (
                                    <Button
                                        key={mode}
                                        size="sm"
                                        variant={viewMode === mode ? 'default' : 'outline'}
                                        onClick={() => setViewMode(mode)}
                                    >
                                        {mode}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow p-4">
                        {validTasks.length > 0 ? (
                            <div 
                                style={{ width: '100%', height: '400px', maxWidth: '1400px', overflowY: 'auto', overflowX: 'auto' }}
                            >
                                <GanttChart
                                    tasks={validTasks}
                                    viewMode={viewMode}
                                    onDateChange={handleDateChange}
                                />
                            </div>
                        ) : (
                            <div className="flex items-center justify-center h-64">
                                <div className="text-center">
                                    <p className="text-gray-500 mb-2">{t('No tasks with valid dates')}</p>
                                    <p className="text-sm text-gray-400">{t('Tasks need both start and end dates to appear in the Gantt chart')}</p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </PageTemplate>
        </>
    );
}