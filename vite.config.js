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
    server: {
        // Allow Herd's *.test domain to reach the dev server (Vite 6 blocks
        // unknown hosts by default).
        allowedHosts: [".test"],
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
