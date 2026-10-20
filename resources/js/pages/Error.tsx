import { Button } from '@astryxdesign/core/Button';
import { Head, usePage } from '@inertiajs/react';
import { Logo } from '@/components/layout/Logo';
import { errorText } from '@/lib/i18n';
import type { SharedProps } from '@/lib/types';

export default function ErrorPage({ status, code, ref: reference }: { status: number; code: string; ref: string }) {
    const { t } = usePage<SharedProps>().props;
    const e = errorText(t, code, { ref: reference });
    return (
        <div className="login-page">
            <Head title={String(status)} />
            <div className="login-box">
                <Logo size={56} />
                <h1>{e.title}</h1>
                <p className="muted">{e.message} {e.action}</p>
                <div><Button variant="primary" label={t?.studio?.nav?.projects ?? 'Projects'} href={route('studio.index')} /></div>
            </div>
        </div>
    );
}
