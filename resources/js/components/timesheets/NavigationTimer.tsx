import React, { useState, useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Clock } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';

interface TimerState {
    active: boolean;
    elapsed_seconds: number;
    is_paused: boolean;
}

export default function NavigationTimer() {
    const { auth } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const canUseTimer = hasPermission(permissions, 'timesheet_use_timer');

    const [timerState, setTimerState] = useState<TimerState>({
        active: false,
        elapsed_seconds: 0,
        is_paused: false
    });
    const [displayTime, setDisplayTime] = useState('00:00:00');
    const [microseconds, setMicroseconds] = useState(0);
    const loggedOutRef = useRef(false);

    useEffect(() => {
        if (!canUseTimer) return;
        fetchTimerStatus();
        const statusInterval = setInterval(() => {
            // A logged-out session gets redirected to the HTML login page (still a
            // 200) instead of JSON - stop polling rather than repeatedly failing to
            // parse HTML as JSON.
            if (loggedOutRef.current) {
                clearInterval(statusInterval);
                return;
            }
            // Only a re-sync: the clock ticks locally and timer actions fire events
            // below. A 2s poll kept a single-threaded server permanently busy.
            if (document.visibilityState === 'hidden') return;
            fetchTimerStatus();
        }, 30000);
        // Tick locally between syncs. Functional update: this effect runs once,
        // so reading timerState here would see only the initial (inactive) state.
        const timerInterval = setInterval(() => {
            setTimerState(prev => (prev.active && !prev.is_paused
                ? { ...prev, elapsed_seconds: prev.elapsed_seconds + 1 }
                : prev));
        }, 1000);
        // Re-sync as soon as the tab is shown again (polling pauses while hidden).
        const handleVisibility = () => {
            if (document.visibilityState === 'visible' && !loggedOutRef.current) fetchTimerStatus();
        };
        document.addEventListener('visibilitychange', handleVisibility);

        // Listen for all timer events
        const handleTimerEvent = () => {
            fetchTimerStatus();
        };
        window.addEventListener('timerStarted', handleTimerEvent);
        window.addEventListener('timerStopped', handleTimerEvent);
        window.addEventListener('timerPaused', handleTimerEvent);
        window.addEventListener('timerResumed', handleTimerEvent);

        return () => {
            clearInterval(statusInterval);
            clearInterval(timerInterval);
            document.removeEventListener('visibilitychange', handleVisibility);
            window.removeEventListener('timerStarted', handleTimerEvent);
            window.removeEventListener('timerStopped', handleTimerEvent);
            window.removeEventListener('timerPaused', handleTimerEvent);
            window.removeEventListener('timerResumed', handleTimerEvent);
        };
    }, []);

    useEffect(() => {
        const totalSeconds = Math.floor(timerState.elapsed_seconds);
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        setDisplayTime(`${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
    }, [timerState.elapsed_seconds]);

    const fetchTimerStatus = async () => {
        try {
            if (typeof route === 'undefined') {
                console.warn('Route helper not available');
                return;
            }
            const response = await fetch(route('timer.status'));
            const isJson = response.headers.get('content-type')?.includes('application/json');
            if (response.ok && isJson) {
                const data = await response.json();
                if (data.active) {
                    setTimerState(data);
                    setMicroseconds(0); // Reset microseconds on sync
                } else {
                    setTimerState({
                        active: false,
                        elapsed_seconds: 0,
                        is_paused: false
                    });
                    setMicroseconds(0);
                }
            } else if (!isJson) {
                // Not authenticated anymore (redirected to the HTML login page).
                loggedOutRef.current = true;
            }
        } catch (error) {
            console.error('Failed to fetch timer status:', error);
        }
    };

    const navigateToTimer = () => {
        try {
            if (typeof route === 'undefined') {
                window.location.href = route('timesheets.index');
                return;
            }
            router.visit(route('timesheets.index'));
        } catch (error) {
            console.error('Navigation failed:', error);
            window.location.href = route('timesheets.index');
        }
    };

    const buttonClass = timerState.active 
        ? (timerState.is_paused 
            ? 'bg-yellow-50 border-yellow-200 hover:bg-yellow-100' 
            : 'bg-green-50 border-green-200 hover:bg-green-100')
        : 'bg-blue-50 border-blue-200 hover:bg-blue-100';

    const iconClass = timerState.active 
        ? (timerState.is_paused ? 'text-yellow-600' : 'text-green-600')
        : 'text-blue-600';

    const textClass = timerState.active
        ? (timerState.is_paused ? 'text-yellow-800' : 'text-green-800')
        : 'text-blue-800';

    if (!canUseTimer) {
        return null;
    }

    return (
        <Button 
            variant="outline" 
            size="sm" 
            className={`gap-2 ${buttonClass}`}
            onClick={navigateToTimer}
        >
            <Clock className={`h-4 w-4 ${iconClass}`} />
            <span className={`font-mono text-xs ${textClass}`}>
                {displayTime}
            </span>
        </Button>
    );
}