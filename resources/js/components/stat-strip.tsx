import { cn } from '@/lib/utils';

export type StatTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger' | 'violet';

export interface StatItem {
    key: string;
    label: string;
    value: number | string;
    tone?: StatTone;
    /** Highlights the stat whose filter is currently applied. */
    active?: boolean;
    /** Makes the stat a filter shortcut. */
    onClick?: () => void;
}

const TONE_DOT: Record<StatTone, string> = {
    neutral: 'bg-gray-400',
    info: 'bg-blue-500',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-red-500',
    violet: 'bg-violet-500',
};

/**
 * One compact row of headline numbers. Replaces rows of separate icon cards:
 * a label and a value per stat, nothing else, so the page's own content stays
 * the focus. Scrolls sideways on narrow screens instead of stacking.
 */
export function StatStrip({ items, className }: { items: StatItem[]; className?: string }) {
    return (
        <div className={cn('flex overflow-x-auto rounded-xl border bg-card', className)}>
            {items.map((item) => {
                const content = (
                    <>
                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            {item.tone && <span className={cn('h-1.5 w-1.5 shrink-0 rounded-full', TONE_DOT[item.tone])} />}
                            <span className="truncate">{item.label}</span>
                        </span>
                        <span
                            className={cn(
                                'text-2xl font-semibold tabular-nums leading-tight',
                                item.tone === 'danger' && Number(item.value) > 0 && 'text-red-600 dark:text-red-400',
                            )}
                        >
                            {item.value}
                        </span>
                    </>
                );

                const cellClass = cn(
                    'relative flex min-w-[7.5rem] flex-1 flex-col gap-0.5 border-r px-4 py-3 text-left last:border-r-0',
                    item.active && 'bg-muted/60 after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:rounded-full after:bg-primary',
                );

                return item.onClick ? (
                    <button
                        key={item.key}
                        type="button"
                        onClick={item.onClick}
                        aria-pressed={!!item.active}
                        className={cn(cellClass, 'transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset')}
                    >
                        {content}
                    </button>
                ) : (
                    <div key={item.key} className={cellClass}>
                        {content}
                    </div>
                );
            })}
        </div>
    );
}
