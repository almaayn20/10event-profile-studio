import { usePage } from '@inertiajs/react';
import type { SharedProps } from './types';

function lookup(dict: Record<string, any>, key: string): any {
    return key.split('.').reduce<any>((node, part) => (node && typeof node === 'object' ? node[part] : undefined), dict);
}

export function interpolate(text: string, params?: Record<string, string | number | null | undefined>): string {
    // A placeholder with nothing to put in it is dropped, not printed: a stored error code can
    // reach the screen without the numbers it was written for, and ":needed" is not a sentence.
    const filled = text.replace(/:([a-zA-Z_]+)/g, (m, name) => {
        const v = params?.[name];
        return v !== undefined && v !== null ? String(v) : '';
    });

    return filled === text ? text : filled.replace(/\s{2,}/g, ' ').replace(/\s+([.،,؛;:!؟?])/g, '$1').trim();
}

/** t('ui.key', {param}) — reads the dictionary shared by Laravel for the active locale. */
export function useT() {
    const { t: dict, app } = usePage<SharedProps>().props;
    const t = (key: string, params?: Record<string, any>): string => {
        const value = lookup(dict, key);
        return typeof value === 'string' ? interpolate(value, params) : key;
    };
    const has = (key: string) => typeof lookup(dict, key) === 'string';
    const raw = (key: string) => lookup(dict, key);
    const num = (n: number | null | undefined, digits = 0) =>
        n === null || n === undefined ? '—' : new Intl.NumberFormat(app.locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', { maximumFractionDigits: digits }).format(n);
    return { t, has, raw, num, locale: app.locale, dir: app.dir };
}

export function errorText(dict: Record<string, any>, code: string, params?: Record<string, any>) {
    const entry = dict?.errors?.[code] ?? dict?.errors?.generic;
    return {
        title: interpolate(entry?.title ?? code, params),
        message: interpolate(entry?.message ?? '', params),
        action: interpolate(entry?.action ?? '', params),
    };
}
