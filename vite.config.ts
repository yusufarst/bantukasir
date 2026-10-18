import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

// Vite runs at build time only (D-005). No server-side rendering bundle is
// built (STACK_AND_MODULES section 1).
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/.claude/**', '**/Claude outputs/**'],
        },
    },
});
