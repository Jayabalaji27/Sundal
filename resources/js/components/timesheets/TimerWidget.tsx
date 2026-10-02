import React, { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import TimerNotifications from './TimerNotifications';
import { Play, Pause, Square, Clock } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';

interface Project {
    id: number;
    title: string;
    tasks?: Task[];
}

interface Task {
    id: number;
    title: string;
}

interface TimerState {
    active: boolean;
    project_id?: number;
    task_id?: number;
    description?: string;
    elapsed_seconds: number;
    is_paused: boolean;
}

interface Props {
    projects: Project[];
    permissions: string[];
}

export default function TimerWidget({ projects, permissions }: Props) {
    
    // Don't render if user doesn't have timer permission
    if (!hasPermission(permissions, 'timesheet_use_timer')) {
        return null;
    }
    const [timerState, setTimerState] = useState<TimerState>({
        active: false,
        elapsed_seconds: 0,
        is_paused: false
    });
    const [selectedProject, setSelectedProject] = useState<string>('');
    const [selectedTask, setSelectedTask] = useState<string>('');
    const [description, setDescription] = useState('');
    const [displayTime, setDisplayTime] = useState('00:00:00');
    const [microseconds, setMicroseconds] = useState(0);
    const loggedOutRef = useRef(false);

    useEffect(() => {
        fetchTimerStatus();
        const syncInterval = setInterval(() => {
            // If a prior tick discovered the session is gone (logout in another
            // tab/request), stop polling instead of spamming failed requests.
            if (loggedOutRef.current) {
                clearInterval(syncInterval);
                return;
            }
            fetchTimerStatus();
        }, 3000); // Sync every 3 seconds
        const timerInterval = setInterval(() => {
            if (timerState.active && !timerState.is_paused) {
                setMicroseconds(prev => {
                    const newMicros = prev + 100;
                    if (newMicros >= 1000) {
                        setTimerState(prevState => ({
                            ...prevState,
                            elapsed_seconds: prevState.elapsed_seconds + 1
                        }));
                        return 0;
                    }
                    return newMicros;
                });
            }
        }, 100);

        return () => {
            clearInterval(syncInterval);
            clearInterval(timerInterval);
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
            const response = await fetch(route('timer.status'));
            // A logged-out session gets redirected to the HTML login page instead of
            // JSON (e.g. after logout in this or another tab) - stop polling rather
            // than repeatedly failing to parse HTML as JSON.
            if (!response.headers.get('content-type')?.includes('application/json')) {
                loggedOutRef.current = true;
                return;
            }
            const data = await response.json();
            if (data.active) {
                setTimerState(data);
                setSelectedProject(data.project_id?.toString() || '');
                setSelectedTask(data.task_id?.toString() || '');
                setDescription(data.description || '');
                setMicroseconds(0); // Reset microseconds on sync
            } else {
                setTimerState({ active: false, elapsed_seconds: 0, is_paused: false });
                setMicroseconds(0);
            }
        } catch (error) {
            console.error('Failed to fetch timer status:', error);
        }
    };

    const handleStart = async () => {
        if (!selectedProject || timerState.active) return;

        try {
            const response = await fetch(route('timer.start'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    project_id: selectedProject,
                    task_id: selectedTask || null,
                    description
                })
            });
            
            if (response.ok) {
                setTimerState(prev => ({ ...prev, active: true, is_paused: false }));
                await fetchTimerStatus(); // Refresh timer state
                window.dispatchEvent(new CustomEvent('timerStarted'));
            } else {
                const errorData = await response.json();
                console.error('Failed to start timer:', errorData.error);
            }
        } catch (error) {
            console.error('Failed to start timer:', error);
        }
    };

    const handlePause = async () => {
        try {
            const response = await fetch(route('timer.pause'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            
            if (response.ok) {
                setTimerState(prev => ({ ...prev, is_paused: true }));
                window.dispatchEvent(new CustomEvent('timerPaused'));
            } else {
                const errorData = await response.json();
                console.error('Failed to pause timer:', errorData.error);
            }
        } catch (error) {
            console.error('Failed to pause timer:', error);
        }
    };

    const handleResume = async () => {
        try {
            const response = await fetch(route('timer.resume'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            
            if (response.ok) {
                setTimerState(prev => ({ ...prev, is_paused: false }));
                window.dispatchEvent(new CustomEvent('timerResumed'));
            } else {
                const errorData = await response.json();
                console.error('Failed to resume timer:', errorData.error);
            }
        } catch (error) {
            console.error('Failed to resume timer:', error);
        }
    };

    const handleStop = async () => {
        try {
            const response = await fetch(route('timer.stop'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            
            if (response.ok) {
                setTimerState({
                    active: false,
                    elapsed_seconds: 0,
                    is_paused: false
                });
                setMicroseconds(0);
                setSelectedProject('');
                setSelectedTask('');
                setDescription('');
                await fetchTimerStatus(); // Force sync after stop
                // Notify other timer components
                window.dispatchEvent(new CustomEvent('timerStopped'));
            } else {
                const errorData = await response.json();
                console.error('Failed to stop timer:', errorData.error);
            }
        } catch (error) {
            console.error('Failed to stop timer:', error);
        }
    };

    const selectedProjectData = projects.find(p => p.id.toString() === selectedProject);
    const availableTasks = selectedProjectData?.tasks || [];

    return (
        <Card className="w-full">
            <CardContent className="p-4">
                <div className="flex items-center gap-2 mb-4">
                    <Clock className="h-5 w-5" />
                    <span className="font-semibold">Timer</span>
                    <div className="ml-auto text-lg font-mono">
                        {displayTime}
                    </div>
                </div>

                {!timerState.active ? (
                    <div className="grid grid-cols-1 gap-3 items-end sm:grid-cols-5">
                        <div>
                            <Select value={selectedProject} onValueChange={setSelectedProject}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select project *" />
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

                        <div>
                            <Select value={selectedTask} onValueChange={setSelectedTask}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select task *" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableTasks.map(task => (
                                        <SelectItem key={task.id} value={task.id.toString()}>
                                            {task.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="col-span-2">
                            <Input
                                placeholder="What are you working on?"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                            />
                        </div>

                        <div>
                            <Button
                                type="button"
                                onClick={handleStart}
                                disabled={!selectedProject || timerState.active}
                                className="w-full"
                            >
                                <Play className="h-4 w-4 mr-2" />
                                Start Timer
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 items-center sm:grid-cols-5">
                        <div className="text-sm text-muted-foreground">
                            <div className="font-medium">{selectedProjectData?.title}</div>
                        </div>
                        
                        <div className="text-sm text-muted-foreground">
                            <div className="font-medium">{availableTasks.find(t => t.id.toString() === selectedTask)?.title}</div>
                        </div>
                        
                        <div className="col-span-2 text-sm text-muted-foreground">
                            <div>{description || 'No description'}</div>
                        </div>

                        <div className="flex gap-2">
                            {timerState.is_paused ? (
                                <Button type="button" onClick={handleResume} size="sm">
                                    <Play className="h-4 w-4 mr-1" />
                                    Resume
                                </Button>
                            ) : (
                                <Button type="button" onClick={handlePause} variant="outline" size="sm">
                                    <Pause className="h-4 w-4 mr-1" />
                                    Pause
                                </Button>
                            )}
                            
                            <Button type="button" onClick={handleStop} variant="destructive" size="sm">
                                <Square className="h-4 w-4 mr-1" />
                                Stop
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
            
            <TimerNotifications timerState={timerState} />
        </Card>
    );
}