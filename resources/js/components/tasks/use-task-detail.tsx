import { useState } from 'react';
import TaskModal from '@/pages/tasks/TaskModal';
import { toast } from '@/components/custom-toast';

/**
 * Opens a task's detail dialog (the one the Tasks page uses) on any page, so
 * the project Backlog and Board can show a task without leaving the project.
 * Returns the opener and the dialog element to render.
 */
export function useTaskDetail({ members = [], stages = [], milestones = [] }: { members?: any[]; stages?: any[]; milestones?: any[] }) {
    const [task, setTask] = useState<any>(null);
    const [permissions, setPermissions] = useState<any>(undefined);

    const openTask = async (taskId: number) => {
        try {
            const response = await fetch(route('tasks.show', taskId), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            const data = await response.json();
            setPermissions(data.permissions);
            setTask(data.task);
        } catch {
            toast.error('Could not open the task.');
        }
    };

    const dialog = task ? (
        <TaskModal
            key={task.id}
            task={task}
            isOpen={!!task}
            onClose={() => setTask(null)}
            members={members}
            stages={stages}
            milestones={task.project?.milestones || milestones}
            permissions={permissions}
        />
    ) : null;

    return { openTask, taskDialog: dialog };
}
