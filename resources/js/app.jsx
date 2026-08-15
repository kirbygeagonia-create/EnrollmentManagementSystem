import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import ErrorBoundary from '@/Components/ErrorBoundary';

const appName = import.meta.env.VITE_APP_NAME || 'SEAIT EMS';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ErrorBoundary>
                <App {...props} />
            </ErrorBoundary>
        );
    },
    progress: {
        color: '#ff7900',
        includeCSS: true,
        showSpinner: true,
    },
});

// ── Global Inertia Error Listener ──────────────────────────────────
// Intercept navigation errors (500, 503, etc.) and show a branded
// error page instead of the default browser error or white screen.
router.on('invalid', (event) => {
    event.preventDefault();
    console.error('[Inertia] Invalid response received', event.detail.response);
});
