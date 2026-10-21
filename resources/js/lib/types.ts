export type Locale = 'ar' | 'en';

export interface SharedProps {
    app: { name: string; locale: Locale; dir: 'rtl' | 'ltr'; uploadMaxMb?: number; statsShown: boolean };
    t: Record<string, any>;
    flash: { success?: string | null; error?: { code: string; params?: Record<string, any> } | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}
