import { ButtonHTMLAttributes } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface AuthButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    processing?: boolean;
    tabIndex?: number;
    children: React.ReactNode;
}

/** The full-width primary action on the auth pages (44px tall for touch). */
export default function AuthButton({
    processing = false,
    tabIndex,
    children,
    className = '',
    disabled,
    ...props
}: AuthButtonProps) {
    return (
        <Button
            {...props}
            type={props.type || 'submit'}
            size="lg"
            className={cn('w-full', className)}
            tabIndex={tabIndex}
            disabled={disabled}
            loading={processing}
            loadingText={children}
        >
            {children}
        </Button>
    );
}
