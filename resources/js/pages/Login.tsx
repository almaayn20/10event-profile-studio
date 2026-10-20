import { Banner } from '@astryxdesign/core/Banner';
import { Button } from '@astryxdesign/core/Button';
import { Card } from '@astryxdesign/core/Card';
import { TextInput } from '@astryxdesign/core/TextInput';
import { Head, useForm } from '@inertiajs/react';
import { Logo } from '@/components/layout/Logo';
import { useT } from '@/lib/i18n';

export default function Login({ configured }: { configured: boolean }) {
    const { t } = useT();
    const form = useForm({ password: '' });
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('studio.login.attempt'), { onFinish: () => form.reset('password') });
    };
    return (
        <div className="login-page">
            <Head title={t('studio.login.title')} />
            <div className="login-box">
                <Logo size={64} />
                <h1>{t('studio.login.title')}</h1>
                <p className="muted">{t('studio.login.subtitle')}</p>
                <Card padding={6}>
                    <form onSubmit={submit} className="studio-stack" style={{ gap: 16 }}>
                        {!configured && <Banner status="warning" title={t('studio.login.not_configured')} collapsible={false} />}
                        <TextInput type="password" label={t('studio.login.password')} value={form.data.password} onChange={(v) => form.setData('password', v)}
                            isRequired width="100%" status={form.errors.password ? { type: 'error', message: form.errors.password } : undefined} />
                        <Button type="submit" variant="primary" label={t('studio.login.submit')} isLoading={form.processing} width="100%" />
                    </form>
                </Card>
            </div>
        </div>
    );
}
