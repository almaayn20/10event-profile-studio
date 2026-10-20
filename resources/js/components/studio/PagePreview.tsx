import { useEffect, useRef, useState } from 'react';
import { xsrfHeader } from './compress';

export type Fit = 'ok' | 'shrunk' | 'cut';

/**
 * The page exactly as it prints: the server renders the same template Chrome prints, and it is shown here at the
 * width available (the page is 960 × 540 pt = 1280 × 720 CSS px, scaled down as a whole).
 */
export function PagePreview({ url, data, version, onFit }: { url: string; data: Record<string, any>; version: string; onFit?: (f: Fit) => void }) {
    const box = useRef<HTMLDivElement>(null);
    const frame = useRef<HTMLIFrameElement>(null);
    const [scale, setScale] = useState(0.5);
    const [html, setHtml] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const el = box.current;
        if (!el) return;
        const ro = new ResizeObserver(() => setScale(el.clientWidth / 1280));
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const body = JSON.stringify(data);
    useEffect(() => {
        const ctl = new AbortController();
        setLoading(true);
        const id = window.setTimeout(() => {
            fetch(url, {
                method: 'POST', signal: ctl.signal, credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest', ...xsrfHeader() },
                body,
            })
                .then((r) => (r.ok ? r.text() : Promise.reject(r.status)))
                .then((h) => { setHtml(h); setLoading(false); })
                .catch(() => {});
        }, 350);
        return () => { ctl.abort(); window.clearTimeout(id); };
    }, [url, body, version]);

    // the page steps a long description down by itself; read back whether it had to, once it has settled
    const onLoad = () => {
        const doc = frame.current?.contentDocument;
        if (!doc || !onFit) return;
        let tries = 0;
        const read = () => {
            const root = doc.documentElement;
            if (root.dataset.fitted !== '1' && tries++ < 40) return window.setTimeout(read, 100);
            const desc = doc.getElementById('desc');
            onFit(root.dataset.overflow === '1' ? 'cut' : desc?.style.fontSize ? 'shrunk' : 'ok');
        };
        read();
    };

    return (
        <div ref={box} className="studio-preview" style={{ height: 720 * scale }} aria-busy={loading}>
            <iframe ref={frame} title="preview" srcDoc={html} onLoad={onLoad} scrolling="no" tabIndex={-1}
                style={{ width: 1280, height: 720, transform: `scale(${scale})`, transformOrigin: 'top left' }} />
            {loading && <span className="studio-preview-busy" />}
        </div>
    );
}
