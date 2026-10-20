import { Badge } from '@astryxdesign/core/Badge';
import { Button } from '@astryxdesign/core/Button';
import { Card } from '@astryxdesign/core/Card';
import { TextInput } from '@astryxdesign/core/TextInput';
import { router, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import StudioLayout from '@/components/layout/StudioLayout';
import { PageHead } from '@/components/ui/bits';
import { useT } from '@/lib/i18n';

function FontRow({ weight, uploaded }: { weight: 'medium' | 'bold'; uploaded: boolean }) {
    const { t } = useT();
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const send = (file?: File) => file && router.post(route('studio.settings.font', weight), { file }, {
        forceFormData: true, preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false),
    });
    return (
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            <b style={{ minWidth: 260 }}>{t(`studio.settings.weight.${weight}`)}</b>
            <Badge variant={uploaded ? 'success' : 'warning'} label={uploaded ? t('studio.settings.uploaded') : t('studio.settings.missing')} />
            <Button size="sm" variant="secondary" icon={<Upload size={14} />} label={uploaded ? t('studio.settings.replace_font') : t('studio.settings.upload_font')} isLoading={busy} onClick={() => input.current?.click()} />
            <input ref={input} type="file" accept=".ttf,.otf" hidden onChange={(e) => send(e.target.files?.[0])} />
        </div>
    );
}

export default function Settings({ fonts }: { fonts: { medium: boolean; bold: boolean } }) {
    const { t } = useT();
    const form = useForm({ current: '', password: '', password_confirmation: '' });
    const err = (k: 'current' | 'password') => (form.errors[k] ? { type: 'error' as const, message: form.errors[k] } : undefined);

    return (
        <StudioLayout title={t('studio.settings.title')}>
            <PageHead title={t('studio.settings.title')} />

            <Card padding={5}>
                <div className="studio-stack" style={{ gap: 12, maxWidth: 460 }}>
                    <h2 className="studio-h2">{t('studio.settings.password')}</h2>
                    <p className="muted" style={{ margin: 0 }}>{t('studio.settings.password_hint')}</p>
                    <TextInput type="password" label={t('studio.settings.current')} value={form.data.current} onChange={(v) => form.setData('current', v)} status={err('current')} width="100%" />
                    <TextInput type="password" width="100%" label={t('studio.settings.new')} value={form.data.password} onChange={(v) => form.setData('password', v)} status={err('password')} />
                    <TextInput type="password" width="100%" label={t('studio.settings.confirm')} value={form.data.password_confirmation} onChange={(v) => form.setData('password_confirmation', v)} />
                    <div><Button variant="primary" label={t('studio.settings.save_password')} isLoading={form.processing}
                        onClick={() => form.put(route('studio.settings.password'), { preserveScroll: true, onSuccess: () => form.reset() })} /></div>
                </div>
            </Card>

            <Card padding={5}>
                <div className="studio-stack" style={{ gap: 12 }}>
                    <h2 className="studio-h2">{t('studio.settings.fonts')}</h2>
                    <p className="muted" style={{ margin: 0, lineHeight: 1.8 }}>{t('studio.settings.fonts_hint')}</p>
                    <FontRow weight="medium" uploaded={fonts.medium} />
                    <FontRow weight="bold" uploaded={fonts.bold} />
                    <p className="fine" style={{ marginTop: 0 }}>{t('studio.settings.fonts_license')}</p>
                </div>
            </Card>
        </StudioLayout>
    );
}
