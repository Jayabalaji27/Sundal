import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, Radio, Bot, ChevronRight } from 'lucide-react';
import { hasPermission } from '@/utils/authorization';

declare const route: any;

interface Agent {
    id: number;
    name: string;
    description: string | null;
}

interface Props {
    agents: Agent[];
}

const PRESET_TOOLS = [
    {
        key: 'risk-radar',
        icon: <Radio className="h-6 w-6 text-amber-500" />,
        color: 'bg-amber-50 dark:bg-amber-900/20',
        route: 'risk-radar.index',
        title: 'Risk Radar',
        description: 'Surfaces overdue tasks, budget overruns, and project health issues at a glance.',
        permission: 'agent_advanced_insights',
    },
    {
        key: 'resource-conflicts',
        icon: <AlertTriangle className="h-6 w-6 text-rose-500" />,
        color: 'bg-rose-50 dark:bg-rose-900/20',
        route: 'resource-conflicts.index',
        title: 'Resource Conflicts',
        description: 'Detects team members over-assigned across concurrent projects and sprints.',
        permission: 'agent_advanced_insights',
    },
] as const;

export default function AiPage({ agents }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const permissions: string[] = auth?.permissions || [];
    const visibleTools = PRESET_TOOLS.filter(tool => !tool.permission || hasPermission(permissions, tool.permission));

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('AI') },
    ];

    return (
        <PageTemplate title={t('AI Tools')} breadcrumbs={breadcrumbs}>
            <div className="space-y-8">
                {/* Built-in analysis tools */}
                {visibleTools.length > 0 && (
                <div>
                    <h2 className="text-base font-semibold mb-4 text-muted-foreground uppercase tracking-wide text-xs">
                        {t('Built-in Analysis')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {visibleTools.map((tool) => (
                            <Card
                                key={tool.key}
                                className="cursor-pointer hover:shadow-md transition-shadow group"
                                onClick={() => router.visit(route(tool.route))}
                            >
                                <CardHeader className="pb-2">
                                    <div className={`mb-3 flex h-12 w-12 items-center justify-center rounded-xl ${tool.color}`}>
                                        {tool.icon}
                                    </div>
                                    <CardTitle className="flex items-center justify-between text-base">
                                        {t(tool.title)}
                                        <ChevronRight className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription>{t(tool.description)}</CardDescription>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
                )}

                {/* Custom agents */}
                {agents.length > 0 && (
                    <div>
                        <h2 className="text-base font-semibold mb-4 text-muted-foreground uppercase tracking-wide text-xs">
                            {t('Custom Agents')}
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {agents.map((agent) => (
                                <Card
                                    key={agent.id}
                                    className="cursor-pointer hover:shadow-md transition-shadow group"
                                    onClick={() => router.visit(route('agents.chat', agent.id))}
                                >
                                    <CardHeader className="pb-2">
                                        <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 dark:bg-violet-900/20">
                                            <Bot className="h-6 w-6 text-violet-500" />
                                        </div>
                                        <CardTitle className="flex items-center justify-between text-base">
                                            {agent.name}
                                            <ChevronRight className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </CardTitle>
                                    </CardHeader>
                                    {agent.description && (
                                        <CardContent>
                                            <CardDescription>{agent.description}</CardDescription>
                                        </CardContent>
                                    )}
                                </Card>
                            ))}
                        </div>
                    </div>
                )}

                {agents.length === 0 && (
                    <div className="flex flex-col items-center gap-3 py-8 text-center text-muted-foreground">
                        <Bot className="h-10 w-10 opacity-30" />
                        <p className="text-sm">{t('No custom agents yet.')}</p>
                        {hasPermission(permissions, 'agent_create') && (
                            <Button variant="outline" size="sm" onClick={() => router.visit(route('agents.index'))}>
                                {t('Create an Agent')}
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </PageTemplate>
    );
}
