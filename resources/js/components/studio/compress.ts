/**
 * Pictures are made lighter in the browser before they are sent: no side longer than 2400 px, JPEG at 0.88.
 * A phone photo of 6–12 MB arrives as a few hundred KB, upright (the browser applies the camera's rotation),
 * and still prints sharply at full page width. The server re-checks and stores its own copy.
 */
const MAX_SIDE = 2400;

export async function lighten(file: File): Promise<File> {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' } as ImageBitmapOptions);
        const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
        if (scale === 1 && file.type === 'image/jpeg' && file.size < 1.5 * 1024 * 1024) {
            bitmap.close();
            return file;                    // already light: send as it is
        }
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const ctx = canvas.getContext('2d')!;
        ctx.fillStyle = '#fff';             // a transparent PNG lands on white, as on the printed page
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        const blob = await new Promise<Blob | null>((ok) => canvas.toBlob(ok, 'image/jpeg', 0.88));
        if (!blob) return file;
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch {
        return file;                        // the server can still read most formats the browser could not
    }
}

export function xsrfHeader(): Record<string, string> {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? { 'X-XSRF-TOKEN': decodeURIComponent(m[1]) } : {};
}
