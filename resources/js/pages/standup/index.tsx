import { useState } from 'react';
import { router } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { CheckCircle, AlertTriangle, Clock, Copy, Check, Calendar } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { todayLocalDateString } from '@/utils/formHelpers';

interface StandupItem { project: string; item: string; }
interface TodayTask  { title: string; project: string; priority: string; progress: number; end_date: string|null; }
interface StandupData {
    yesterday: StandupItem[];
    today: TodayTask[];
    blockers: string[];
    has_data: boolean;
}
interface Standup { user: { id: number; name: string; avatar: string|null }; data: StandupData; }

const priorityColor: Record<string,string> = {
    critical: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400',
    high:     'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-400',
    medium:   'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-400',
    low:      'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
};

function UserStandupCard({ standup, date }: { standup: Standup; date: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const { user, data } = standup;

    const initials = user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);

    const copyText = async () => {
        const lines = [
            `📋 ${user.name} — ${date}`,
            '',
            '✅ Yesterday:',
            ...(data.yesterday.length ? data.yesterday.map(y => `  • [${y.project}] ${y.item}`) : ['  • No activity logged']),
            '',
            '🎯 Today:',
            ...(data.today.length ? data.today.map(t => `  • [${t.project}] ${t.title} (${t.progress}%)`) : ['  • No tasks planned']),
            '',
            '🚧 Blockers:',
            ...(data.blockers.length ? data.blockers.map(b => `  • ${b}`) : ['  • None']),
        ];
        try {
            await navigator.clipboard.writeText(lines.join('\n'));
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error(t('Could not copy to clipboard — your browser may have blocked clipboard access.'));
        }
    };

    if (!data.has_data && data.blockers.length === 0) {
        return (
            <Card className="opacity-60">
                <CardContent className="p-4 flex items-center gap-3">
                    <Avatar className="h-9 w-9">
                        {user.avatar && <AvatarImage src={user.avatar} />}
                        <AvatarFallback className="text-xs">{initials}</AvatarFallback>
                    </Avatar>
                    <div>
                        <p className="font-medium text-sm">{user.name}</p>
                        <p className="text-xs text-muted-foreground">{t('No activity recorded')}</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-3 flex flex-row items-start justify-between space-y-0">
                <div className="flex items-center gap-3">
                    <Avatar className="h-9 w-9">
                        {user.avatar && <AvatarImage src={user.avatar} />}
                        <AvatarFallback className="text-xs font-medium">{initials}</AvatarFallback>
                    </Avatar>
                    <CardTitle className="text-sm font-semibold">{user.name}</CardTitle>
                </div>
                <Button size="sm" variant="ghost" onClick={copyText} className="h-7 px-2 text-xs gap-1.5">
                    {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                    {copied ? t('Copied') : t('Copy')}
                </Button>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Yesterday */}
                <div>
                    <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">
                        <CheckCircle className="h-3.5 w-3.5 text-emerald-500" /> {t('Yesterday')}
                    </div>
                    {data.yesterday.length === 0
                        ? <p className="text-xs text-muted-foreground pl-5">{t('No activity logged')}</p>
                        : <ul className="space-y-1 pl-5">
                            {data.yesterday.map((y, i) => (
                                <li key={i} className="text-xs flex items-start gap-1.5">
                                    <span className="text-muted-foreground shrink-0 font-medium">[{y.project}]</span>
                                    <span>{y.item}</span>
                                </li>
                            ))}
                        </ul>
                    }
                </div>

                {/* Today */}
                <div>
                    <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">
                        <Clock className="h-3.5 w-3.5 text-blue-500" /> {t('Today')}
                    </div>
                    {data.today.length === 0
                        ? <p className="text-xs text-muted-foreground pl-5">{t('No tasks assigned')}</p>
                        : <ul className="space-y-1.5 pl-5">
                            {data.today.map((task, i) => (
                                <li key={i} className="text-xs flex items-center gap-2">
                                    <Badge className={`text-[10px] px-1.5 py-0 h-4 ${priorityColor[task.priority] ?? ''}`}>
                                        {task.priority}
                                    </Badge>
                                    <span className="flex-1">[{task.project}] {task.title}</span>
                                    <span className="text-muted-foreground shrink-0">{task.progress}%</span>
                                </li>
                            ))}
                        </ul>
                    }
                </div>

                {/* Blockers */}
                {data.blockers.length > 0 && (
                    <div>
                        <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">
                            <AlertTriangle className="h-3.5 w-3.5 text-red-500" /> {t('Blockers')}
                        </div>
                        <ul className="space-y-1 pl-5">
                            {data.blockers.map((b, i) => (
                                <li key={i} className="text-xs text-red-600 dark:text-red-400">{b}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function StandupIndex({ standups, date, projects }: {
    standups: Standup[];
    date: string;
    projects: { id: number; title: string }[];
}) {
    const { t } = useTranslation();
    const [selectedDate, setSelectedDate] = useState(date);

    const loadDate = (d: string) => {
        setSelectedDate(d);
        router.get(route('standup.index'), { date: d }, { preserveState: true });
    };

    const copyAll = async () => {
        const active = standups.filter(s => s.data.has_data || s.data.blockers.length > 0);
        const lines = active.flatMap(s => {
            const d = s.data;
            return [
                `👤 ${s.user.name}`,
                '  Yesterday: ' + (d.yesterday.length ? d.yesterday.map(y => `[${y.project}] ${y.item}`).join(', ') : 'nothing logged'),
                '  Today: '     + (d.today.length     ? d.today.map(t => t.title).join(', ')                      : 'no tasks'),
                '  Blockers: '  + (d.blockers.length  ? d.blockers.join(', ')                                      : 'none'),
                '',
            ];
        });
        try {
            await navigator.clipboard.writeText([`🗓️ Standup — ${selectedDate}`, '', ...lines].join('\n'));
            toast.success(t('Full standup copied!'));
        } catch {
            toast.error(t('Could not copy to clipboard — your browser may have blocked clipboard access.'));
        }
    };

    const active = standups.filter(s => s.data.has_data || s.data.blockers.length > 0);
    const inactive = standups.filter(s => !s.data.has_data && s.data.blockers.length === 0);

    return (
        <PageTemplate title={t('Standup Bot')} subtitle={t('Auto-generated daily standups for your team')}>
            {/* Toolbar */}
            <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-muted-foreground" />
                    <Input type="date" value={selectedDate} onChange={e => loadDate(e.target.value)}
                        className="w-44 h-9 text-sm" />
                    <Button variant="outline" size="sm" onClick={() => loadDate(todayLocalDateString())}>
                        {t('Today')}
                    </Button>
                </div>
                {active.length > 0 && (
                    <Button size="sm" onClick={copyAll} className="gap-2">
                        <Copy className="h-3.5 w-3.5" />
                        {t('Copy All Standups')}
                    </Button>
                )}
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 gap-3 mb-6 sm:grid-cols-3">
                <Card><CardContent className="p-3 text-center">
                    <div className="text-2xl font-bold">{standups.length}</div>
                    <div className="text-xs text-muted-foreground">{t('Team Members')}</div>
                </CardContent></Card>
                <Card><CardContent className="p-3 text-center">
                    <div className="text-2xl font-bold text-emerald-600">{active.length}</div>
                    <div className="text-xs text-muted-foreground">{t('Active')}</div>
                </CardContent></Card>
                <Card><CardContent className="p-3 text-center">
                    <div className="text-2xl font-bold text-red-500">
                        {standups.reduce((n, s) => n + s.data.blockers.length, 0)}
                    </div>
                    <div className="text-xs text-muted-foreground">{t('Blockers')}</div>
                </CardContent></Card>
            </div>

            {/* Cards */}
            {active.length === 0 && (
                <Card><CardContent className="p-10 text-center text-muted-foreground">
                    {t('No activity recorded for this date. Try a different date.')}
                </CardContent></Card>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                {active.map(s => <UserStandupCard key={s.user.id} standup={s} date={selectedDate} />)}
                {inactive.map(s => <UserStandupCard key={s.user.id} standup={s} date={selectedDate} />)}
            </div>
        </PageTemplate>
    );
}
