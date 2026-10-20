import type { route as routeFn } from 'ziggy-js';

declare global {
    const route: typeof routeFn;
}

declare module '*.json' {
    const value: any;
    export default value;
}
