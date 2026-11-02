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

    // The running time opens the Timesheet (the week), where the time is logged.
    const navigateToTimer = () => router.visit(route('timesheets.weekly-view'));

    const buttonClass = timerState.active 
        ? (timerState.is_paused 
            ? 'bg-ds-warning-bg border-ds-warning/30 hover:bg-ds-warning-bg hover:border-ds-warning/50' 
            : 'bg-ds-success-bg border-ds-success/30 hover:bg-ds-success-bg hover:border-ds-success/50')
        : 'bg-ds-info-bg border-ds-info/25 hover:bg-ds-info-bg hover:border-ds-info/40';

    const iconClass = timerState.active 
        ? (timerState.is_paused ? 'text-ds-warning' : 'text-ds-success')
        : 'text-ds-info';

    const textClass = timerState.active
        ? (timerState.is_paused ? 'text-ds-warning-fg' : 'text-ds-success-fg')
        : 'text-ds-info-fg';

    if (!canUseTimer) {
        return null;
    }

    // Nothing in the header while idle: time is logged on the Timesheet page. A timer
    // started elsewhere (e.g. by the AI Assistant) shows here while it runs or is paused.
    if (!timerState.active) {
        return null;
    }

    return (
        <Button 
            variant="outline" 
            size="sm" 
            className={`gap-2 ${buttonClass}`}
            onClick={navigateToTimer}
            title={timerState.is_paused ? 'Timer paused' : 'Timer running'}
        >
            <Clock className={`h-4 w-4 ${iconClass}`} />
            <span className={`font-mono text-xs ${textClass}`}>
                {displayTime}
            </span>
        </Button>
    );
}