import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle, RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function ThankYou() {
    const { form } = usePage().props as any;
    return (
        <>
            <Head title="Thank You" />
            <div className="flex min-h-screen items-center justify-center bg-muted/30 px-4">
                <div className="rounded-2xl border bg-background p-10 text-center shadow-sm max-w-sm w-full">
                    <CheckCircle className="mx-auto mb-4 h-12 w-12 text-green-500" />
                    <h1 className="text-xl font-bold">Thank you!</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Your response to <strong>{form.title}</strong> has been submitted.
                    </p>
                    <div className="mt-6 flex flex-col gap-2">
                        <Button variant="outline" className="w-full gap-2"
                            onClick={() => router.visit(`/f/${form.token}`)}>
                            <RotateCcw className="h-4 w-4" /> Submit another response
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
