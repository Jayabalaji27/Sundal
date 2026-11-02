import { type ClassValue, clsx } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// The design-system type and shadow utilities (resources/css/design-system/theme.css).
// Without registering them, tailwind-merge reads `text-ds-card-title` as a text
// colour and drops it next to `text-ds-fg`, and keeps `shadow-ds-xs` next to `shadow-none`.
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: ['ds-page-title', 'ds-section-title', 'ds-card-title', 'ds-body', 'ds-meta'],
            shadow: ['ds-xs', 'ds-sm', 'ds-md'],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatDateTime(date: string | Date): string {
    if (!date) return '';
    
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    
    return dateObj.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}
