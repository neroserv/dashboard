import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { createLogger, defineConfig } from 'vite';

const viteLogger = createLogger();

function skipPublicImageUrl(msg: string): boolean {
    return (
        msg.includes("didn't resolve at build time") &&
        (msg.includes('/images/') || msg.includes('\\images\\'))
    );
}

const viteWarn = viteLogger.warn.bind(viteLogger);
viteLogger.warn = (msg, options) => {
    if (typeof msg === 'string' && skipPublicImageUrl(msg)) {
        return;
    }
    viteWarn(msg, options);
};

const viteWarnOnce = viteLogger.warnOnce.bind(viteLogger);
viteLogger.warnOnce = (msg, options) => {
    if (typeof msg === 'string' && skipPublicImageUrl(msg)) {
        return;
    }
    viteWarnOnce(msg, options);
};

export default defineConfig({
    customLogger: viteLogger,
    css: {
        preprocessorOptions: {
            scss: {
                silenceDeprecations: [
                    'import',
                    'global-builtin',
                    'color-functions',
                    'if-function',
                ],
            },
        },
    },
    optimizeDeps: {
        include: ['motion-v'],
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
