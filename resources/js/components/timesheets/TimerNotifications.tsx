import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Clock, AlertTriangle } from 'lucide-react';

interface TimerState {
    active: boolean;
    elapsed_seconds: number;
    is_paused: boolean;
    project_name?: string;
}

interface Props {
    timerState: TimerState;
}

export default function TimerNotifications({ timerState }: Props) {
    const [lastNotification, setLastNotification] = useState<number>(0);

    useEffect(() => {
        if (!timerState.active || timerState.is_paused) return;

        const hours = Math.floor(timerState.elapsed_seconds / 3600);
        
        // Notify every hour after 2 hours
        if (hours >= 2 && hours > lastNotification) {
            setLastNotification(hours);
            
            // Browser notification
            if (Notification.permission === 'granted') {
                new Notification('Timer Running', {
                    body: `You've been working on ${timerState.project_name} for ${hours} hours`,
                    icon: '/favicon.png'
                });
            }
            
            // Toast notification
            toast.warning(`Timer Alert`, {
                description: `You've been working for ${hours} hours. Consider taking a break!`,
                icon: <AlertTriangle className="h-4 w-4" />,
                duration: 5000
            });
        }

        // Long session warning (8+ hours)
        if (hours >= 8 && lastNotification < 8) {
            setLastNotification(8);
            
            toast.error('Long Work Session', {
                description: 'You\'ve been working for 8+ hours. Please take a break!',
                icon: <Clock className="h-4 w-4" />,
                duration: 10000
            });
        }
    }, [timerState.elapsed_seconds, timerState.active, timerState.is_paused, lastNotification, timerState.project_name]);

    // Request notification permission on mount
    useEffect(() => {
        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }, []);

    // Reset notification counter when timer stops
    useEffect(() => {
        if (!timerState.active) {
            setLastNotification(0);
        }
    }, [timerState.active]);

    return null; // This component only handles notifications
}