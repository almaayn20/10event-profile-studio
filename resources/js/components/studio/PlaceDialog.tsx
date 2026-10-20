import { Button } from '@astryxdesign/core/Button';
import { NumberInput } from '@astryxdesign/core/NumberInput';
import { Fragment, useEffect, useRef, useState } from 'react';
import { ScrollDialog } from '@/components/ui/ScrollDialog';
import { useT } from '@/lib/i18n';

/**
 * "Which page should it be?" — every page of the current profile as a small picture, with a slot between each two.
 * Clicking a slot (or typing a number) chooses where the new page goes; it becomes page `at`.
 * Moving a page that is already in the profile is the same choice, made among the other pages (`mode="move"`).
 */
export function PlaceDialog({ pages, initial, busy, submitLabel, onSubmit, onClose, mode = 'place' }: {
    pages: string[]; initial: number; busy: boolean; submitLabel: string; onSubmit: (at: number) => void; onClose: () => void; mode?: 'place' | 'move';
}) {
    const { t, num } = useT();
    const total = pages.length + 1;
    const [at, setAt] = useState(Math.max(1, Math.min(initial, total)));
    const chosen = useRef<HTMLButtonElement>(null);
    useEffect(() => { chosen.current?.scrollIntoView({ block: 'nearest', inline: 'center' }); }, [at]);

    const slot = (position: number) => (
        <button key={`slot-${position}`} type="button" ref={position === at ? chosen : undefined}
            className={`studio-slot${position === at ? ' is-chosen' : ''}`} onClick={() => setAt(position)}
            aria-pressed={position === at} aria-label={t('studio.place.becomes', { at: num(position), total: num(total) })}>
            {position === at ? <span className="studio-slot-new">{num(position)}</span> : <span className="studio-slot-line" />}
        </button>
    );

    return (
        <ScrollDialog open onClose={onClose} width={980} title={t(mode === 'move' ? 'studio.place.move_title' : 'studio.place.title')}
            footer={<>
                <span className="muted" style={{ marginInlineEnd: 'auto' }}>{busy ? t(mode === 'move' ? 'studio.place.move_working' : 'studio.place.working') : t('studio.place.becomes', { at: num(at), total: num(total) })}</span>
                <Button variant="ghost" label={t('common.cancel')} onClick={onClose} isDisabled={busy} />
                <Button variant="primary" label={submitLabel} isLoading={busy} onClick={() => onSubmit(at)} />
            </>}>
            <div className="studio-stack" style={{ gap: 14 }}>
                <p className="muted" style={{ margin: 0 }}>{t(mode === 'move' ? 'studio.place.move_hint' : 'studio.place.hint')}</p>
                <NumberInput label={t('studio.place.at')} isIntegerOnly min={1} max={total} value={at} onChange={(v) => setAt(Math.max(1, Math.min(total, Math.round(v || 1))))} width={160} />
                <div className="studio-pages">
                    {pages.map((src, i) => (
                        <Fragment key={src}>
                            {slot(i + 1)}
                            <figure className="studio-page-thumb">
                                <img src={src} alt="" loading="lazy" />
                                <figcaption>{num(i + 1 >= at ? i + 2 : i + 1)}</figcaption>
                            </figure>
                        </Fragment>
                    ))}
                    {slot(total)}
                </div>
            </div>
        </ScrollDialog>
    );
}
