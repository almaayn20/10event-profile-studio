import '@astryxdesign/core/reset.css';
import '@astryxdesign/core/astryx.css';
import '../css/fonts.css';
import '../css/tailwind.css';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Providers } from '@/components/layout/Providers';

createInertiaApp({
    title: (title) => (title ? `${title} · ` : '') + '10 Event',
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
