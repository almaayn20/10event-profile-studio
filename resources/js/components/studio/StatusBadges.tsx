import { Badge } from '@astryxdesign/core/Badge';
import { AlertTriangle, BookOpen } from 'lucide-react';
import { useT } from '@/lib/i18n';
import type { StudioProject } from './types';

/** Draft or approved, which page of the profile it is, and whether the profile still has an older copy. */
export function StatusBadges({ project }: { project: StudioProject }) {
    const { t } = useT();
    return (
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
            <Badge variant={project.status === 'approved' ? 'success' : 'neutral'} label={t(`studio.status.${project.status}`)} />
            {project.in_profile && <Badge variant="info" icon={<BookOpen size={12} />} label={t('studio.index.page', { page: project.profile_page })} />}
            {project.status === 'approved' && !project.in_profile && <Badge variant="warning" label={t('studio.index.not_in_profile')} />}
            {project.in_profile && project.profile_stale && <Badge variant="warning" icon={<AlertTriangle size={12} />} label={t('studio.index.stale')} />}
        </div>
    );
}
