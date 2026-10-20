import { Button } from '@astryxdesign/core/Button';
import { Card } from '@astryxdesign/core/Card';
import { ProgressBar } from '@astryxdesign/core/ProgressBar';
import { router } from '@inertiajs/react';
import { ImagePlus, Shuffle, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { useUploadGuard } from '@/lib/upload';
import { useT } from '@/lib/i18n';
import { lighten } from './compress';
import type { StudioImage } from './types';

/** The page's pictures: add any number, mark up to three as 1 · 2 · 3 (the large ones), remove, rearrange. */
export function ImagesPanel({ projectId, images }: { projectId: string; images: StudioImage[] }) {
    const { t, num } = useT();
    const input = useRef<HTMLInputElement>(null);
    const guard = useUploadGuard(25);
    const [phase, setPhase] = useState<null | 'preparing' | 'uploading'>(null);
    const [progress, setProgress] = useState(0);
    const [over, setOver] = useState(false);
    const [busy, setBusy] = useState<number | 'shuffle' | null>(null);

    const add = async (list: FileList | File[] | null) => {
        const files = Array.from(list ?? []).filter((f) => f.type.startsWith('image/') || /\.(jpe?g|png|webp)$/i.test(f.name));
        if (!files.length) return;
        setPhase('preparing');
        const light: File[] = [];
        for (const f of files) light.push(await lighten(f));
        const fit = guard(light);
        if (!fit.length) { setPhase(null); return; }
        setPhase('uploading');
        setProgress(0);
        router.post(route('studio.projects.images.store', projectId), { images: fit }, {
            forceFormData: true, preserveScroll: true, preserveState: true,
            onProgress: (e) => setProgress(Math.round(e?.percentage ?? 0)),
            onFinish: () => { setPhase(null); if (input.current) input.current.value = ''; },
        });
    };

    const setPriority = (img: StudioImage, priority: number | null) => {
        if (img.priority === priority) return;
        setBusy(img.id);
        router.put(route('studio.images.priority', img.id), { priority }, { preserveScroll: true, preserveState: true, onFinish: () => setBusy(null) });
    };
    const remove = (img: StudioImage) => {
        setBusy(img.id);
        router.delete(route('studio.images.destroy', img.id), { preserveScroll: true, preserveState: true, onFinish: () => setBusy(null) });
    };
    const shuffle = () => {
        setBusy('shuffle');
        router.post(route('studio.projects.shuffle', projectId), {}, { preserveScroll: true, preserveState: true, onFinish: () => setBusy(null) });
    };

    // the large ones first, in their order; then the rest as they were added
    const sorted = [...images].sort((a, b) => (a.priority ?? 9) - (b.priority ?? 9));

    return (
        <Card padding={5}>
            <div className="studio-stack" style={{ gap: 14 }}>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                    <h2 className="studio-h2">{t('studio.edit.images')} <span className="muted">({num(images.length)})</span></h2>
                    <div style={{ marginInlineStart: 'auto', display: 'flex', gap: 8 }}>
                        {images.length > 1 && <Button size="sm" variant="ghost" icon={<Shuffle size={15} />} label={t('studio.edit.shuffle')} tooltip={t('studio.edit.shuffle_hint')} isLoading={busy === 'shuffle'} onClick={shuffle} />}
                        <Button size="sm" variant="secondary" icon={<ImagePlus size={15} />} label={t('studio.edit.add_images')} onClick={() => input.current?.click()} isDisabled={phase !== null} />
                    </div>
                </div>
                <p className="muted" style={{ margin: 0, lineHeight: 1.8 }}>{t('studio.edit.images_hint')}</p>
                <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={(e) => add(e.target.files)} />

                {phase ? (
                    <ProgressBar label={t(phase === 'preparing' ? 'studio.edit.preparing' : 'studio.edit.uploading')} value={phase === 'preparing' ? 5 : progress} hasValueLabel={phase === 'uploading'} variant="accent" />
                ) : (
                    <button type="button" className={`studio-drop${over ? ' is-over' : ''}`} onClick={() => input.current?.click()}
                        onDragOver={(e) => { e.preventDefault(); setOver(true); }} onDragLeave={() => setOver(false)}
                        onDrop={(e) => { e.preventDefault(); setOver(false); add(e.dataTransfer.files); }}>
                        <ImagePlus size={20} aria-hidden /> {t('studio.edit.drop')}
                    </button>
                )}

                {sorted.length > 0 && (
                    <div className="studio-images">
                        {sorted.map((img) => (
                            <div key={img.id} className={`studio-image${img.priority ? ' is-priority' : ''}`} aria-busy={busy === img.id}>
                                <div className="studio-image-pic">
                                    <img src={img.url} alt="" loading="lazy" />
                                    {img.priority && <span className="studio-image-rank">{num(img.priority)}</span>}
                                    <button type="button" className="studio-image-remove" aria-label={t('studio.edit.remove_image')} title={t('studio.edit.remove_image')} onClick={() => remove(img)}>
                                        <Trash2 size={14} />
                                    </button>
                                </div>
                                <div className="studio-prio" role="radiogroup" aria-label={t('studio.edit.priority')}>
                                    {[1, 2, 3].map((n) => (
                                        <button key={n} type="button" role="radio" aria-checked={img.priority === n} className={img.priority === n ? 'is-on' : ''}
                                            title={t('studio.edit.priority_label', { n: num(n) })} onClick={() => setPriority(img, n)}>{num(n)}</button>
                                    ))}
                                    <button type="button" role="radio" aria-checked={!img.priority} className={!img.priority ? 'is-on' : ''} onClick={() => setPriority(img, null)}>
                                        {t('studio.edit.priority_none')}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </Card>
    );
}
