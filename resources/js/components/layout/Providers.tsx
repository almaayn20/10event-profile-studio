import { InternationalizationProvider } from '@astryxdesign/core/i18n';
import { LinkProvider } from '@astryxdesign/core/Link';
import { Theme } from '@astryxdesign/core/theme';
import { ToastViewport, useToast } from '@astryxdesign/core/Toast';
import arSA from '@astryxdesign/core/locales/ar-SA.json';
import { Link as InertiaLink } from '@inertiajs/react';
import { forwardRef, useEffect, type ReactNode } from 'react';
import { errorText } from '@/lib/i18n';
import { theme } from '@/lib/theme';
import type { SharedProps } from '@/lib/types';

/** Inertia <Link> for every Astryx link/button with href; downloads and outside links stay plain links. */
const AppLink = forwardRef<HTMLAnchorElement, any>(function AppLink({ href, children, target, download, ...rest }, ref) {
    const plain = typeof href === 'string' && (/^https?:\/\//.test(href) || href.startsWith('mailto:') || target === '_blank' || download
        || /\/(download|pdf)(\?|$)/.test(href));
    if (plain) {
        return <a ref={ref} href={href} target={target} download={download} {...rest}>{children}</a>;
    }
    return <InertiaLink ref={ref as any} href={href} {...rest}>{children}</InertiaLink>;
});

function lookup(dict: any, key: string): string | undefined {
    const v = key.replace(/^ui\./, '').split('.').reduce((n, p) => (n && typeof n === 'object' ? n[p] : undefined), dict);
    return typeof v === 'string' ? v : undefined;
}

function FlashToasts({ page }: { page: SharedProps }) {
    const toast = useToast();
    const { flash, t } = page;
    useEffect(() => {
        if (flash?.success) toast({ body: lookup(t, flash.success) ?? flash.success, type: 'info', uniqueID: `s-${flash.success}` });
        if (flash?.error?.code) {
            const e = errorText(t, flash.error.code, flash.error.params);
            toast({ body: <span><b>{e.title}</b><br />{e.message} {e.action}</span>, type: 'error', uniqueID: `e-${flash.error.code}` });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.success, flash?.error?.code, JSON.stringify(flash?.error?.params ?? {})]);
    return null;
}

export function Providers({ page, children }: { page: SharedProps; children: ReactNode }) {
    const locale = page.app.locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en';   // Western digits, Arabic words
    useEffect(() => {
        document.documentElement.lang = page.app.locale;
        document.documentElement.dir = page.app.dir;
    }, [page.app.locale, page.app.dir]);

    return (
        <InternationalizationProvider locale={locale} messages={{ 'ar-SA': arSA as any }} dir={page.app.dir}>
            <Theme theme={theme} mode="light">
                <LinkProvider component={AppLink}>
                    <ToastViewport position="bottomStart">
                        <FlashToasts page={page} />
                        {children}
                    </ToastViewport>
                </LinkProvider>
            </Theme>
        </InternationalizationProvider>
    );
}
