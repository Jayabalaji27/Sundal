import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { TrendingUp, AlertTriangle, CheckCircle, XCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface ScopeData {
    baseline_tasks: number;
    total_tasks: number;
    added_after_baseline: number;
    creep_rate: number;
    status: 'none' | 'moderate' | 'high' | 'severe';
    message: string;
    recent_additions: { id: number; title: string; priority: string; added_by: string; created_at: string }[];
}

const statusCfg = {
    none:     { icon: CheckCircle,  color: 'text-emerald-600', bg: 'bg-emerald-50 dark:bg-emerald-950/30', border: 'border-emerald-200', label: 'Stable'   },
    moderate: { icon: AlertTriangle, color: 'text-amber-600',  bg: 'bg-amber-50 dark:bg-amber-950/30',     border: 'border-amber-200',   label: 'Moderate' },
    high:     { icon: AlertTriangle, color: 'text-orange-600', bg: 'bg-orange-50 dark:bg-orange-950/30',   border: 'border-orange-200',  label: 'High'     },
    severe:   { icon: XCircle,       color: 'text-red-600',    bg: 'bg-red-50 dark:bg-red-950/30',         border: 'border-red-200',     label: 'Severe'   },
};

export function ScopeCreepWidget({ projectId }: { projectId: number }) {
    const { t } = useTranslation();
    const [data, setData] = useState<ScopeData | null>(null);

    useEffect(() => {
        fetch(route('projects.scope-creep', projectId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(setData)
            .catch(() => {});
    }, [projectId]);

    if (!data) return null;
    if (data.status === 'none' && data.added_after_baseline === 0) return null;

    const cfg = statusCfg[data.status];
    const Icon = cfg.icon;

    return (
        <Card className={`border ${data.status !== 'none' ? cfg.border : 'border-border'}`}>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                    <TrendingUp className="h-4 w-4 text-muted-foreground" />
                    {t('Scope Creep')}
                    {data.status !== 'none' && (
                        <Badge variant="outline" className={`ml-auto text-[10px] ${cfg.color} ${cfg.bg} ${cfg.border} border`}>
                            <Icon className="h-3 w-3 mr-1" />{t(cfg.label)}
                        </Badge>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-3 gap-2 mb-3 text-center">
                    <div className="bg-muted/40 rounded-lg p-2">
                        <div className="text-lg font-bold">{data.baseline_tasks}</div>
                        <div className="text-[10px] text-muted-foreground">{t('Baseline')}</div>
                    </div>
                    <div className="bg-muted/40 rounded-lg p-2">
                        <div className="text-lg font-bold">{data.total_tasks}</div>
                        <div className="text-[10px] text-muted-foreground">{t('Current')}</div>
                    </div>
                    <div className={`rounded-lg p-2 ${data.creep_rate > 0 ? cfg.bg : 'bg-muted/40'}`}>
                        <div className={`text-lg font-bold ${data.creep_rate > 0 ? cfg.color : ''}`}>+{data.creep_rate}%</div>
                        <div className="text-[10px] text-muted-foreground">{t('Growth')}</div>
                    </div>
                </div>

                {data.status !== 'none' && (
                    <div className={`rounded-lg p-2.5 ${cfg.bg} mb-3`}>
                        <p className={`text-xs ${cfg.color}`}>{data.message}</p>
                    </div>
                )}

                {data.recent_additions.length > 0 && (
                    <div>
                        <p className="text-[10px] font-semibold text-muted-foreground uppercase tracking-wide mb-1.5">{t('Recently Added')}</p>
                        <ul className="space-y-1">
                            {data.recent_additions.slice(0,4).map(task => (
                                <li key={task.id} className="text-xs flex items-center gap-1.5">
                                    <span className="flex-1 truncate">{task.title}</span>
                                    <span className="text-muted-foreground shrink-0">by {task.added_by}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
