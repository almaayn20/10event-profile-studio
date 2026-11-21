import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/lib/types';
import eventLogo from './logo-10event.svg?raw';
import giftLogo from './logo-10gift.svg?raw';

/** The studio's mark — 10 Event or 10 Gift, by the edition — drawn in the current text colour. */
export function Logo({ size = 44 }: { size?: number }) {
    const gift = usePage<SharedProps>().props.app?.edition === 'gift';
    return <span className="logo-10event" style={{ height: size, width: size * 1.1 }} dangerouslySetInnerHTML={{ __html: gift ? giftLogo : eventLogo }} />;
}
