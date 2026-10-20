import type { ReactNode } from 'react';

export function PageHead({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: ReactNode }) {
    return (
        <div className="page-head">
            <div style={{ minWidth: 0 }}>
                <h1>{title}</h1>
                {subtitle && <p className="muted" style={{ margin: '6px 0 0', lineHeight: 1.8 }}>{subtitle}</p>}
            </div>
            {actions && <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>{actions}</div>}
        </div>
    );
}
