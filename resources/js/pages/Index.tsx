import { Button } from '@astryxdesign/core/Button';
import { Card } from '@astryxdesign/core/Card';
import { SegmentedControl, SegmentedControlItem } from '@astryxdesign/core/SegmentedControl';
import { Switch } from '@astryxdesign/core/Switch';
import { Link, router, usePage } from '@inertiajs/react';
import { Download, FileText, ImageIcon, Plus, Upload } from 'lucide-react';
import { useState } from 'react';
import StudioLayout from '@/components/layout/StudioLayout';
import { PageHead } from '@/components/ui/bits';
import { StatusBadges } from '@/components/studio/StatusBadges';
import { formatBytes, formatDate, type StudioProject, type StudioVersion } from '@/components/studio/types';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/lib/types';

export default function StudioIndex({ projects, profile }: { projects: StudioProject[]; profile: StudioVersion | null }) {
    const { t, locale, num } = useT();
    const [filter, setFilter] = useState<'all' | 'draft' | 'approved'>('all');
    const [creating, setCreating] = useState(false);
    const shown = projects.filter((p) => filter === 'all' || p.status === filter);
    const statsShown = usePage<SharedProps>().props.app.statsShown;
    const [savingStats, setSavingStats] = useState(false);
    // one switch for every project; the pages already in the profile that change are marked for updating
    const setStats = (on: boolean) => {
        if (!window.confirm(t(on ? 'studio.index.stats_on_confirm' : 'studio.index.stats_off_confirm'))) return;
        router.put(route('studio.settings.stats'), { on }, { preserveScroll: true, onStart: () => setSavingStats(true), onFinish: () => setSavingStats(false) });
    };

    return (
        <StudioLayout title={t('studio.index.title')}>
            <PageHead title={t('studio.index.title')} subtitle={t('studio.index.subtitle')}
                actions={<Button variant="primary" icon={<Plus size={16} />} label={t('studio.index.new')} isLoading={creating}
                    onClick={() => router.post(route('studio.projects.store'), {}, { onStart: () => setCreating(true), onFinish: () => setCreating(false) })} />} />

            <Card padding={0}>
                <div className="studio-profile-strip">
                    {profile?.thumb ? <img src={profile.thumb} alt="" className="studio-profile-cover" /> : <span className="studio-profile-cover is-empty"><FileText size={26} aria-hidden /></span>}
                    <div style={{ display: 'grid', gap: 4, minWidth: 0 }}>
                        <b>{t('studio.index.profile_title')}</b>
                        {profile ? (
                            <>
                                <span className="muted"><bdi>{profile.name}</bdi></span>
                                <span className="muted" style={{ fontSize: '.88rem' }}>{t('studio.index.profile_pages', { pages: num(profile.pages) })} · <bdi className="ltr">{formatBytes(profile.bytes)}</bdi> · <bdi>{formatDate(profile.created_at, locale)}</bdi></span>
                            </>
                        ) : <span className="muted">{t('studio.index.profile_none')}</span>}
                    </div>
                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginInlineStart: 'auto' }}>
                        {profile && <Button size="sm" variant="secondary" icon={<Download size={15} />} label={t('studio.index.download')} href={route('studio.profile.download', profile.id)} />}
                        <Button size="sm" variant={profile ? 'ghost' : 'primary'} icon={profile ? undefined : <Upload size={15} />}
                            label={profile ? t('studio.index.profile_manage') : t('studio.index.profile_upload')} onClick={() => router.visit(route('studio.profile'))} />
                    </div>
                </div>
            </Card>

            <div style={{ display: 'flex', justifyContent: 'flex-start', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                <SegmentedControl label={t('studio.index.title')} value={filter} onChange={(v) => setFilter(v as any)} size="sm">
                    <SegmentedControlItem value="all" label={`${t('studio.index.filter_all')} (${num(projects.length)})`} />
                    <SegmentedControlItem value="draft" label={`${t('studio.index.filter_draft')} (${num(projects.filter((p) => p.status === 'draft').length)})`} />
                    <SegmentedControlItem value="approved" label={`${t('studio.index.filter_approved')} (${num(projects.filter((p) => p.status === 'approved').length)})`} />
                </SegmentedControl>
                <div style={{ marginInlineStart: 'auto' }}>
                    <Switch label={t('studio.index.stats_switch')} description={statsShown ? t('studio.index.stats_switch_on') : t('studio.index.stats_switch_off')}
                        value={statsShown} isLoading={savingStats} onChange={(on) => setStats(on)} size="sm" />
                </div>
            </div>

            {shown.length === 0 ? (
                <p className="muted tinted" style={{ margin: 0 }}>{t('studio.index.empty')}</p>
            ) : (
                <div className="studio-grid">
                    {shown.map((p) => (
                        <Link key={p.id} href={route('studio.projects.edit', p.id)} className="studio-card">
                            <div className="studio-card-thumb">
                                {p.thumb ? <img src={p.thumb} alt="" loading="lazy" /> : <span><ImageIcon size={26} aria-hidden /></span>}
                            </div>
                            <div className="studio-card-body">
                                <b className="studio-card-title"><bdi>{p.title || t('studio.index.untitled')}</bdi></b>
                                <span className="muted"><bdi>{p.client || t('studio.index.no_client')}</bdi> · {t('studio.index.images', { count: num(p.images_count) })}</span>
                                <StatusBadges project={p} />
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </StudioLayout>
    );
}
