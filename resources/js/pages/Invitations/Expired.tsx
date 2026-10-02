import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertTriangle, Home } from 'lucide-react';

export default function Expired() {
    const { t } = useTranslation();
    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <Head title={t('Invitation Expired')} />
            
            <div className="max-w-md w-full space-y-8">
                <div className="text-center">
                    <AlertTriangle className="mx-auto h-12 w-12 text-red-600" />
                    <h2 className="mt-6 text-3xl font-extrabold text-gray-900">
                        {t('Invitation Expired')}
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        {t('This invitation link has expired and is no longer valid')}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-center text-red-600">
                            {t('Link Expired')}
                        </CardTitle>
                        <CardDescription className="text-center">
                            {t('The workspace invitation you\'re trying to access has expired.')} 
                            {t('Please contact the workspace owner to send you a new invitation.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-center">
                        <Link href="/">
                            <Button className="w-full">
                                <Home className="w-4 h-4 mr-2" />
                                {t('Go to Homepage')}
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}