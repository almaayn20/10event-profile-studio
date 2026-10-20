import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.tsx'], refresh: true }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js'),
            'ziggy-js': path.resolve(import.meta.dirname, 'vendor/tightenco/ziggy'),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5190,
        strictPort: true,
        watch: { ignored: ['**/storage/**', '**/vendor/**'] },
    },
    // Pre-bundle every dependency any page uses at startup, so the dev server never has to
    // re-optimise mid-session (which breaks lazy page imports with "Outdated Optimize Dep").
    optimizeDeps: { entries: ['resources/js/app.tsx', 'resources/js/pages/**/*.tsx'] },
    build: { chunkSizeWarningLimit: 1600 },
});
