import { useToast } from '@astryxdesign/core/Toast';
import { usePage } from '@inertiajs/react';
import { errorText } from '@/lib/i18n';
import type { SharedProps } from '@/lib/types';

/**
 * Checks file sizes in the browser before anything is sent. A file over the limit never reaches the server
 * (which would refuse the whole request with a bare 413); the user gets the same Arabic message the server uses.
 * Returns only the files that fit. `maxMb` defaults to the platform's upload limit.
 */
export function useUploadGuard(maxMb?: number) {
    const toast = useToast();
    const { props } = usePage<SharedProps>();
    const limit = maxMb ?? props.app.uploadMaxMb ?? 25;
    return (files: File[]): File[] => {
        const fit = files.filter((f) => f.size <= limit * 1024 * 1024);
        if (fit.length < files.length) {
            const e = errorText(props.t, 'file_too_large', { max: limit });
            toast({ body: <span><b>{e.title}</b><br />{e.message} {e.action}</span>, type: 'error', uniqueID: 'e-file_too_large' });
        }
        return fit;
    };
}
