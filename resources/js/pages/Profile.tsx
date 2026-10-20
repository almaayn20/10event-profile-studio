import { Badge } from '@astryxdesign/core/Badge';
import { Button } from '@astryxdesign/core/Button';
import { Card } from '@astryxdesign/core/Card';
import { ProgressBar } from '@astryxdesign/core/ProgressBar';
import { TextInput } from '@astryxdesign/core/TextInput';
import { Link, router, useForm } from '@inertiajs/react';
import { Download, FileUp, History, RotateCcw } from 'lucide-react';
import { useRef } from 'react';
import StudioLayout from '@/components/layout/StudioLayout';
import { PageHead } from '@/components/ui/bits';
import { formatBytes, formatDate, type StudioVersion } from '@/components/studio/types';
import { useUploadGuard } from '@/lib/upload';
import { useT } from '@/lib/i18n';

interface Placed { id: string; client: string; title: string; stale: boolean }

export default function StudioProfile({ versions, current, placed, maxMb }: {
    versions: StudioVersion[]; current: (StudioVersion & { pages_urls: string[] }) | null; placed: Record<number, Placed>; maxMb: number;
}) {
    const { t, locale, num } = useT();
    const input = useRef<HTMLInputElement>(null);
    const guard = useUploadGuard(maxMb);
    const form = useForm<{ file: File | null; note: string }>({ file: null, note: '' });
    const upload = () => form.post(route('studio.profile.store'), {
        forceFormData: true, preserveScroll: true,
        onSuccess: () => { form.reset(); if (input.current) input.current.value = ''; },
    });

    return (
        <StudioLayout title={t('studio.profile.title')} wide>
            <PageHead title={t('studio.profile.title')} subtitle={t('studio.profile.subtitle')}
                actions={current ? <Button variant="primary" icon={<Download size={16} />} label={t('studio.index.download')} href={route('studio.profile.download', current.id)} /> : undefined} />

            <Card padding={5}>
                <div className="studio-stack" style={{ gap: 12 }}>
                    <h2 className="studio-h2">{t('studio.profile.upload')}</h2>
                    <p className="muted" style={{ margin: 0 }}>{t('studio.profile.upload_hint', { max: num(maxMb) })} {current ? t('studio.profile.upload_relink') : ''}</p>
                    <div style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
                        <Button variant="secondary" icon={<FileUp size={16} />} label={form.data.file ? form.data.file.name : t('studio.profile.choose')} onClick={() => input.current?.click()} />
                        <input ref={input} type="file" accept="application/pdf,.pdf" hidden onChange={(e) => form.setData('file', guard(Array.from(e.target.files ?? []))[0] ?? null)} />
                        <TextInput label={t('studio.profile.note')} placeholder={t('studio.profile.note_placeholder')} value={form.data.note} onChange={(v) => form.setData('note', v)} isOptional width={320} />
                        <Button variant="primary" label={t('studio.profile.upload_submit')} isDisabled={!form.data.file} isLoading={form.processing} onClick={upload} />
                    </div>
                    {form.progress && <ProgressBar label={t('studio.profile.uploading')} value={form.progress.percentage ?? 0} hasValueLabel variant="accent" />}
                    {form.errors.file && <span style={{ color: 'var(--color-error)' }}>{form.errors.file}</span>}
                </div>
            </Card>

            {current ? (
                <Card padding={5}>
                    <div className="studio-stack" style={{ gap: 12 }}>
                        <div style={{ display: 'flex', gap: 8, alignItems: 'baseline', flexWrap: 'wrap' }}>
                            <h2 className="studio-h2">{t('studio.profile.pages')}</h2>
                            <span className="muted"><bdi>{current.name}</bdi> · {t('studio.index.profile_pages', { pages: num(current.pages) })} · <bdi className="ltr">{formatBytes(current.bytes)}</bdi></span>
                        </div>
                        <div className="studio-pages is-grid">
                            {current.pages_urls.map((src, i) => {
                                const p = placed[i + 1];
                                const fig = (
                                    <figure className={`studio-page-thumb${p ? ' is-project' : ''}`}>
                                        <img src={src} alt="" loading="lazy" />
                                        <figcaption>{num(i + 1)}{p ? <> · <bdi>{p.client || p.title}</bdi></> : null}</figcaption>
                                    </figure>
                                );
                                return p ? <Link key={src} href={route('studio.projects.edit', p.id)} title={p.title}>{fig}</Link> : <div key={src}>{fig}</div>;
                            })}
                        </div>
                    </div>
                </Card>
            ) : <p className="muted tinted" style={{ margin: 0 }}>{t('studio.profile.none')}</p>}

            {versions.length > 0 && (
                <Card padding={5}>
                    <div className="studio-stack" style={{ gap: 10 }}>
                        <h2 className="studio-h2" style={{ display: 'flex', alignItems: 'center', gap: 8 }}><History size={18} aria-hidden />{t('studio.profile.history')}</h2>
                        <div className="studio-versions" role="table">
                            <div className="studio-version is-head" role="row">
                                <span>{t('studio.profile.col.date')}</span><span>{t('studio.profile.col.change')}</span>
                                <span>{t('studio.profile.col.pages')}</span><span>{t('studio.profile.col.size')}</span><span />
                            </div>
                            {versions.map((v, i) => (
                                <div key={v.id} className="studio-version" role="row">
                                    <span><bdi>{formatDate(v.created_at, locale)}</bdi></span>
                                    <span style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap', minWidth: 0 }}>
                                        <Badge variant={v.source === 'upload' ? 'blue' : v.source === 'remove' ? 'orange' : v.source === 'restore' ? 'purple' : 'green'} label={t(`studio.profile.source.${v.source}`)} />
                                        {i === 0 && <Badge variant="success" label={t('studio.profile.current_badge')} />}
                                        {v.note && <bdi className="muted">{v.note}</bdi>}
                                    </span>
                                    <span>{num(v.pages)}</span>
                                    <span className="ltr">{formatBytes(v.bytes)}</span>
                                    <span style={{ display: 'flex', gap: 4, justifyContent: 'flex-end' }}>
                                        <Button size="sm" variant="ghost" icon={<Download size={14} />} label={t('studio.profile.download')} href={route('studio.profile.download', v.id)} />
                                        {i > 0 && <Button size="sm" variant="ghost" icon={<RotateCcw size={14} />} label={t('studio.profile.restore')}
                                            onClick={() => window.confirm(t('studio.profile.restore_confirm')) && router.post(route('studio.profile.restore', v.id), {}, { preserveScroll: true })} />}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </Card>
            )}
        </StudioLayout>
    );
}
