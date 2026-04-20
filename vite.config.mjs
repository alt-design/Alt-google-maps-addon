import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        statamic(),
        laravel({
            input: ['resources/js/cp.js', 'resources/css/cp.css'],
            publicDirectory: 'resources/dist',
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, '../../../vendor/statamic/cms/resources/js'),
        },
    },
});
