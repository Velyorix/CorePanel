import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { allViteInputs } from './scripts/discover-theme-vite-entries.mjs';

export default defineConfig({
    plugins: [
        laravel({
            input: allViteInputs(),
            refresh: [
                'resources/views/**',
                'Themes/**',
            ],
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
