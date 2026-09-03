import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const publicUrl = env.VITE_PUBLIC_URL;

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

            ...(publicUrl
                ? {
                    origin: publicUrl,
                    allowedHosts: [new URL(publicUrl).hostname],
                    hmr: {
                        protocol: 'wss',
                        host: new URL(publicUrl).hostname,
                        clientPort: 443,
                    },
                }
                : {
                    hmr: {
                        host: 'localhost',
                        port: 5173,
                    },
                }),
        },
    };
});