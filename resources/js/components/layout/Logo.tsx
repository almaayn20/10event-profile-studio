import logo from './logo-10event.svg?raw';

/** The 10 Event mark, drawn in the current text colour. */
export function Logo({ size = 44 }: { size?: number }) {
    return <span className="logo-10event" style={{ height: size, width: size * 1.1 }} dangerouslySetInnerHTML={{ __html: logo }} />;
}
