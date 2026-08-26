import { defineConfig } from "vite";
import inertia from "@inertiajs/vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        // Serves SSR from the dev server, so `npm run dev` needs no separate
        // Node process. The entry is named because app.js has its own `setup`
        // callback, which stops the plugin generating one.
        inertia({
            ssr: {
                entry: "resources/js/ssr.js",
            },
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            // Hugeicons puts its pure annotations in a position Rollup will
            // not accept, and warns once per icon file: ~11,000 lines burying
            // anything real in a deploy log, over a comment Rollup then drops
            // harmlessly. Nothing to fix our side.
            onwarn(warning, warn) {
                const from = warning.id ?? warning.loc?.file ?? '';

                if (warning.code === 'INVALID_ANNOTATION' && from.includes('@hugeicons-pro')) {
                    return;
                }

                warn(warning);
            },
        },
    },
    server: {
        // Allow Herd's *.test domain to reach the dev server (Vite 6 blocks
        // unknown hosts by default).
        allowedHosts: [".test"],
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
