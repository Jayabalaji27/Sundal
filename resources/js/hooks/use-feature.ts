import { usePage } from '@inertiajs/react';

/**
 * Returns true when the named feature flag is enabled on the server.
 * Feature flags come from config/features.php, shared via HandleInertiaRequests.
 */
export function useFeature(flag: string): boolean {
    const { features } = usePage().props as any;
    return features?.[flag] === true;
}
