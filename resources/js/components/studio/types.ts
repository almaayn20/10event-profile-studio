export interface StudioStat { value: string; label: string }

export interface StudioCrop { x: number; y: number; zoom: number }

export interface StudioImage { id: number; width: number; height: number; bytes: number; priority: 1 | 2 | 3 | null; crop: StudioCrop | null; url: string }

/** A picture's window on the page, in points: the part of its cell outside the white card; cx, cy = the middle of what shows. */
export interface StudioWindow { id: number; x: number; y: number; w: number; h: number; cx: number; cy: number }

export interface StudioProject {
    id: string;
    kind: 'project' | 'image';
    client: string;
    title: string;
    status: 'draft' | 'approved';
    in_profile: boolean;
    profile_page: number | null;
    profile_stale: boolean;
    thumb: string | null;
    images_count: number;
    updated_at: string | null;
    approved_at: string | null;
    // the editor's full copy
    description?: string;
    details_url?: string;
    details_label?: string;
    stats?: StudioStat[];
    layout_seed?: number;
    images?: StudioImage[];
    windows?: StudioWindow[];
}

export interface StudioVersion {
    id: number; name: string; pages: number; bytes: number; source: 'upload' | 'insert' | 'update' | 'remove' | 'move' | 'restore';
    note: string | null; project_id: number | null; created_at: string | null;
    thumb?: string; pages_urls?: string[];
}

export function formatBytes(n: number): string {
    if (n >= 1024 * 1024) return `${(n / 1024 / 1024).toFixed(1)} MB`;
    return `${Math.max(1, Math.round(n / 1024))} KB`;
}

export function formatDate(iso: string | null | undefined, locale: string): string {
    if (!iso) return '—';
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-nu-latn-ca-gregory' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}
