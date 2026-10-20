import { Banner } from '@astryxdesign/core/Banner';
import { Button } from '@astryxdesign/core/Button';
import { Card } from '@astryxdesign/core/Card';
import { NumberInput } from '@astryxdesign/core/NumberInput';
import { TextArea } from '@astryxdesign/core/TextArea';
import { TextInput } from '@astryxdesign/core/TextInput';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ArrowUpDown, BookPlus, Check, Download, Plus, RefreshCw, RotateCcw, Save, Trash2, Undo2, X } from 'lucide-react';
import { useState } from 'react';
import StudioLayout from '@/components/layout/StudioLayout';
import { CropDialog } from '@/components/studio/CropDialog';
import { ImagesPanel } from '@/components/studio/ImagesPanel';
import { PagePreview, type Fit, type HeadFit } from '@/components/studio/PagePreview';
import { PlaceDialog } from '@/components/studio/PlaceDialog';
import { StatusBadges } from '@/components/studio/StatusBadges';
import type { StudioProject, StudioStat, StudioVersion } from '@/components/studio/types';
import { ScrollDialog } from '@/components/ui/ScrollDialog';
import { useT } from '@/lib/i18n';

interface Props {
    project: StudioProject;
    profile: (StudioVersion & { pages_urls: string[] }) | null;
    suggestedAt: number;
    defaults: { stats: StudioStat[]; details_label: string };
    card: string;
}

