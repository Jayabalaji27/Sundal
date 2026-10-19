import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Check, ChevronDown, Columns3, LayoutGrid, Rows3, Sheet as SheetIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { hasPermission } from '@/utils/authorization';
import { cn } from '@/lib/utils';

export type BoardView = 'detail' | 'plain' | 'sheet';
export const BOARD_VIEWS: BoardView[] = ['detail', 'plain', 'sheet'];

/**
 * The view menu of the project Board and the Tasks page: Detail cards, Plain
 * cards (title and owner only) or Sheet (a sortable table), plus Manage stages
 * for people allowed to change the columns.
 */
export function BoardViewMenu({ view, onChange, className }: { view: BoardView; onChange: (view: BoardView) => void; className?: string }) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const views: Record<BoardView, { label: string; hint: string; icon: typeof Rows3 }> = {
        detail: { label: t('Detail view'), hint: t('Cards with priority, severity, points and due date'), icon: LayoutGrid },
        plain: { label: t('Plain view'), hint: t('Compact cards: title and owner'), icon: Rows3 },
        sheet: { label: t('Sheet view'), hint: t('A table you can sort, and change stages in'), icon: SheetIcon },
    };
    const CurrentIcon = views[view].icon;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button size="sm" variant="outline" className={cn('h-9', className)} aria-label={t('Board view')}>
                    <CurrentIcon className="h-4 w-4" />
                    {views[view].label}
                    <ChevronDown className="h-3.5 w-3.5 opacity-70" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72">
                {BOARD_VIEWS.map(key => {
                    const option = views[key];
                    return (
                        <DropdownMenuItem key={key} onClick={() => onChange(key)} className="items-start gap-3 py-2">
                            <option.icon className="mt-0.5 h-4 w-4 shrink-0" />
                            <span className="min-w-0 flex-1">
                                <span className="block font-medium">{option.label}</span>
                                <span className="block text-xs text-muted-foreground">{option.hint}</span>
                            </span>
                            {view === key && <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />}
                        </DropdownMenuItem>
                    );
                })}
                {/* The columns are the workspace's stages (for tasks and bugs alike). */}
                {hasPermission(auth?.permissions || [], 'task_manage_stages') && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild className="gap-3 py-2">
                            <Link href={route('task-stages.index')}>
                                <Columns3 className="h-4 w-4 shrink-0" />
                                <span className="font-medium">{t('Manage stages')}</span>
                            </Link>
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
