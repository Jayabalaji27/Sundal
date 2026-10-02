import { router, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Eye, FolderKanban, ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';

const STATUS_COLORS: Record<string, string> = {
    planning: 'bg-blue-100 text-blue-700',
    active: 'bg-green-100 text-green-700',
    on_hold: 'bg-yellow-100 text-yellow-700',
    completed: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-700',
};

const PRIORITY_COLORS: Record<string, string> = {
    low: 'bg-slate-100 text-slate-600',
    medium: 'bg-blue-100 text-blue-600',
    high: 'bg-orange-100 text-orange-600',
    urgent: 'bg-red-100 text-red-600',
};

export default function PortfolioShow() {
    const { t } = useTranslation();
    const { portfolio, projects } = usePage().props as any;

    return (
        <PageTemplate
            title={portfolio.name}
            description={portfolio.description || t('Portfolio projects')}
            url={route('portfolios.show', portfolio.id)}
            breadcrumbs={[
                { title: t('Portfolios'), href: route('portfolios.index') },
                { title: portfolio.name, href: route('portfolios.show', portfolio.id) },
            ]}
            actions={[{
                label: t('Go to Projects'),
                icon: <ArrowLeft className="mr-1.5 h-4 w-4" />,
                variant: 'outline',
                onClick: () => router.get(route('projects.index')),
            }]}
        >
                {/* Portfolio summary bar */}
                <div className="mb-6 flex items-center gap-3 rounded-xl border bg-muted/30 p-4">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg"
                        style={{ backgroundColor: portfolio.color + '20', color: portfolio.color }}>
                        <FolderKanban className="h-5 w-5" />
                    </div>
                    <div className="flex-1">
                        <p className="font-semibold">{portfolio.name}</p>
                        {portfolio.description && (
                            <p className="text-sm text-muted-foreground">{portfolio.description}</p>
                        )}
                    </div>
                    <Badge variant="secondary">
                        {projects.length} {projects.length === 1 ? t('project') : t('projects')}
                    </Badge>
                </div>

                {/* Projects Grid */}
                {projects.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed py-16 text-center">
                        <FolderKanban className="mb-3 h-10 w-10 text-muted-foreground" />
                        <p className="text-muted-foreground">{t('No projects in this portfolio yet.')}</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('Create or edit a project and assign it to this portfolio.')}
                        </p>
                        <Button className="mt-4" onClick={() => router.get(route('projects.index'))}>
                            {t('Go to Projects')}
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {projects.map((p: any) => (
                            <Card key={p.id}
                                className="cursor-pointer transition-shadow hover:shadow-md"
                                onClick={() => router.get(route('projects.show', p.id))}>
                                <CardHeader className="pb-2">
                                    <div className="flex items-start justify-between gap-2">
                                        <CardTitle className="text-base font-semibold">{p.title}</CardTitle>
                                        <Eye className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    </div>
                                    <div className="flex flex-wrap gap-1.5 pt-1">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[p.status] ?? ''}`}>
                                            {t(p.status.replace('_', ' '))}
                                        </span>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${PRIORITY_COLORS[p.priority] ?? ''}`}>
                                            {t(p.priority)}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    {p.description && (
                                        <p className="mb-3 text-sm text-muted-foreground line-clamp-2">{p.description}</p>
                                    )}
                                    <div className="mt-2">
                                        <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                                            <span>{t('Progress')}</span>
                                            <span>{p.progress ?? 0}%</span>
                                        </div>
                                        <Progress value={p.progress ?? 0} className="h-1.5" />
                                    </div>
                                    {p.deadline && (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {t('Due')}: {new Date(p.deadline).toLocaleDateString()}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
        </PageTemplate>
    );
}
