import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/uploader.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            output: {
                // Copy PDF.js worker to public directory
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.includes('pdf.worker')) {
                        return 'build/assets/pdf.worker.[hash].js';
                    }
                    return 'build/assets/[name].[hash][extname]';
                },
            },
        },
    },
    resolve: {
        alias: {
            'pdfjs-dist': resolve(__dirname, 'node_modules/pdfjs-dist'),
        },
    },
});
