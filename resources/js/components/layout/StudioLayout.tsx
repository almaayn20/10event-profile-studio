import { Button } from '@astryxdesign/core/Button';
import { Head, Link, router } from '@inertiajs/react';
import { LogOut, Menu, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Logo } from '@/components/layout/Logo';
import { useT } from '@/lib/i18n';

/** The studio's frame: the 10 Event mark, the three sections, and sign out. A drawer below 1080 px. */
export default function StudioLayout({ title, wide = false, children }: { title: string; wide?: boolean; children: ReactNode }) {
    const { t } = useT();
    const [open, setOpen] = useState(false);
    useEffect(() => router.on('navigate', () => setOpen(false)), []);
    const is = (n: string) => !!route().current(n);
    const links = [
        { label: t('studio.nav.projects'), href: route('studio.index'), active: is('studio.index') || is('studio.projects.*') },
        { label: t('studio.nav.profile'), href: route('studio.profile'), active: is('studio.profile') },
        { label: t('studio.nav.settings'), href: route('studio.settings'), active: is('studio.settings') },
    ];
    const logout = () => router.post(route('studio.logout'));
    return (
        <>
            <Head title={title} />
            <div className="site-frame">
                <header className="navbar">
                    <nav className="navbar-inner" aria-label={t('studio.name')}>
                        <div className="navbar-start">
                            <Link href={route('studio.index')} className="navbar-brand" aria-label="10 Event"><Logo size={44} /></Link>
                            <span className="navbar-badge">{t('studio.badge')}</span>
                        </div>
                        <div className="navbar-links">
                            {links.map((l) => (
                                <Link key={l.href} href={l.href} className={`navbar-link${l.active ? ' is-active' : ''}`} aria-current={l.active ? 'page' : undefined}>{l.label}</Link>
                            ))}
                        </div>
                        <div className="navbar-end">
                            <span className="navbar-tools"><Button size="sm" variant="ghost" icon={<LogOut size={15} />} label={t('studio.nav.logout')} onClick={logout} /></span>
                            <button type="button" className="navbar-burger" aria-label={open ? t('common.close') : t('common.menu')} aria-expanded={open} onClick={() => setOpen((o) => !o)}>
                                {open ? <X size={20} /> : <Menu size={20} />}
                            </button>
                        </div>
                    </nav>
                </header>
                {open && (
                    <div className="drawer" role="dialog" aria-modal="true" aria-label={t('common.menu')}>
                        <button type="button" className="drawer-scrim" aria-label={t('common.close')} onClick={() => setOpen(false)} />
                        <div className="drawer-panel">
                            <div style={{ marginBottom: 14 }}><Logo size={40} /></div>
                            {links.map((l) => <Link key={l.href} href={l.href} className={`drawer-link${l.active ? ' is-active' : ''}`}>{l.label}</Link>)}
                            <button type="button" className="drawer-link" onClick={logout}><LogOut size={16} />{t('studio.nav.logout')}</button>
                        </div>
                    </div>
                )}
                <main className="site-main">
                    <div className={`app-content${wide ? ' studio-wide' : ''}`}>{children}</div>
                </main>
            </div>
        </>
    );
}
