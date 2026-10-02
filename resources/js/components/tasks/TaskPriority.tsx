import React from 'react';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, ArrowUp, Minus, ArrowDown } from 'lucide-react';

interface Props {
    priority: 'low' | 'medium' | 'high' | 'critical';
    showIcon?: boolean;
}

export default function TaskPriority({ priority, showIcon = false }: Props) {
    const getPriorityConfig = (priority: string) => {
        switch (priority) {
            case 'critical':
                return {
                    color: 'bg-red-100 text-red-800 border-red-200',
                    icon: AlertTriangle,
                    label: 'Critical'
                };
            case 'high':
                return {
                    color: 'bg-orange-100 text-orange-800 border-orange-200',
                    icon: ArrowUp,
                    label: 'High'
                };
            case 'medium':
                return {
                    color: 'bg-yellow-100 text-yellow-800 border-yellow-200',
                    icon: Minus,
                    label: 'Medium'
                };
            case 'low':
                return {
                    color: 'bg-green-100 text-green-800 border-green-200',
                    icon: ArrowDown,
                    label: 'Low'
                };
            default:
                return {
                    color: 'bg-gray-100 text-gray-800 border-gray-200',
                    icon: Minus,
                    label: 'Medium'
                };
        }
    };

    const config = getPriorityConfig(priority);
    const Icon = config.icon;

    return (
        <Badge className={config.color}>
            {showIcon && <Icon className="h-3 w-3 mr-1" />}
            {config.label}
        </Badge>
    );
}