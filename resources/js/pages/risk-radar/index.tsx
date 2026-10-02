import { Link } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { AlertTriangle, XCircle, CheckCircle, ExternalLink, Activity, Calendar } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Factor { type: string; message: string; }
interface ProjectRisk {
    id: number; title: string; status: string; deadline: string|null;
    progress: number;
    health: { score: number; status: string; factors: Factor[]; };
}
interface Summary { critical: number; at_risk: number; healthy: number; }

const statusCfg = {
    healthy:  { icon: CheckCircle,  color: 'text-emerald-600', bg: 'bg-emerald-50 dark:bg-emerald-950/30', badge: 'border-emerald-300 text-emerald-700 bg-emerald-50' },
    at_risk:  { icon: AlertTriangle, color: 'text-amber-600',  bg: 'bg-amber-50 dark:bg-amber-950/30',    badge: 'border-amber-300 text-amber-700 bg-amber-50'   },
    critical: { icon: XCircle,       color: 'text-red-600',    bg: 'bg-red-50 dark:bg-red-950/30',         badge: 'border-red-300 text-red-700 bg-red-50'         },
};

const scoreBar = (score: number) => {
    if (score >= 80) return 'bg-emerald-500';
    if (score >= 50) return 'bg-amber-500';
    return 'bg-red-500';
};

export default function RiskRadarIndex({ projects, summary }: { projects: ProjectRisk[]; summary: Summary }) {
    const { t } = useTranslation();

    return (
        <PageTemplate title={t('Risk Radar')} subtitle={t('Real-time health overview of all active projects')}>
            {/* Summary cards */}
            <div className="grid grid-cols-1 gap-4 mb-8 sm:grid-cols-3">
                {[
                    { key: 'critical', label: t('Critical'), icon: XCircle,        color: 'text-red-600',     bg: 'bg-red-50 dark:bg-red-950/30',       border: 'border-red-200'   },
                    { key: 'at_risk',  label: t('At Risk'),  icon: AlertTriangle,    color: 'text-amber-600',   bg: 'bg-amber-50 dark:bg-amber-950/30',   border: 'border-amber-200' },
                    { key: 'healthy',  label: t('Healthy'),  icon: CheckCircle,      color: 'text-emerald-600', bg: 'bg-emerald-50 dark:bg-emerald-950/30', border: 'border-emerald-200' },
                ].map(({ key, label, icon: Icon, color, bg, border }) => (
                    <Card key={key} className={`border ${border} ${bg}`}>
                        <CardContent className="p-4 flex items-center gap-3">
                            <Icon className={`h-8 w-8 ${color}`} />
                            <div>
                                <div className={`text-3xl font-bold ${color}`}>{(summary as any)[key]}</div>
                                <div className="text-xs text-muted-foreground">{label}</div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Project list */}
            {projects.length === 0 && (
                <Card><CardContent className="p-10 text-center text-muted-foreground">
                    {t('No active projects found.')}
                </CardContent></Card>
            )}

            <div className="space-y-3">
                {projects.map(project => {
                    const cfg = statusCfg[project.health.status as keyof typeof statusCfg];
                    const Icon = cfg.icon;
                    return (
                        <Card key={project.id} className={`border ${project.health.status === 'critical' ? 'border-red-200' : project.health.status === 'at_risk' ? 'border-amber-200' : 'border-border'}`}>
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-start gap-3 flex-1 min-w-0">
                                        <div className={`rounded-full p-1.5 ${cfg.bg} shrink-0 mt-0.5`}>
                                            <Icon className={`h-4 w-4 ${cfg.color}`} />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <Link href={route('projects.show', project.id)}
                                                    className="font-semibold text-sm hover:underline truncate max-w-xs">
                                                    {project.title}
                                                </Link>
                                                <Badge variant="outline" className={`text-[10px] ${cfg.badge}`}>
                                                    {t(cfg.icon === CheckCircle ? 'Healthy' : cfg.icon === AlertTriangle ? 'At Risk' : 'Critical')}
                                                </Badge>
                                            </div>
                                            {/* Top 2 factors */}
                                            <div className="mt-1.5 space-y-0.5">
                                                {project.health.factors.slice(0,2).map((f,i) => (
                                                    <p key={i} className="text-xs text-muted-foreground">{f.message}</p>
                                                ))}
                                            </div>
                                            {/* Score bar */}
                                            <div className="mt-2 flex items-center gap-2">
                                                <div className="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                                                    <div className={`h-full rounded-full ${scoreBar(project.health.score)}`}
                                                        style={{ width: `${project.health.score}%` }} />
                                                </div>
                                                <span className="text-xs font-medium w-8 text-right">{project.health.score}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right shrink-0 space-y-1">
                                        <div className="text-sm font-medium">{project.progress}%</div>
                                        {project.deadline && (
                                            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                                <Calendar className="h-3 w-3" />
                                                {new Date(project.deadline).toLocaleDateString()}
                                            </div>
                                        )}
                                        <Link href={route('projects.show', project.id)}
                                            className="inline-flex items-center gap-1 text-xs text-primary hover:underline">
                                            {t('View')} <ExternalLink className="h-3 w-3" />
                                        </Link>
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
