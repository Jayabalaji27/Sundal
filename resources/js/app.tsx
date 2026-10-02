// RTL is now handled by app.blade.php and LayoutContext

import '../css/app.css';
import '../css/dark-mode.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Suspense } from 'react';
import { LayoutProvider } from './contexts/LayoutContext';
import { SidebarProvider } from './contexts/SidebarContext';
import { BrandProvider } from './contexts/BrandContext';
import { ModalStackProvider } from './contexts/ModalStackContext';

import { initializeTheme } from './hooks/use-appearance';
import { CustomToast } from './components/custom-toast';
import { ErrorBoundary } from './components/error-boundary';
import { initializeGlobalSettings } from './utils/globalSettings';
import { applyRTLFromCookies } from './utils/rtl-utils';
import i18n from './i18n';
import './utils/axios-config';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        try { (window as any).page = props.initialPage; } catch (e) {}
        try { (window as any).isDemo = props.initialPage.props?.is_demo || false; } catch (e) {}

        const globalSettings = props.initialPage.props.globalSettings || {};
        if (Object.keys(globalSettings).length > 0) initializeGlobalSettings(globalSettings);
        applyRTLFromCookies(globalSettings);

        const applyDir = (settings?: any) => {
            const dir = ['ar', 'he'].includes(i18n.language) ? 'rtl' : 'ltr';
            document.documentElement.dir = dir;
            document.body.dir = dir;
            document.documentElement.setAttribute('dir', dir);
            if (dir === 'rtl') {
                document.documentElement.classList.add('rtl');
                document.body.classList.add('rtl');
            } else {
                document.documentElement.classList.remove('rtl');
                document.body.classList.remove('rtl');
            }
            if (settings) applyRTLFromCookies(settings);
        };

        applyDir();

        const renderApp = (appProps: any) => {
            const gs = appProps.initialPage.props.globalSettings || {};
            const user = appProps.initialPage.props.auth?.user;
            root.render(
                <ModalStackProvider>
                    <LayoutProvider>
                        <SidebarProvider>
                            <BrandProvider globalSettings={gs} user={user}>
                                <ErrorBoundary fallback={<div style={{padding:'2rem',textAlign:'center'}}>Something went wrong. Please refresh the page.</div>}>
                                    <Suspense fallback={null}>
                                        <App {...appProps} />
                                    </Suspense>
                                </ErrorBoundary>
                                <CustomToast />
                            </BrandProvider>
                        </SidebarProvider>
                    </LayoutProvider>
                </ModalStackProvider>
            );
        };

        // Initial render only — Inertia handles subsequent page swaps internally
        renderApp(props);

        // Only handle side effects on navigation, not re-render
        router.on('navigate', (event) => {
            try {
                (window as any).page = event.detail.page;
                const newSettings = event.detail.page.props.globalSettings || {};
                setTimeout(() => {
                    applyDir(newSettings);
                    if (newSettings?.layoutDirection) {
                        window.dispatchEvent(new CustomEvent('layoutDirectionChanged', {
                            detail: { direction: newSettings.layoutDirection }
                        }));
                    }
                }, 0);
            } catch (e) {
                console.error('Navigation error:', e);
            }
        });

        i18n.on('languageChanged', () => applyDir());
    },
    progress: {
        color: 'var(--primary, #22c55e)',
        includeCSS: true,
        showSpinner: false,
        delay: 100,
    },
});


initializeTheme();
