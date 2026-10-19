import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type ApprovalTab = 'timesheets' | 'expenses';
export type ApprovalCounts = Partial<Record<ApprovalTab, number>>;
export type ApprovalStatus = 'pending' | 'history';

const TAB_ROUTES: Record<ApprovalTab, string> = {
    timesheets: 'timesheet-approvals.index',
    expenses: 'expense-approvals.index',
};

interface Props {
    active: ApprovalTab;
    counts: ApprovalCounts;
    status: ApprovalStatus;
    search: string;
    onChange: (params: { status?: ApprovalStatus; search?: string }) => void;
}

/**
 * The top of the Approvals inbox: one tab per queue the user can review (with
 * what's waiting), a Pending / History switch and a search that runs as you type.
 */
export function ApprovalsHeader({ active, counts, status, search, onChange }: Props) {
    const { t } = useTranslation();
    const [term, setTerm] = useState(search);
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const tabs = (Object.keys(TAB_ROUTES) as ApprovalTab[]).filter(tab => counts[tab] !== undefined);
    const labels: Record<ApprovalTab, string> = { timesheets: t('Timesheets'), expenses: t('Expenses') };

    useEffect(() => () => clearTimeout(timer.current), []);

    const onSearch = (value: string) => {
        setTerm(value);
        clearTimeout(timer.current);
        timer.current = setTimeout(() => onChange({ search: value.trim() }), 400);
    };

    return (
        <div className="space-y-4">
            {tabs.length > 1 && (
                <div className="flex gap-1 border-b">
                    {tabs.map(tab => (
                        <button
                            key={tab}
                            type="button"
                            onClick={() => tab !== active && router.get(route(TAB_ROUTES[tab]))}
                            className={cn(
                                '-mb-px flex items-center gap-2 border-b-2 px-3 pb-2 text-sm font-medium transition-colors',
                                tab === active ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {labels[tab]}
                            {!!counts[tab] && (
                                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{counts[tab]}</span>
                            )}
                        </button>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="inline-flex rounded-md border p-0.5">
                    {(['pending', 'history'] as ApprovalStatus[]).map(value => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => value !== status && onChange({ status: value })}
                            className={cn(
                                'rounded px-3 py-1 text-sm font-medium transition-colors',
                                value === status ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {value === 'pending' ? t('Pending') : t('History')}
                        </button>
                    ))}
                </div>
                <div className="relative w-full sm:w-72">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input value={term} onChange={e => onSearch(e.target.value)} placeholder={t('Search by name...')} className="pl-9" />
                </div>
            </div>
        </div>
    );
}
