import { Dialog } from '@astryxdesign/core/Dialog';
import type { ReactNode } from 'react';

/** A form dialog whose middle scrolls: the title and the action buttons stay put however long the form grows. */
export function ScrollDialog({ open, onClose, title, footer, children, width = 760 }: { open: boolean; onClose: () => void; title: ReactNode; footer?: ReactNode; children: ReactNode; width?: number }) {
    return (
        <Dialog isOpen={open} onOpenChange={(o) => !o && onClose()} width={width} maxHeight="90dvh" purpose="form" padding={0}>
            <div className="scroll-dialog">
                <div className="scroll-dialog-head"><h2>{title}</h2></div>
                <div className="scroll-dialog-body">{children}</div>
                {footer && <div className="scroll-dialog-foot">{footer}</div>}
            </div>
        </Dialog>
    );
}
