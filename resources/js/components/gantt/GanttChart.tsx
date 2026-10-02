import React, { useEffect, useRef } from 'react';

interface Task {
    id: string;
    name: string;
    start: string;
    end: string;
    progress: number;
    custom_class?: string;
    extra?: {
        priority: string;
        comments: number;
        duration: string;
    };
}

interface GanttChartProps {
    tasks: Task[];
    viewMode?: string;
    onDateChange?: (task: Task, start: Date, end: Date) => void;
}

const GanttChart: React.FC<GanttChartProps> = ({ tasks, viewMode = 'Week', onDateChange }) => {
    const ganttRef = useRef<HTMLDivElement>(null);
    const ganttInstance = useRef<any>(null);
    const onDateChangeRef = useRef(onDateChange);

    useEffect(() => {
        onDateChangeRef.current = onDateChange;
    }, [onDateChange]);

    // For Quarter Day / Half Day: clamp task dates to a 7-day window around
    // the earliest task start — prevents thousands of columns being rendered
    const getTasksForView = (mode: string): Task[] => {
        if (!['Quarter Day', 'Half Day'].includes(mode)) return tasks;

        const today = new Date();
        const windowStart = new Date(today);
        windowStart.setDate(today.getDate() - 3);
        const windowEnd = new Date(today);
        windowEnd.setDate(today.getDate() + 4);

        const fmt = (d: Date) => d.toISOString().split('T')[0];
        const clamp = (dateStr: string, min: Date, max: Date) => {
            const d = new Date(dateStr);
            if (d < min) return fmt(min);
            if (d > max) return fmt(max);
            return fmt(d);
        };

        return tasks.map(t => ({
            ...t,
            start: clamp(t.start, windowStart, windowEnd),
            end:   clamp(t.end,   windowStart, windowEnd),
        }));
    };

    useEffect(() => {
        if (!ganttRef.current || tasks.length === 0 || !(window as any).Gantt) return;

        ganttInstance.current = null;
        ganttRef.current.innerHTML = '';

        const tasksToRender = getTasksForView(viewMode);

        ganttInstance.current = new (window as any).Gantt(ganttRef.current, tasksToRender, {
            view_mode: viewMode,
            column_width: 50,
            custom_popup_html: function(task: Task) {
                let status_class = 'success';
                if (task.custom_class === 'medium') status_class = 'info';
                else if (task.custom_class === 'high') status_class = 'danger';
                return `<div class="details-container">
                            <div class="title">${task.name}</div>
                            <div class="subtitle">
                                <b>${task.progress}%</b> Progress <br>
                                <b>${task.extra?.comments || 0}</b> Comments <br>
                                <b>Duration:</b> ${task.extra?.duration || 'No dates set'} <br>
                                <b>Status:</b><span class="badge badge-${status_class}">${task.extra?.priority || 'Low'}</span>
                            </div>
                        </div>`;
            },
            on_date_change: function(task: Task, start: Date, end: Date) {
                if (onDateChangeRef.current) onDateChangeRef.current(task, start, end);
            }
        });
    }, [tasks, viewMode]);

    useEffect(() => {
        if (ganttInstance.current?.change_view_mode) {
            ganttInstance.current.change_view_mode(viewMode);
        }
    }, [viewMode]);

    return (
        <div 
            ref={ganttRef} 
            className="gantt-target" 
            style={{ minWidth: '1500px', width: 'auto' }}
        />
    );
};

export default GanttChart;