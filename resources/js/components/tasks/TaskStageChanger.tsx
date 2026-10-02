import React from 'react';
import { router } from '@inertiajs/react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Task, TaskStage } from '@/types';

interface Props {
    task: Task;
    stages: TaskStage[];
    variant?: 'select' | 'badge';
}

export default function TaskStageChanger({ task, stages, variant = 'select' }: Props) {
    const handleStageChange = (stageId: string) => {
        router.put(route('tasks.change-stage', task.id), {
            task_stage_id: stageId
        });
    };

    const currentStage = stages.find(s => s.id === task.task_stage_id);

    if (variant === 'badge') {
        return (
            <Badge 
                variant="outline" 
                style={{ 
                    backgroundColor: currentStage?.color + '20', 
                    borderColor: currentStage?.color,
                    color: currentStage?.color
                }}
            >
                {currentStage?.name}
            </Badge>
        );
    }

    return (
        <Select value={task.task_stage_id.toString()} onValueChange={handleStageChange}>
            <SelectTrigger>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {stages.map((stage) => (
                    <SelectItem key={stage.id} value={stage.id.toString()}>
                        <div className="flex items-center space-x-2">
                            <div 
                                className="w-3 h-3 rounded-full" 
                                style={{ backgroundColor: stage.color }}
                            />
                            <span>{stage.name}</span>
                        </div>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}