import { Link } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { AlertTriangle, XCircle, Info, Calendar, User } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface ConflictTask { id: number; title: string; priority: string; end_date: string|null; progress: number; project: string; }
interface Conflict {
    user: { id: number; name: string; avatar: string|null };
    task_count: number;
    high_priority_count: number;
    earliest_deadline: string|null;
    severity: 'critical'|'high'|'medium';
    tasks: ConflictTask[];
    recommendation: string;
}

const severityCfg = {
    critical: { icon: XCircle,        color: 'text-red-600',   bg: 'bg-red-50 dark:bg-red-950/30',     border: 'border-red-200',   badge: 'border-red-300 text-red-700 bg-red-50'   },
    high:     { icon: AlertTriangle,   color: 'text-amber-600', bg: 'bg-amber-50 dark:bg-amber-950/30', border: 'border-amber-200', badge: 'border-amber-300 text-amber-700 bg-amber-50' },
    medium:   { icon: Info,            color: 'text-blue-600',  bg: 'bg-blue-50 dark:bg-blue-950/30',   border: 'border-blue-200',  badge: 'border-blue-300 text-blue-700 bg-blue-50'   },
};
const priorityColor: Record<string,string> = {
    critical: 'border-red-300 text-red-700 bg-red-50',
    high:     'border-orange-300 text-orange-700 bg-orange-50',
    medium:   'border-blue-300 text-blue-700 bg-blue-50',
    low:      'border-gray-300 text-gray-600 bg-gray-50',
};

export default function ResourceConflictsIndex({ conflicts }: { conflicts: Conflict[] }) {
    const { t } = useTranslation();

    return (
        <PageTemplate title={t('Resource Conflict Alert')} subtitle={t('Team members approaching or exceeding capacity in the next 14 days')}>
            {conflicts.length === 0 && (
                <Card><CardContent className="p-10 text-center">
                    <div className="text-4xl mb-3">🎉</div>
                    <p className="font-semibold">{t('No resource conflicts detected')}</p>
                    <p className="text-sm text-muted-foreground mt-1">{t('All team members are within a healthy workload range.')}</p>
                </CardContent></Card>
            )}

            <div className="space-y-4">
                {conflicts.map((c, i) => {
                    const cfg = severityCfg[c.severity];
                    const Icon = cfg.icon;
                    const initials = c.user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
                    return (
                        <Card key={i} className={`border ${cfg.border}`}>
                            <CardContent className="p-5">
                                <div className="flex items-start gap-4">
                                    <Avatar className="h-10 w-10 shrink-0">
                                        {c.user.avatar && <AvatarFallback className="text-sm font-medium">{initials}</AvatarFallback>}
                                        <AvatarFallback className="text-sm font-medium">{initials}</AvatarFallback>
                                    </Avatar>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap mb-1">
                                            <span className="font-semibold">{c.user.name}</span>
                                            <Badge variant="outline" className={`text-xs ${cfg.badge}`}>
                                                <Icon className="h-3 w-3 mr-1" />
                                                {c.severity === 'critical' ? t('Overloaded') : c.severity === 'high' ? t('High Load') : t('Approaching Limit')}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">{c.task_count} {t('tasks due in 14 days')}</span>
                                        </div>
                                        <p className={`text-sm ${cfg.color} font-medium mb-3`}>{c.recommendation}</p>
                                        {/* Task list */}
                                        <div className="space-y-1.5">
                                            {c.tasks.map((task, j) => (
                                                <div key={j} className="flex items-center gap-2 text-xs">
                                                    <Badge variant="outline" className={`text-[10px] px-1.5 py-0 h-4 shrink-0 ${priorityColor[task.priority] ?? ''}`}>
                                                        {task.priority}
                                                    </Badge>
                                                    <span className="flex-1 truncate">[{task.project}] {task.title}</span>
                                                    {task.end_date && (
                                                        <span className="flex items-center gap-0.5 text-muted-foreground shrink-0">
                                                            <Calendar className="h-3 w-3" />
                                                            {new Date(task.end_date).toLocaleDateString()}
                                                        </span>
                                                    )}
                                                    <span className="text-muted-foreground shrink-0">{task.progress}%</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </PageTemplate>
    );
}
