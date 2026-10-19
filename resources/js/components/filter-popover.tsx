import * as PopoverPrimitive from '@radix-ui/react-popover';
import { Filter } from 'lucide-react';
import { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

/**
 * A "Filters" button that opens the page's less-used filters in a panel, so the
 * toolbar itself only carries search. Shows how many filters are applied.
 */
export function FilterPopover({
    activeCount = 0,
    onReset,
    children,
}: {
    activeCount?: number;
    onReset?: () => void;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <PopoverPrimitive.Root>
            <PopoverPrimitive.Trigger asChild>
                <Button variant={activeCount > 0 ? 'secondary' : 'outline'} size="sm" className="h-9">
                    <Filter className="h-3.5 w-3.5" />
                    {t('Filters')}
                    {activeCount > 0 && (
                        <span className="ml-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs text-primary-foreground">
                            {activeCount}
                        </span>
                    )}
                </Button>
            </PopoverPrimitive.Trigger>
            <PopoverPrimitive.Portal>
                <PopoverPrimitive.Content
                    align="start"
                    sideOffset={6}
                    className="z-50 w-64 space-y-4 rounded-lg border bg-popover p-4 text-popover-foreground shadow-md outline-none"
                >
                    {children}
                    {onReset && (
                        <Button variant="ghost" size="sm" className="w-full" onClick={onReset} disabled={activeCount === 0}>
                            {t('Reset Filters')}
                        </Button>
                    )}
                </PopoverPrimitive.Content>
            </PopoverPrimitive.Portal>
        </PopoverPrimitive.Root>
    );
}
