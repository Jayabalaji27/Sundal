import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Check, Copy } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface DemoLogins {
    password: string;
    accounts: { role: string; name: string; email: string }[];
}

/** Copies a value and briefly shows a tick; quietly does nothing where the clipboard is blocked. */
function CopyButton({ value, label, className }: { value: string; label: string; className?: string }) {
    const [copied, setCopied] = useState(false);
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            /* clipboard unavailable (e.g. plain http on another device) */
        }
    };

    return (
        <button
            type="button"
            onClick={copy}
            aria-label={label}
            title={label}
            className={cn('inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200', className)}
        >
            {copied ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
        </button>
    );
}

/**
 * Demo accounts on the login page (config/demo.php, shown while
 * DEMO_LOGIN_PANEL is on): one shared password, and a "Fill in" per account.
 */
export function DemoLoginsPanel({ logins, primaryColor, selectedEmail, onFill }: {
    logins: DemoLogins;
    primaryColor?: string;
    selectedEmail?: string;
    onFill: (email: string, password: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="rounded-xl border border-gray-200 bg-white text-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <p className="font-semibold text-gray-900 dark:text-gray-100">{t('Demo accounts')}</p>
                <div className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                    <span className="uppercase tracking-wide">{t('Password')}</span>
                    <code className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[13px] font-semibold text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                        {logins.password}
                    </code>
                    <CopyButton value={logins.password} label={t('Copy password')} />
                </div>
            </div>

            <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                {logins.accounts.map(account => {
                    const selected = selectedEmail === account.email;
                    return (
                        <li key={account.email} className={cn('flex items-center gap-3 px-4 py-2', selected && 'bg-gray-50 dark:bg-gray-700/40')}>
                            <span className="hidden w-[5.75rem] shrink-0 text-[11px] font-medium uppercase tracking-wide text-gray-500 sm:block dark:text-gray-400">
                                {t(account.role)}
                            </span>
                            <div className="min-w-0 flex-1">
                                {/* Phones: the role sits above the name instead of in its own column. */}
                                <p className="text-[10px] font-medium uppercase tracking-wide text-gray-500 sm:hidden dark:text-gray-400">{t(account.role)}</p>
                                <p className="truncate font-medium text-gray-900 dark:text-gray-100">{account.name}</p>
                                <p className="truncate font-mono text-xs text-gray-500 dark:text-gray-400" title={account.email}>{account.email}</p>
                            </div>
                            <CopyButton value={account.email} label={t('Copy email')} className="hidden sm:inline-flex" />
                            <button
                                type="button"
                                onClick={() => onFill(account.email, logins.password)}
                                className="shrink-0 rounded-md px-2 py-1 text-xs font-semibold transition-colors hover:bg-gray-100 dark:hover:bg-gray-700"
                                style={{ color: primaryColor }}
                                aria-label={t('Fill in :role login').replace(':role', `${t(account.role)} ${account.name}`)}
                            >
                                {selected ? t('Filled') : t('Fill in')}
                            </button>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
