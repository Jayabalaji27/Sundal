import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface ErrorPageProps {
    status: number;
}

const MESSAGES: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Forbidden',
        description: "You don't have permission to access this page.",
    },
    404: {
        title: 'Page Not Found',
        description: "The page you're looking for doesn't exist or may have been moved.",
    },
    419: {
        title: 'Page Expired',
        description: 'Your session expired. Please try again.',
    },
    500: {
        title: 'Server Error',
        description: 'Something went wrong on our end. Please try again shortly.',
    },
    503: {
        title: 'Service Unavailable',
        description: "We're performing maintenance. Please check back soon.",
    },
};

export default function ErrorPage({ status }: ErrorPageProps) {
    const { t } = useTranslation();
    const { title, description } = MESSAGES[status] ?? {
        title: 'Unexpected Error',
        description: 'An unexpected error occurred.',
    };

    return (
        <>
            <Head title={t(title)} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-center text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="text-6xl font-semibold text-[#19140035] dark:text-[#3E3E3A]">{status}</div>
                <h1 className="mt-4 text-2xl font-medium">{t(title)}</h1>
                <p className="mt-2 max-w-md text-sm text-[#706f6c] dark:text-[#A1A09A]">{t(description)}</p>
                <Link
                    href={route('dashboard')}
                    className="mt-6 inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                >
                    {t('Back to Dashboard')}
                </Link>
            </div>
        </>
    );
}
