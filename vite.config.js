import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    const hmrHost = env.VITE_HMR_HOST || 'distrack.test';
    const hmrProtocol = env.VITE_HMR_PROTOCOL || 'ws';
    const hmrClientPort = Number(env.VITE_HMR_CLIENT_PORT || 5173);

    const port = env.VITE_PORT ? Number(env.VITE_PORT) : 5173;
    const devServerOrigin = (env.VITE_DEV_SERVER_ORIGIN || '').replace(/\/$/, '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            cors: true,
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
            hmr: {
                host: hmrHost,
                protocol: hmrProtocol,
                clientPort: hmrClientPort,
            },
            ...(devServerOrigin ? { origin: devServerOrigin } : {}),
        },
    };
});
