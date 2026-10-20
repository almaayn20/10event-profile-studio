import { Button } from '@astryxdesign/core/Button';
import { Slider } from '@astryxdesign/core/Slider';
import { useRef, useState } from 'react';
import { ScrollDialog } from '@/components/ui/ScrollDialog';
import { useT } from '@/lib/i18n';
import type { StudioCrop, StudioImage, StudioWindow } from './types';

const BROWN = '#AF4F12';

/**
 * The same sum as PageGeometry::placeImage: the picture covers its window, times the zoom, with the point (fx, fy)
 * on the middle of what shows, as near as the picture's edges allow. All in points, relative to the window.
 */
export function placeImage(win: StudioWindow, iw: number, ih: number, fx: number, fy: number, zoom: number) {
    const s = Math.max(win.w / iw, win.h / ih) * Math.max(1, zoom);
    const w = iw * s;
    const h = ih * s;
    const x = Math.min(0, Math.max(win.w - w, win.cx - fx * w));
    const y = Math.min(0, Math.max(win.h - h, win.cy - fy * h));
    return { x, y, w, h };
}

/**
 * Choose the part of a picture that shows on the page: drag it, zoom it. The frame is the picture's window on the
 * page (only the part outside the white card); what lies under the card is veiled, what falls outside the window is
 * faded, so the view is exactly the page's.
 */
export function CropDialog({ image, win, card, busy, onSave, onClose }: {
    image: StudioImage; win: StudioWindow; card: string; busy: boolean;
    onSave: (crop: StudioCrop | null) => void; onClose: () => void;
}) {
    const { t } = useT();
    const [zoom, setZoom] = useState(image.crop?.zoom ?? 1);
    const [focus, setFocus] = useState({ x: image.crop?.x ?? 0.5, y: image.crop?.y ?? 0.5 });
    const drag = useRef<{ px: number; py: number; left: number; top: number } | null>(null);

    // the window as large as the dialog allows (points → screen pixels)
    const k = Math.min(620 / win.w, 300 / win.h);
    const at = placeImage(win, image.width, image.height, focus.x, focus.y, zoom);
    const MARGIN = 40;

    /** Back from where the picture is drawn to the point that sits on the view's middle. */
    const focusFrom = (left: number, top: number, z: number) => {
        const p = placeImage(win, image.width, image.height, 0.5, 0.5, z);
        const l = Math.min(0, Math.max(win.w - p.w, left));
        const tp = Math.min(0, Math.max(win.h - p.h, top));
        return { x: (win.cx - l) / p.w, y: (win.cy - tp) / p.h };
    };

    const onDown = (e: React.PointerEvent) => {
        (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
        drag.current = { px: e.clientX, py: e.clientY, left: at.x, top: at.y };
    };
    const onMove = (e: React.PointerEvent) => {
        const d = drag.current;
        if (!d) return;
        setFocus(focusFrom(d.left + (e.clientX - d.px) / k, d.top + (e.clientY - d.py) / k, zoom));
    };
    const onUp = () => { drag.current = null; };
    // zooming keeps the middle of the view where it is
    const clampZoom = (z: number) => Math.round(Math.max(1, Math.min(4, z)) * 100) / 100;
    const zoomTo = (z: number) => setZoom(clampZoom(z));
    const onWheel = (e: React.WheelEvent) => setZoom((z) => clampZoom(z * (e.deltaY < 0 ? 1.06 : 1 / 1.06)));
    const nudge = (dx: number, dy: number) => setFocus(focusFrom(at.x + dx, at.y + dy, zoom));
    const onKey = (e: React.KeyboardEvent) => {
        const step = e.shiftKey ? 20 : 5;
        const moves: Record<string, [number, number]> = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
        if (moves[e.key]) { e.preventDefault(); nudge(...moves[e.key]); }
        if (e.key === '+' || e.key === '=') zoomTo(zoom * 1.1);
        if (e.key === '-') zoomTo(zoom / 1.1);
    };

    const picture = (opacity: number) => (
        <img src={image.url} alt="" draggable={false}
            style={{ position: 'absolute', left: at.x * k, top: at.y * k, width: at.w * k, height: at.h * k, maxWidth: 'none', opacity, userSelect: 'none', pointerEvents: 'none' }} />
    );

    return (
        <ScrollDialog open onClose={() => !busy && onClose()} width={760} title={t('studio.crop.title')}
            footer={<>
                <Button variant="ghost" label={t('studio.crop.reset')} isDisabled={busy} onClick={() => onSave(null)} />
                <span style={{ marginInlineEnd: 'auto' }} />
                <Button variant="ghost" label={t('common.cancel')} onClick={onClose} isDisabled={busy} />
                <Button variant="primary" label={t('studio.crop.save')} isLoading={busy}
                    onClick={() => onSave({ x: Math.round(focus.x * 10000) / 10000, y: Math.round(focus.y * 10000) / 10000, zoom })} />
            </>}>
            <div className="studio-stack" style={{ gap: 14 }}>
                <p className="muted" style={{ margin: 0 }}>{t('studio.crop.hint')}</p>
                <div className="studio-crop-stage" style={{ width: win.w * k + 2 * MARGIN, height: win.h * k + 2 * MARGIN }}
                    onPointerDown={onDown} onPointerMove={onMove} onPointerUp={onUp} onPointerCancel={onUp} onWheel={onWheel}
                    tabIndex={0} onKeyDown={onKey} role="application" aria-label={t('studio.crop.title')}>
                    {/* the whole picture, faded: what the crop leaves out */}
                    <div style={{ position: 'absolute', left: MARGIN, top: MARGIN }}>{picture(0.28)}</div>
                    {/* the window itself */}
                    <div className="studio-crop-window" style={{ left: MARGIN, top: MARGIN, width: win.w * k, height: win.h * k }}>
                        {picture(1)}
                        <svg viewBox={`${win.x} ${win.y} ${win.w} ${win.h}`} preserveAspectRatio="none" aria-hidden
                            style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', pointerEvents: 'none' }}>
                            <path d={card} fill="#fff" fillOpacity={0.88} stroke={BROWN} strokeWidth={2} vectorEffect="non-scaling-stroke" />
                        </svg>
                    </div>
                </div>
                <Slider label={t('studio.crop.zoom')} min={1} max={4} step={0.01} value={zoom} onChange={zoomTo} width={320}
                    formatValue={(v) => `${Math.round(v * 100)}%`} />
            </div>
        </ScrollDialog>
    );
}
