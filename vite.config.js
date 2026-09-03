import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
            ],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],

    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true,
        ...(env.VITE_PUBLIC_URL
            ? (() => {
                const publicUrl = new URL(env.VITE_PUBLIC_URL);

                return {
                    origin: env.VITE_PUBLIC_URL,
                    allowedHosts: [publicUrl.hostname],
                    hmr: {
                        protocol: publicUrl.protocol === 'https:' ? 'wss' : 'ws',
                        host: publicUrl.hostname,
                        clientPort: publicUrl.protocol === 'https:' ? 443 : publicUrl.port || 80,
                    },
                };
            })()
            : {
                hmr: {
                    host: 'localhost',
                    port: 5173,
                },
            }),
    },
    };
});
