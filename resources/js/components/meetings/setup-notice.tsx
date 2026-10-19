import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PlugZap } from 'lucide-react';
import { Button } from '@/components/ui/button';

const PROVIDERS = {
    zoom: { name: 'Zoom', anchor: '#zoom-settings' },
    google: { name: 'Google Meet', anchor: '#google-meet-settings' },
} as const;

/**
 * Shown on the Meetings pages until the workspace owner connects Zoom or Google
 * Meet (Settings › Integrations). Meetings already scheduled stay visible; new
 * ones need the connection, since that is what gives them a join link.
 */
export function MeetingSetupNotice({ providers, canConnect }: {
    /** The providers that are not connected yet. */
    providers: ('zoom' | 'google')[];
    /** The workspace owner, who holds the integration settings. */
    canConnect: boolean;
}) {
    const { t } = useTranslation();
    if (providers.length === 0) return null;
    const names = providers.map(p => PROVIDERS[p].name).join(` ${t('or')} `);

    return (
        <div className="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-900 dark:bg-amber-950/30">
            <PlugZap className="h-5 w-5 shrink-0 text-amber-600" />
            <div className="min-w-0 flex-1">
                <p className="font-medium text-amber-900 dark:text-amber-100">{t(':names is not connected yet.').replace(':names', names)}</p>
                <p className="text-amber-800/80 dark:text-amber-200/80">
                    {canConnect
                        ? t('Connect it in Settings › Integrations so new meetings get a join link. Meetings already scheduled are listed here.')
                        : t('Your workspace owner connects it in Settings › Integrations. Until then you can see meetings here, but new ones cannot be scheduled.')}
                </p>
            </div>
            {canConnect && (
                <Button asChild size="sm" className="shrink-0">
                    <Link href={route('settings') + PROVIDERS[providers[0]].anchor}>{t('Connect :name').replace(':name', PROVIDERS[providers[0]].name)}</Link>
                </Button>
            )}
        </div>
    );
}
