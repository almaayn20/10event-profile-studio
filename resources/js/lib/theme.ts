import { defineTheme } from '@astryxdesign/core/theme';
import { neutralTheme } from '@astryxdesign/theme-neutral';

const FALLBACK_AR = "'IBM Plex Sans Arabic', 'SF Arabic', 'Geeza Pro', 'Segoe UI', Tahoma, sans-serif";

/** Warm neutrals, one deep-green action colour, Alexandria for headings and IBM Plex Sans Arabic for reading. */
export const theme = defineTheme({
    name: 'studio',
    extends: neutralTheme,
    color: { accent: ['#1A2B27', '#E8E1D6'], neutralStyle: 'warm' },
    typography: {
        scale: { base: 16, ratio: 1.22 },
        body: { family: 'IBM Plex Sans Arabic', fallbacks: FALLBACK_AR },
        heading: { family: 'Alexandria', fallbacks: `'IBM Plex Sans Arabic', ${FALLBACK_AR}` },
    },
    radius: { base: 8, multiplier: 1 },
    motion: { fast: 180, medium: 420, ratio: 0.75 },
    tokens: {
        '--color-on-accent': ['#F5EFE8', '#1A2B27'],
        '--color-background-body': ['#F7F7F7', '#0E1512'],
        '--color-background-surface': ['#FFFFFF', '#141D1A'],
        '--color-background-card': ['#FFFFFF', '#141D1A'],
        '--color-background-popover': ['#FFFFFF', '#141D1A'],
        '--color-text-primary': ['#1A2B27', '#F5EFE8'],
        '--color-text-secondary': ['#5A6560', '#A8B4AF'],
        '--color-icon-primary': ['#1A2B27', '#F5EFE8'],
        '--color-icon-secondary': ['#5A6560', '#A8B4AF'],
        '--color-border': ['#E6E6E6', '#26322E'],
        '--color-success': ['#2F7D55', '#5DBB88'],
        '--color-warning': ['#A96F12', '#E3AE4E'],
        '--color-error': ['#B3261E', '#F2776E'],
        '--color-background-blue': ['#E6EBF1', '#1D2733'],
        '--color-border-blue': ['#4A6177', '#7F97AE'],
        '--color-icon-blue': ['#3E566C', '#9DB3C8'],
        '--color-text-blue': ['#1E2A3A', '#D5E0EA'],
        '--color-accent-muted': ['#1A2B271F', '#E8E1D633'],
    } as any,
    localTokens: {
        '--astryx-theme-neutral-color-status-fill-accent': ['#1A2B27', '#E8E1D6'],
        '--astryx-theme-neutral-color-status-muted-accent': ['#EAE3D9', '#E8E1D633'],
        '--astryx-theme-neutral-color-status-fill-success': ['#2F7D55', '#5DBB88'],
        '--astryx-theme-neutral-color-status-fill-error': ['#B3261E', '#F2776E'],
        '--astryx-theme-neutral-color-status-fill-warning': ['#C9973C', '#E3AE4E'],
    },
    components: {
        button: { base: { fontWeight: '600' } },
    },
});
