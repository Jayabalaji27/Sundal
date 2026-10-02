import { Head } from '@inertiajs/react';
import { XCircle } from 'lucide-react';

export default function FormClosed() {
    return (
        <>
            <Head title="Form Closed" />
            <div className="flex min-h-screen items-center justify-center bg-muted/30 px-4">
                <div className="rounded-2xl border bg-background p-10 text-center shadow-sm max-w-sm w-full">
                    <XCircle className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                    <h1 className="text-xl font-bold">Form Closed</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        This form is no longer accepting responses.
                    </p>
                </div>
            </div>
        </>
    );
}
