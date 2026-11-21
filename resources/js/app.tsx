import '@astryxdesign/core/reset.css';
import '@astryxdesign/core/astryx.css';
import '../css/fonts.css';
import '../css/tailwind.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Providers } from '@/components/layout/Providers';

// the brand in the tab's title: 10 Event or 10 Gift, by the edition the server says it is
const brand = (() => {
    try {
        // Inertia puts the first page in <script data-page type="application/json"> (or, older, in #app[data-page])
        const script = document.querySelector('script[data-page]');
        const raw = script?.textContent || (document.getElementById('app')?.dataset.page ?? '');
        return JSON.parse(raw)?.props?.app?.brand ?? '10 Event';
    } catch {
        return '10 Event';
    }
})();

createInertiaApp({
    title: (title) => (title ? `${title} · ` : '') + brand,
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx', { eager: false });
        const page = pages[`./pages/${name}.tsx`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return page() as any;
    },
    setup({ el, App, props }) {
        createRoot(el!).render(
            <App {...props}>
                {({ Component, props: pageProps, key }) => (
                    <Providers page={pageProps as any}>
                        <Component key={key} {...pageProps} />
                    </Providers>
                )}
            </App>,
        );
    },
    progress: { color: '#AF4F12', delay: 200 },
});