export default function StudioEdit({ project, profile, suggestedAt, defaults, card }: Props) {
    const { t, dir } = useT();
    const form = useForm({
        client: project.client ?? '',
        title: project.title ?? '',
        description: project.description ?? '',
        details_url: project.details_url ?? '',
        details_label: project.details_label ?? '',
        stats: (project.stats ?? []) as StudioStat[],
    });
    const d = form.data;
    const [fit, setFit] = useState<Fit>('ok');
    const [head, setHead] = useState<HeadFit>({ client: 1, title: 1 });
    // set noticeably smaller to fit the card: say so, by how much
    const smaller = (k: number) => (k < 0.8 ? { type: 'warning' as const, message: t('studio.edit.head_shrunk', { pct: Math.round(k * 100) }) } : undefined);
    const [dialog, setDialog] = useState<null | 'place' | 'link' | 'move'>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [linkPage, setLinkPage] = useState<number>(1);
    const [cropping, setCropping] = useState<number | null>(null);

    const err = (k: string) => ((form.errors as Record<string, string>)[k] ? { type: 'error' as const, message: (form.errors as Record<string, string>)[k] } : undefined);
    const setStat = (i: number, patch: Partial<StudioStat>) => form.setData('stats', d.stats.map((s, j) => (j === i ? { ...s, ...patch } : s)));

    const save = (then?: () => void) => form.put(route('studio.projects.update', project.id), { preserveScroll: true, onSuccess: () => then?.() });
    /** An action on the profile always works from the saved project: unsaved edits are saved first. */
    const act = (name: string, url: string, data: Record<string, any> = {}, done?: () => void) => {
        const go = () => router.post(url, data, {
            preserveScroll: true, onStart: () => setBusy(name), onFinish: () => setBusy(null), onSuccess: () => done?.(),
        });
        form.isDirty ? save(go) : go();
    };

    const images = project.images ?? [];
    const windows = project.windows ?? [];
    const cropImage = images.find((i) => i.id === cropping);
    const cropWindow = windows.find((w) => w.id === cropping);
    const saveCrop = (crop: { x: number; y: number; zoom: number } | null) => {
        if (!cropping) return;
        router.put(route('studio.images.crop', cropping), crop ?? { zoom: null }, {
            preserveScroll: true, preserveState: true, onStart: () => setBusy('crop'), onFinish: () => setBusy(null), onSuccess: () => setCropping(null),
        });
    };
    const inProfile = project.in_profile;
    const pageTitle = project.title || project.client || t('studio.edit.title_new');

    return (
        <StudioLayout title={pageTitle} wide>
            <div className="page-head">
                <div style={{ minWidth: 0, display: 'grid', gap: 8 }}>
                    <Link href={route('studio.index')} className="fine-link" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: '.9rem' }}>
                        {dir === 'rtl' ? <ArrowRight size={15} aria-hidden /> : <ArrowLeft size={15} aria-hidden />}{t('studio.edit.back')}
                    </Link>
                    <h1><bdi>{pageTitle}</bdi></h1>
                    <StatusBadges project={project} />
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
                    {form.isDirty && <span className="muted" style={{ fontSize: '.88rem' }}>{t('studio.edit.unsaved')}</span>}
                    <Button variant="secondary" icon={<Save size={16} />} label={t('studio.edit.save')} isLoading={form.processing && !busy} isDisabled={!form.isDirty} onClick={() => save()} />
                    {project.status === 'draft' && (
                        <Button variant="primary" icon={<Check size={16} />} label={t('studio.edit.approve')} isDisabled={!images.length} onClick={() => setDialog('place')} />
                    )}
                    {project.status === 'approved' && inProfile && project.profile_stale && (
                        <Button variant="primary" icon={<RefreshCw size={16} />} label={t('studio.edit.refresh')} isLoading={busy === 'refresh'}
                            onClick={() => act('refresh', route('studio.projects.refresh', project.id))} />
                    )}
                    {inProfile && profile && (
                        <Button variant="secondary" icon={<ArrowUpDown size={16} />} label={t('studio.edit.move')} onClick={() => setDialog('move')} />
                    )}
                </div>
            </div>

            {!profile && project.status === 'draft' && <Banner status="info" title={t('studio.place.need_profile')} collapsible={false} />}
            {project.status === 'approved' && inProfile && project.profile_stale && <Banner status="warning" title={t('studio.edit.stale_note')} collapsible={false} />}
            {project.status === 'approved' && !inProfile && (
                <Banner status="warning" title={t('studio.edit.not_in_current')} collapsible={false}>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', paddingTop: 6 }}>
                        <Button size="sm" variant="primary" icon={<BookPlus size={15} />} label={t('studio.edit.place')} isDisabled={!profile} onClick={() => setDialog('place')} />
                        <Button size="sm" variant="secondary" label={t('studio.edit.link')} isDisabled={!profile} onClick={() => setDialog('link')} />
                    </div>
                </Banner>
            )}

            <div className="studio-editor">
                <div className="studio-editor-form">
                    <ImagesPanel projectId={project.id} images={images} onCrop={(img) => windows.some((w) => w.id === img.id) && setCropping(img.id)} />

                    <Card padding={5}>
                        <div className="studio-stack" style={{ gap: 14 }}>
                            <h2 className="studio-h2">{t('studio.edit.texts')}</h2>
                            <div className="grid gap-[12px] sm:grid-cols-2">
                                <TextInput width="100%" label={t('studio.edit.client')} value={d.client} onChange={(v) => form.setData('client', v)} status={err('client') ?? (d.client ? smaller(head.client) : undefined)} />
                                <TextInput width="100%" label={t('studio.edit.title')} description={t('studio.edit.title_hint')} value={d.title} onChange={(v) => form.setData('title', v)} status={err('title') ?? (d.title ? smaller(head.title) : undefined)} />
                            </div>
                            <TextArea width="100%" label={t('studio.edit.description')} description={t('studio.edit.description_hint')} rows={7} maxLength={2000}
                                value={d.description} onChange={(v) => form.setData('description', v)}
                                status={err('description') ?? (fit === 'cut' ? { type: 'error', message: t('studio.edit.description_cut') } : fit === 'shrunk' ? { type: 'warning', message: t('studio.edit.description_long') } : undefined)} />
                            <div className="grid gap-[12px] sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                                <TextInput width="100%" label={t('studio.edit.details_url')} description={t('studio.edit.details_url_hint')} value={d.details_url} dir="ltr"
                                    placeholder="https://" onChange={(v) => form.setData('details_url', v)} status={err('details_url')} isOptional />
                                <TextInput width="100%" label={t('studio.edit.details_label')} value={d.details_label} placeholder={defaults.details_label}
                                    onChange={(v) => form.setData('details_label', v)} status={err('details_label')} isOptional />
                            </div>
                        </div>
                    </Card>

                    <Card padding={5}>
                        <div className="studio-stack" style={{ gap: 12 }}>
                            <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                                <h2 className="studio-h2">{t('studio.edit.stats')}</h2>
                                <div style={{ marginInlineStart: 'auto', display: 'flex', gap: 8 }}>
                                    <Button size="sm" variant="ghost" icon={<RotateCcw size={14} />} label={t('studio.edit.stats_reset')} onClick={() => form.setData('stats', defaults.stats)} />
                                    {d.stats.length < 3 && <Button size="sm" variant="secondary" icon={<Plus size={14} />} label={t('studio.edit.stat_add')} onClick={() => form.setData('stats', [...d.stats, { value: '', label: '' }])} />}
                                </div>
                            </div>
                            <p className="muted" style={{ margin: 0 }}>{t('studio.edit.stats_hint')}</p>
                            {d.stats.length === 0 && <p className="muted tinted" style={{ margin: 0 }}>{t('studio.edit.stats_empty')}</p>}
                            {d.stats.map((s, i) => (
                                <div key={i} className="studio-stat-row">
                                    <span className="studio-stat-n">{i + 1}</span>
                                    <TextInput width="100%" label={t('studio.edit.stat_value')} value={s.value} placeholder="100+" onChange={(v) => setStat(i, { value: v })} status={err(`stats.${i}.value`)} />
                                    <TextInput width="100%" label={t('studio.edit.stat_label')} value={s.label} placeholder="مُعلِـن" onChange={(v) => setStat(i, { label: v })} status={err(`stats.${i}.label`)} />
                                    <Button variant="ghost" size="sm" icon={<X size={15} />} label={t('studio.edit.stat_remove')} isIconOnly
                                        onClick={() => form.setData('stats', d.stats.filter((_, j) => j !== i))} />
                                </div>
                            ))}
                        </div>
                    </Card>

                    <Card padding={5}>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <Button variant="secondary" icon={<Download size={15} />} label={t('studio.edit.download_pdf')} isDisabled={!images.length}
                                href={route('studio.projects.pdf', project.id)} />
                            {project.status === 'approved' && (
                                <Button variant="ghost" icon={<Undo2 size={15} />} label={t('studio.edit.unapprove')} isLoading={busy === 'unapprove'}
                                    onClick={() => window.confirm(t('studio.edit.unapprove_confirm')) && act('unapprove', route('studio.projects.unapprove', project.id))} />
                            )}
                            <Button variant="destructive" icon={<Trash2 size={15} />} label={t('studio.edit.delete')}
                                onClick={() => window.confirm(t('studio.edit.delete_confirm')) && router.delete(route('studio.projects.destroy', project.id))} />
                        </div>
                    </Card>
                </div>

                <aside className="studio-editor-preview">
                    <div className="studio-sticky">
                        <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, flexWrap: 'wrap' }}>
                            <h2 className="studio-h2">{t('studio.edit.preview')}</h2>
                            {inProfile && <span className="muted">{t('studio.edit.in_profile', { page: project.profile_page })}</span>}
                        </div>
                        <PagePreview url={route('studio.projects.preview', project.id)} data={d}
                            version={`${project.layout_seed}-${images.map((i) => `${i.id}:${i.priority ?? 0}:${i.crop ? `${i.crop.x},${i.crop.y},${i.crop.zoom}` : ''}`).join(',')}`}
                            onFit={setFit} onPick={setCropping} onHeadFit={setHead} />
                        <p className="fine" style={{ marginTop: 0 }}>{t('studio.edit.preview_hint')} {images.length > 0 && t('studio.crop.preview_hint')}</p>
                    </div>
                </aside>
            </div>

            {dialog === 'place' && profile && (
                <PlaceDialog pages={profile.pages_urls} initial={suggestedAt} busy={busy === 'approve'}
                    submitLabel={project.status === 'draft' ? t('studio.place.submit') : t('studio.place.submit_place')}
                    onSubmit={(at) => act('approve', route('studio.projects.approve', project.id), { at }, () => setDialog(null))}
                    onClose={() => busy !== 'approve' && setDialog(null)} />
            )}
            {cropImage && cropWindow && (
                <CropDialog key={cropImage.id} image={cropImage} win={cropWindow} card={card} busy={busy === 'crop'} onSave={saveCrop} onClose={() => setCropping(null)} />
            )}
            {dialog === 'move' && profile && project.profile_page && (
                <PlaceDialog mode="move" pages={profile.pages_urls.filter((_, i) => i + 1 !== project.profile_page)} initial={project.profile_page}
                    busy={busy === 'move'} submitLabel={t('studio.place.move_submit')}
                    onSubmit={(to) => act('move', route('studio.projects.move', project.id), { to }, () => setDialog(null))}
                    onClose={() => busy !== 'move' && setDialog(null)} />
            )}
            {dialog === 'place' && !profile && (
                <ScrollDialog open onClose={() => setDialog(null)} width={520} title={t('studio.place.title')}
                    footer={<Button variant="primary" label={t('studio.index.profile_upload')} onClick={() => router.visit(route('studio.profile'))} />}>
                    <p style={{ margin: 0 }}>{t('studio.place.need_profile')}</p>
                </ScrollDialog>
            )}
            {dialog === 'link' && profile && (
                <ScrollDialog open onClose={() => setDialog(null)} width={520} title={t('studio.edit.link_title')}
                    footer={<>
                        <Button variant="ghost" label={t('common.cancel')} onClick={() => setDialog(null)} />
                        <Button variant="primary" label={t('studio.edit.link_submit')} isLoading={busy === 'link'}
                            onClick={() => act('link', route('studio.projects.link', project.id), { page: linkPage }, () => setDialog(null))} />
                    </>}>
                    <div className="studio-stack" style={{ gap: 12 }}>
                        <p className="muted" style={{ margin: 0 }}>{t('studio.edit.link_hint')}</p>
                        <NumberInput label={t('studio.place.at')} isIntegerOnly min={1} max={profile.pages} value={linkPage} onChange={(v) => setLinkPage(Math.round(v || 1))} width={160} />
                        {profile.pages_urls[linkPage - 1] && <img src={profile.pages_urls[linkPage - 1]} alt="" style={{ width: 260, borderRadius: 6, border: '1px solid var(--color-border)' }} />}
                    </div>
                </ScrollDialog>
            )}
        </StudioLayout>
    );
}
