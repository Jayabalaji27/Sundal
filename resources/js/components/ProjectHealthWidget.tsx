import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Activity, AlertTriangle, CheckCircle, XCircle, Info, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface HealthFactor {
    type: 'critical' | 'warning' | 'info' | 'success';
    message: string;
}

interface HealthData {
    score: number;
    status: 'healthy' | 'at_risk' | 'critical';
    factors: HealthFactor[];
    metrics: {
        task_completion: number;
        total_tasks: number;
        done_tasks: number;
        overdue_tasks: number;
        critical_bugs: number;
        schedule_gap: number | null;
        days_remaining: number | null;
        expected_progress: number | null;
        actual_progress: number | null;
        budget: number | null;
        spent: number | null;
        budget_used_pct: number | null;
    };
}

const statusConfig = {
    healthy:  { color: 'text-emerald-600', bg: 'bg-emerald-50 dark:bg-emerald-950/30', border: 'border-emerald-200 dark:border-emerald-800', label: 'Healthy',  icon: CheckCircle },
    at_risk:  { color: 'text-amber-600',   bg: 'bg-amber-50 dark:bg-amber-950/30',     border: 'border-amber-200 dark:border-amber-800',   label: 'At Risk',  icon: AlertTriangle },
    critical: { color: 'text-red-600',     bg: 'bg-red-50 dark:bg-red-950/30',         border: 'border-red-200 dark:border-red-800',        label: 'Critical', icon: XCircle },
};

const factorIcon = {
    critical: <XCircle className="h-3.5 w-3.5 text-red-500 flex-shrink-0 mt-0.5" />,
    warning:  <AlertTriangle className="h-3.5 w-3.5 text-amber-500 flex-shrink-0 mt-0.5" />,
    info:     <Info className="h-3.5 w-3.5 text-blue-500 flex-shrink-0 mt-0.5" />,
    success:  <CheckCircle className="h-3.5 w-3.5 text-emerald-500 flex-shrink-0 mt-0.5" />,
};

function ScoreGauge({ score, status }: { score: number; status: string }) {
    const circumference = 2 * Math.PI * 40;
    const offset = circumference - (score / 100) * circumference;
    const strokeColor = status === 'healthy' ? '#10b981' : status === 'at_risk' ? '#f59e0b' : '#ef4444';

    return (
        <div className="relative inline-flex items-center justify-center">
            <svg width="96" height="96" viewBox="0 0 96 96" className="-rotate-90">
                <circle cx="48" cy="48" r="40" fill="none" stroke="currentColor" strokeWidth="8"
                    className="text-muted/20" />
                <circle cx="48" cy="48" r="40" fill="none" stroke={strokeColor} strokeWidth="8"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    strokeLinecap="round"
                    style={{ transition: 'stroke-dashoffset 0.8s ease' }} />
            </svg>
            <div className="absolute flex flex-col items-center">
                <span className="text-2xl font-bold leading-none">{score}</span>
                <span className="text-[10px] text-muted-foreground">/100</span>
            </div>
        </div>
    );
}

export function ProjectHealthWidget({ projectId }: { projectId: number }) {
    const { t } = useTranslation();
    const [health, setHealth] = useState<HealthData | null>(null);
    const [loading, setLoading] = useState(true);

    const fetchHealth = () => {
        setLoading(true);
        fetch(route('projects.health', projectId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(data => { setHealth(data); setLoading(false); })
            .catch(() => setLoading(false));
    };

    useEffect(() => { fetchHealth(); }, [projectId]);

    const cfg = health ? statusConfig[health.status] : null;
    const StatusIcon = cfg?.icon;

    return (
        <Card className={`border ${cfg?.border ?? 'border-border'}`}>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between text-sm font-semibold">
                    <span className="flex items-center gap-2">
                        <Activity className="h-4 w-4 text-muted-foreground" />
                        {t('Project Health')}
                    </span>
                    <button onClick={fetchHealth} className="text-muted-foreground hover:text-foreground transition-colors" title={t('Refresh')}>
                        <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                    </button>
                </CardTitle>
            </CardHeader>

            <CardContent>
                {loading && !health && (
                    <div className="flex items-center justify-center h-24">
                        <div className="animate-pulse text-sm text-muted-foreground">{t('Calculating...')}</div>
                    </div>
                )}

                {health && cfg && StatusIcon && (
                    <div className="space-y-4">
                        {/* Score + Status */}
                        <div className="flex items-center gap-4">
                            <ScoreGauge score={health.score} status={health.status} />
                            <div>
                                <Badge variant="outline" className={`${cfg.color} ${cfg.bg} ${cfg.border} border text-xs font-medium mb-1`}>
                                    <StatusIcon className="h-3 w-3 mr-1" />
                                    {t(cfg.label)}
                                </Badge>
                                <div className="grid grid-cols-2 gap-x-4 gap-y-0.5 mt-2">
                                    <span className="text-xs text-muted-foreground">{t('Tasks done')}</span>
                                    <span className="text-xs font-medium">{health.metrics.done_tasks}/{health.metrics.total_tasks}</span>
                                    <span className="text-xs text-muted-foreground">{t('Overdue')}</span>
                                    <span className={`text-xs font-medium ${health.metrics.overdue_tasks > 0 ? 'text-red-500' : ''}`}>
                                        {health.metrics.overdue_tasks}
                                    </span>
                                    {health.metrics.critical_bugs > 0 && <>
                                        <span className="text-xs text-muted-foreground">{t('Crit. bugs')}</span>
                                        <span className="text-xs font-medium text-red-500">{health.metrics.critical_bugs}</span>
                                    </>}
                                    {health.metrics.days_remaining !== null && <>
                                        <span className="text-xs text-muted-foreground">{t('Days left')}</span>
                                        <span className={`text-xs font-medium ${(health.metrics.days_remaining ?? 0) < 7 ? 'text-amber-500' : ''}`}>
                                            {Math.round(health.metrics.days_remaining ?? 0)}
                                        </span>
                                    </>}
                                </div>
                            </div>
                        </div>

                        {/* Risk factors */}
                        <div className={`rounded-lg p-3 ${cfg.bg} space-y-1.5`}>
                            {health.factors.map((f, i) => (
                                <div key={i} className="flex items-start gap-2">
                                    {factorIcon[f.type]}
                                    <span className="text-xs leading-snug">{t(f.message)}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
