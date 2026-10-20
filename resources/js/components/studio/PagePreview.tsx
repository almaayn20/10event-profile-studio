import { useEffect, useRef, useState } from 'react';
import { xsrfHeader } from './compress';

export type Fit = 'ok' | 'shrunk' | 'cut';

/** How far the client and the title had to be set smaller to stay inside the card (1 = their own size). */
export interface HeadFit { client: number; title: number }

/**
 * The page exactly as it prints: the server renders the same template Chrome prints, and it is shown here at the
 * width available (the page is 960 × 540 pt = 1280 × 720 CSS px, scaled down as a whole).
 */
export function PagePreview({ url, data, version, onFit, onPick, onHeadFit }: {
    url: string; data: Record<string, any>; version: string; onFit?: (f: Fit) => void; onPick?: (imageId: number) => void; onHeadFit?: (f: HeadFit) => void;
}) {
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

    const pick = useRef(onPick);
    pick.current = onPick;

    // the page steps a long description down by itself; read back whether it had to, once it has settled
    const onLoad = () => {
        const doc = frame.current?.contentDocument;
        if (doc && onPick) pickable(doc, (id) => pick.current?.(id));
        if (!doc || (!onFit && !onHeadFit)) return;
        let tries = 0;
        const read = () => {
            const root = doc.documentElement;
            if ((root.dataset.fitted !== '1' || root.dataset.titled !== '1') && tries++ < 40) return window.setTimeout(read, 100);
            onHeadFit?.({ client: Number(root.dataset.clientScale ?? 1) || 1, title: Number(root.dataset.titleScale ?? 1) || 1 });
            if (!onFit) return;
            const desc = doc.getElementById('desc');
            onFit(root.dataset.overflow === '1' ? 'cut' : desc?.style.fontSize ? 'shrunk' : 'ok');
        };
        read();
    };

    return (
        <div ref={box} className={`studio-preview${onPick ? ' is-pickable' : ''}`} style={{ height: 720 * scale }} aria-busy={loading}>
            <iframe ref={frame} title="preview" srcDoc={html} onLoad={onLoad} scrolling="no" tabIndex={-1}
                style={{ width: 1280, height: 720, transform: `scale(${scale})`, transformOrigin: 'top left' }} />
            {loading && <span className="studio-preview-busy" />}
        </div>
    );
}

/**
 * A picture on the page answers a click (and shows it can) wherever it is in view: over the white card or the
 * text nothing happens. The card is the page's own path, asked directly whether the point falls inside it.
 */
function pickable(doc: Document, onPick: (id: number) => void) {
    const card = doc.getElementById('card') as SVGPathElement | null;
    const cells = Array.from(doc.querySelectorAll<HTMLElement>('.cell[data-image]'));
    let hovered: HTMLElement | null = null;
    const at = (e: MouseEvent) => {
        const pt = new DOMPoint(e.clientX * 0.75, e.clientY * 0.75);    // CSS px → the page's points
        if (card?.isPointInFill(pt)) return null;
        return cells.find((c) => {
            const r = c.getBoundingClientRect();
            return e.clientX >= r.left && e.clientX < r.right && e.clientY >= r.top && e.clientY < r.bottom;
        }) ?? null;
    };
    const mark = (cell: HTMLElement | null) => {
        if (cell === hovered) return;
        if (hovered) hovered.style.boxShadow = '';
        hovered = cell;
        if (cell) cell.style.boxShadow = 'inset 0 0 0 4px rgba(176, 131, 94, .95)';
        doc.body.style.cursor = cell ? 'pointer' : '';
    };
    doc.addEventListener('mousemove', (e) => mark(at(e)));
    doc.addEventListener('mouseleave', () => mark(null));
    doc.addEventListener('click', (e) => {
        const cell = at(e);
        if (cell) onPick(Number(cell.dataset.image));
    });
}
